<?php

namespace App\Support;

use App\Models\AttemptSchedule;
use App\Models\PresentationDate;

/**
 * What a deferred group is told on its Group Status card (user-directed
 * 2026-10-12): how to resolve the defer, keyed by the defer reason, and what
 * the system does if it is not resolved. The second line follows what
 * QueueAdjustmentService::processEndOfDay() actually does — a group still
 * deferred when its day ends goes to the end of the queue on the next open
 * presentation date, or waits for a new one when none is left.
 */
class DeferGuidance
{
    private const RESOLVE = [
        'PAYMENT_VERIFICATION_CONCERN' => 'Settle the payment and show your official receipt or reference number to the administrator. Once it is verified, the administrator can return your group to the queue.',
        'NOT_PRESENT_WHEN_CALLED' => 'Report to the administrator as soon as your group is at the venue and ready. The administrator can return your group to the queue.',
        'INCOMPLETE_GROUP' => 'Report to the administrator once every member is present. The administrator can return your group to the queue.',
        'NOT_READY_TO_PRESENT' => 'Tell the administrator once your group is ready to present. The administrator can return your group to the queue.',
        'SYSTEM_NOT_WORKING' => 'Fix your system or prototype, then tell the administrator your group is ready. The administrator can return your group to the queue.',
        'INCOMPLETE_REQUIREMENTS' => 'Complete the missing requirements and submit them to the administrator. The administrator can then return your group to the queue.',
        'TECHNICAL_ADVISER_ABSENT' => 'Have your technical adviser report to the venue, then tell the administrator. The administrator can return your group to the queue.',
        'MEDICAL_EMERGENCY' => 'Coordinate with the administrator about when your group can present.',
    ];

    private const RESOLVE_DEFAULT = 'Coordinate with the administrator. Once the concern is settled, the administrator can return your group to the queue.';

    public static function howToResolve(?string $reasonCode): string
    {
        return self::RESOLVE[$reasonCode] ?? self::RESOLVE_DEFAULT;
    }

    public static function ifUnresolved(?AttemptSchedule $schedule): string
    {
        $date = $schedule?->presentationDateRoom?->presentationDate;

        if (! $date) {
            return 'Your group waits until the administrator schedules it again.';
        }

        $next = self::nextOpenDate($date);

        if ($date->completed_at) {
            return $next
                ? 'Your group will be placed at the end of the queue on ' . $next->presentation_date->format('M j, Y') . '.'
                : 'Your group will be placed at the end of the queue once the administrator sets a new presentation date.';
        }

        return $next
            ? 'If it is not resolved before the day ends, your group is moved to the end of the queue on ' . $next->presentation_date->format('M j, Y') . '.'
            : 'If it is not resolved before the day ends, your group waits until the administrator sets a new presentation date, then goes to the end of that queue.';
    }

    private static function nextOpenDate(PresentationDate $date): ?PresentationDate
    {
        return PresentationDate::where('category_id', $date->category_id)
            ->where('id', '!=', $date->id)
            ->whereNull('completed_at')
            ->where(fn ($q) => $q->where('presentation_date', '>', $date->presentation_date)
                ->orWhere(fn ($same) => $same->where('presentation_date', $date->presentation_date)->where('id', '>', $date->id)))
            ->chronological()
            ->with('eventDateStatus')
            ->get()
            ->first(fn (PresentationDate $candidate) => $candidate->isOpenForScheduling());
    }
}

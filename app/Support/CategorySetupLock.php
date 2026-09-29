<?php

namespace App\Support;

use App\Models\PresentationCategory;
use Illuminate\Validation\ValidationException;

/**
 * Presentation Setup takes no further writes once a category has ended
 * (PresentationCategory::isCompleted() — set by EventActivationService::
 * completeCategory(), see its own doc comment). User-directed 2026-09-17:
 * this used to only guard the handful of actions that add new schedule
 * structure (PresentationDateController::storeSpan(),
 * CategoryRoomController::applyToAllDates()/assignToDates(),
 * PanelAssignmentController::storeGroup()) — every other Presentation Setup
 * write (editing project info, panel count, schedule/queue/payment/
 * evaluation configuration, removing a date/room/break, announcements,
 * deleting the category itself) now goes through this same check, so an
 * ended category is read-only rather than only partly locked. Reports is the
 * one place an ended category's data is still meant to be worked with; the
 * category stays visible (and still viewable) in Presentation Setup's own
 * list and workspace until it's explicitly archived — this only blocks
 * writes, never the read/show path.
 */
class CategorySetupLock
{
    public static function guard(PresentationCategory $category, string $action): void
    {
        if ($category->isCompleted()) {
            throw ValidationException::withMessages([
                'category' => "Cannot {$action} — this category has ended.",
            ]);
        }
    }
}

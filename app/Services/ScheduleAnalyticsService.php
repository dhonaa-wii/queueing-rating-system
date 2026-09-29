<?php

namespace App\Services;

use App\Models\PresentationCategory;
use App\Models\PresentationRun;
use App\Models\RoomSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Schedule reality — the analytics half of Reports & Analytics.
 *
 * Reports answers "what is the official record" (grades, sign-off sheets).
 * This answers the one question no report does: does the schedule the admin
 * configured in Presentation Setup match what actually happened? Every figure
 * here exists to be compared against a number the admin can go and change —
 * `category_schedule_settings.duration_minutes` above all, which is also what
 * CapacityAnalysisService plans every room's capacity from.
 *
 * Cross-category by default (user-directed 2026-09-17), optionally narrowed to
 * one category. Computed on demand, never persisted — same convention as
 * CapacityAnalysisService, and the reason `capacity_analysis_snapshots` still
 * has no writer.
 *
 * What counts as real:
 *  - Only `presentation_runs` rows that actually completed and recorded a
 *    duration. A run is written by the room-session tablet, so a group pushed
 *    through Live Monitoring's admin override has no run and is not measured.
 *  - Only room-days that actually ran, i.e. those with a `room_sessions` row.
 *    A cancelled or never-started day never gets one, so it contributes
 *    nothing to booked time and cannot drag utilization down — user-directed:
 *    "cancelled sched means nothing".
 *  - `actual_duration_seconds` already excludes paused time (see
 *    PresentationControlService::complete()), so presenting + paused is the
 *    real wall-clock the room spent on a group.
 */
class ScheduleAnalyticsService
{
    /** Ratio bands for actual-vs-configured, in percent (upper bound, label). */
    private const DURATION_BANDS = [
        [25, '0-25%'],
        [50, '26-50%'],
        [75, '51-75%'],
        [100, '76-100%'],
        [PHP_INT_MAX, 'Over'],
    ];

    /** Call-to-start lag bands, in seconds (upper bound, label). */
    private const LAG_BANDS = [
        [30, 'Under 30s'],
        [120, '30s-2m'],
        [300, '2-5m'],
        [900, '5-15m'],
        [PHP_INT_MAX, 'Over 15m'],
    ];

    /**
     * A category needs at least this many measured presentations before its
     * timings are offered as a duration recommendation. Below it the sample is
     * noise and the page says so rather than advising a config change.
     */
    public const MIN_SAMPLE = 5;

    public function analyze(?PresentationCategory $category = null): array
    {
        $runs = $this->runs($category);
        $sessions = $this->sessions($category);

        $bookedSeconds = (int) $sessions->sum('booked_seconds');
        $presentingSeconds = (int) $runs->sum('actual_duration_seconds');
        $pausedSeconds = (int) $runs->sum('total_paused_seconds');

        $actuals = $runs->pluck('actual_duration_seconds')->sort()->values();
        $configured = $runs->pluck('configured_duration_seconds')->filter()->sort()->values();
        $lags = $runs->pluck('lag_seconds')->filter(fn ($v) => $v !== null)->sort()->values();

        $medianActual = $this->median($actuals);
        $medianConfigured = $this->median($configured);

        return [
            'has_data' => $runs->isNotEmpty() || $sessions->isNotEmpty(),
            'measured' => $runs->count(),
            'thin_sample' => $runs->count() > 0 && $runs->count() < self::MIN_SAMPLE,
            'stats' => [
                'presentations' => $runs->count(),
                'groups' => $runs->pluck('group_id')->filter()->unique()->count(),
                'median_actual' => $medianActual,
                'avg_actual' => $actuals->isNotEmpty() ? (int) round($actuals->avg()) : null,
                'configured' => $medianConfigured,
                'delta_pct' => $medianActual !== null && $medianConfigured
                    ? (int) round(($medianActual - $medianConfigured) / $medianConfigured * 100)
                    : null,
                'presenting_seconds' => $presentingSeconds,
                'paused_seconds' => $pausedSeconds,
                'booked_seconds' => $bookedSeconds,
                'utilization_pct' => $bookedSeconds > 0
                    ? (int) round(($presentingSeconds + $pausedSeconds) / $bookedSeconds * 100)
                    : null,
                'days' => $sessions->pluck('date_id')->unique()->count(),
                'room_days' => $sessions->count(),
                'rooms' => $sessions->pluck('room_name')->unique()->count(),
                'median_lag' => $this->median($lags),
                'max_lag' => $lags->isNotEmpty() ? (int) $lags->last() : null,
                'lag_count' => $lags->count(),
            ],
            'time_split' => $this->timeSplit($presentingSeconds, $pausedSeconds, $bookedSeconds),
            // One category selected means the by-category list would be a single
            // row, so the comparison breaks down by day instead — same question,
            // asked of the level that still has variation in it.
            'comparison' => $category
                ? $this->comparisonByDay($runs)
                : $this->byCategory($runs, $sessions),
            'comparison_by' => $category ? 'day' : 'category',
            'duration_bands' => $this->durationBands($runs),
            'lag_bands' => $this->lagBands($lags),
            'days' => $this->byDay($runs, $sessions),
            'rooms' => $this->byRoom($runs, $sessions),
            'recommendation' => $this->recommendation($runs, $medianConfigured),
        ];
    }

    /**
     * Every completed, timed run, flattened to the few fields the page needs so
     * nothing downstream has to walk relations row by row. Scoped through the
     * run's own room session rather than the attempt's current schedule — the
     * session is where it actually presented, and a later transfer must not
     * retroactively move a finished presentation into another room.
     */
    private function runs(?PresentationCategory $category): Collection
    {
        return PresentationRun::query()
            ->whereNotNull('completed_at')
            ->whereNotNull('actual_duration_seconds')
            ->when($category, fn ($q) => $q->whereHas(
                'roomSession.presentationDateRoom.presentationDate',
                fn ($d) => $d->where('category_id', $category->id),
            ))
            ->with([
                'roomSession.presentationDateRoom.presentationDate.category:id,name',
                'presentationAttempt:id,research_group_id',
            ])
            ->get()
            ->map(function (PresentationRun $run) {
                $room = $run->roomSession?->presentationDateRoom;
                $date = $room?->presentationDate;

                return (object) [
                    'actual_duration_seconds' => (int) $run->actual_duration_seconds,
                    'configured_duration_seconds' => (int) $run->configured_duration_seconds,
                    'total_paused_seconds' => (int) $run->total_paused_seconds,
                    'extended_seconds' => (int) $run->extended_seconds,
                    'lag_seconds' => $run->called_at && $run->started_at
                        ? max(0, $run->started_at->getTimestamp() - $run->called_at->getTimestamp())
                        : null,
                    'completed_at' => $run->completed_at,
                    'group_id' => $run->presentationAttempt?->research_group_id,
                    'room_id' => $room?->id,
                    'room_name' => $room?->room_name,
                    'date_id' => $date?->id,
                    'category_id' => $date?->category_id,
                    'category_name' => $date?->category?->name,
                ];
            })
            ->filter(fn ($row) => $row->date_id !== null)
            ->values();
    }

    /**
     * Room-days that genuinely ran. A room session only exists once Start Room
     * has been pressed, which is exactly the "it happened" test — so a
     * cancelled or never-started day drops out on its own, with no status
     * filtering needed.
     */
    private function sessions(?PresentationCategory $category): Collection
    {
        return RoomSession::query()
            ->when($category, fn ($q) => $q->whereHas(
                'presentationDateRoom.presentationDate',
                fn ($d) => $d->where('category_id', $category->id),
            ))
            ->with('presentationDateRoom.presentationDate.category:id,name')
            ->get()
            ->map(function (RoomSession $session) {
                $room = $session->presentationDateRoom;
                $date = $room?->presentationDate;

                if (! $room || ! $date) {
                    return null;
                }

                $day = $date->presentation_date->toDateString();
                $start = Carbon::parse($day . ' ' . ($room->room_start_time ?? $date->event_start_time));
                $end = Carbon::parse($day . ' ' . ($room->room_end_time ?? $date->event_end_time));

                return (object) [
                    'booked_seconds' => max(0, $end->getTimestamp() - $start->getTimestamp()),
                    'planned_start' => $start,
                    'planned_end' => $end,
                    'started_at' => $session->started_at,
                    'ended_at' => $session->ended_at,
                    'room_id' => $room->id,
                    'room_name' => $room->room_name,
                    'date_id' => $date->id,
                    'date' => $date->presentation_date,
                    'category_id' => $date->category_id,
                    'category_name' => $date->category?->name,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Booked room time split into what it was actually spent on. Idle is
     * whatever is left over — turnaround between groups, a late start, an early
     * finish — and is floored at zero, since a day run past its planned window
     * would otherwise make it negative.
     */
    private function timeSplit(int $presenting, int $paused, int $booked): array
    {
        $idle = max(0, $booked - $presenting - $paused);
        $total = max(1, $presenting + $paused + $idle);

        return [
            'total' => $presenting + $paused + $idle,
            'segments' => [
                ['key' => 'presenting', 'label' => 'Presenting', 'seconds' => $presenting, 'pct' => round($presenting / $total * 100, 1), 'color' => 'var(--brand-accent)'],
                ['key' => 'paused', 'label' => 'Paused', 'seconds' => $paused, 'pct' => round($paused / $total * 100, 1), 'color' => 'var(--brand-danger)'],
                ['key' => 'idle', 'label' => 'Idle / turnaround', 'seconds' => $idle, 'pct' => round($idle / $total * 100, 1), 'color' => 'var(--brand-info)'],
            ],
        ];
    }

    private function byCategory(Collection $runs, Collection $sessions): Collection
    {
        $bookedByCategory = $sessions->groupBy('category_id')->map->sum('booked_seconds');

        return $runs->groupBy('category_id')
            ->map(function (Collection $rows, $categoryId) use ($bookedByCategory) {
                $actuals = $rows->pluck('actual_duration_seconds')->sort()->values();
                $configured = $this->median($rows->pluck('configured_duration_seconds')->filter()->sort()->values());
                $median = $this->median($actuals);
                $booked = (int) ($bookedByCategory[$categoryId] ?? 0);
                $used = (int) $rows->sum('actual_duration_seconds') + (int) $rows->sum('total_paused_seconds');

                return [
                    'id' => $categoryId,
                    'name' => $rows->first()->category_name ?? 'Unknown category',
                    'count' => $rows->count(),
                    'configured' => $configured,
                    'median' => $median,
                    'longest' => (int) $actuals->last(),
                    'over_slot' => $rows->filter(fn ($r) => $r->extended_seconds > 0)->count(),
                    'ratio_pct' => $median !== null && $configured ? (int) round($median / $configured * 100) : null,
                    'utilization_pct' => $booked > 0 ? (int) round($used / $booked * 100) : null,
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /**
     * The same configured-vs-actual comparison as byCategory(), cut by day —
     * used when the page is filtered to one category, where a per-category list
     * would be one row. Days with no timed presentation are left out: they have
     * nothing to compare.
     */
    private function comparisonByDay(Collection $runs): Collection
    {
        return $runs->groupBy('date_id')
            ->map(function (Collection $rows) {
                $actuals = $rows->pluck('actual_duration_seconds')->sort()->values();
                $configured = $this->median($rows->pluck('configured_duration_seconds')->filter()->sort()->values());
                $median = $this->median($actuals);
                $completed = $rows->max('completed_at');

                return [
                    'id' => $rows->first()->date_id,
                    'name' => $completed?->format('M j, Y') ?? 'Unknown day',
                    'sort' => $completed,
                    'count' => $rows->count(),
                    'configured' => $configured,
                    'median' => $median,
                    'longest' => (int) $actuals->last(),
                    'over_slot' => $rows->filter(fn ($r) => $r->extended_seconds > 0)->count(),
                    'ratio_pct' => $median !== null && $configured ? (int) round($median / $configured * 100) : null,
                    'utilization_pct' => null,
                ];
            })
            ->sortByDesc('sort')
            ->values();
    }
    private function durationBands(Collection $runs): Collection
    {
        $counts = array_fill(0, count(self::DURATION_BANDS), 0);

        foreach ($runs as $run) {
            if (! $run->configured_duration_seconds) {
                continue;
            }

            $pct = $run->actual_duration_seconds / $run->configured_duration_seconds * 100;

            foreach (self::DURATION_BANDS as $i => $band) {
                if ($pct <= $band[0]) {
                    $counts[$i]++;
                    break;
                }
            }
        }

        return collect(self::DURATION_BANDS)->map(fn ($band, $i) => [
            'label' => $band[1],
            'count' => $counts[$i],
            'over' => $band[0] === PHP_INT_MAX,
        ]);
    }

    private function lagBands(Collection $lags): Collection
    {
        $counts = array_fill(0, count(self::LAG_BANDS), 0);

        foreach ($lags as $lag) {
            foreach (self::LAG_BANDS as $i => $band) {
                if ($lag <= $band[0]) {
                    $counts[$i]++;
                    break;
                }
            }
        }

        return collect(self::LAG_BANDS)->map(fn ($band, $i) => [
            'label' => $band[1],
            'count' => $counts[$i],
            'over' => $band[0] === PHP_INT_MAX,
        ]);
    }

    /**
     * One row per presentation day that ran. "Over" is how far past the planned
     * window the last group actually finished — the honest end-of-day drift,
     * read from real completion times rather than any planned column.
     */
    private function byDay(Collection $runs, Collection $sessions): Collection
    {
        $runsByDate = $runs->groupBy('date_id');

        return $sessions->groupBy('date_id')
            ->map(function (Collection $roomDays, $dateId) use ($runsByDate) {
                $rows = $runsByDate->get($dateId, collect());
                $booked = (int) $roomDays->sum('booked_seconds');
                $used = (int) $rows->sum('actual_duration_seconds') + (int) $rows->sum('total_paused_seconds');
                $plannedEnd = $roomDays->max('planned_end');
                $lastCompleted = $rows->max('completed_at');

                return [
                    'date' => $roomDays->first()->date,
                    'category' => $roomDays->first()->category_name,
                    'rooms' => $roomDays->pluck('room_name')->unique()->count(),
                    'groups' => $rows->count(),
                    'booked_seconds' => $booked,
                    'used_seconds' => $used,
                    'utilization_pct' => $booked > 0 ? (int) round($used / $booked * 100) : null,
                    'median' => $this->median($rows->pluck('actual_duration_seconds')->sort()->values()),
                    'over_seconds' => $plannedEnd && $lastCompleted
                        ? max(0, $lastCompleted->getTimestamp() - $plannedEnd->getTimestamp())
                        : null,
                ];
            })
            ->sortByDesc('date')
            ->values();
    }

    private function byRoom(Collection $runs, Collection $sessions): Collection
    {
        $runsByRoom = $runs->groupBy('room_name');

        return $sessions->groupBy('room_name')
            ->map(function (Collection $roomDays, $roomName) use ($runsByRoom) {
                $rows = $runsByRoom->get($roomName, collect());
                $booked = (int) $roomDays->sum('booked_seconds');
                $used = (int) $rows->sum('actual_duration_seconds') + (int) $rows->sum('total_paused_seconds');

                return [
                    'name' => $roomName ?: 'Unnamed room',
                    'days' => $roomDays->count(),
                    'groups' => $rows->count(),
                    'booked_seconds' => $booked,
                    'used_seconds' => $used,
                    'utilization_pct' => $booked > 0 ? (int) round($used / $booked * 100) : null,
                ];
            })
            ->sortByDesc('utilization_pct')
            ->values();
    }

    /**
     * The whole point of the page: a slot length to actually put back into
     * Presentation Setup. Suppressed below MIN_SAMPLE — advising a config
     * change off three test clicks would be worse than saying nothing.
     */
    private function recommendation(Collection $runs, ?int $medianConfigured): ?array
    {
        if ($runs->count() < self::MIN_SAMPLE || ! $medianConfigured) {
            return null;
        }

        $actuals = $runs->pluck('actual_duration_seconds')->sort()->values();

        // The 90th percentile, not the median — a slot has to fit almost every
        // group, not the middle one, or half the day runs late by design.
        $p90 = (int) $actuals[(int) floor(($actuals->count() - 1) * 0.9)];
        $suggested = max(1, (int) ceil($p90 / 60));
        $configuredMinutes = (int) round($medianConfigured / 60);

        if ($suggested === $configuredMinutes) {
            return null;
        }

        return [
            'suggested' => $suggested,
            'configured' => $configuredMinutes,
            'sample' => $runs->count(),
            'direction' => $suggested < $configuredMinutes ? 'shorter' : 'longer',
        ];
    }

    private function median(Collection $sorted): ?int
    {
        if ($sorted->isEmpty()) {
            return null;
        }

        $count = $sorted->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (int) $sorted[$middle]
            : (int) round(($sorted[$middle - 1] + $sorted[$middle]) / 2);
    }
}

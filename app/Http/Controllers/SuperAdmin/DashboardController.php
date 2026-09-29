<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\College;
use App\Models\PresentationCategory;
use App\Models\Semester;
use App\Models\User;

class DashboardController extends Controller
{
    /** Days of history behind the audit activity column chart. */
    private const ACTIVITY_DAYS = 14;

    /** account_statuses codes, in the order every status breakdown renders. */
    private const STATUS_ORDER = ['ACTIVE', 'TEMPORARY_CREDENTIALS_ISSUED', 'PASSWORD_RESET_REQUIRED', 'INACTIVE', 'LOCKED'];

    public function index()
    {
        $user = auth()->user();
        $now = now();

        $admins = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'ADMIN'))
            ->with('accountStatus')->get();
        $panelists = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->with('accountStatus')->get();

        $statuses = AccountStatus::all()->keyBy('code');
        $breakdown = fn ($users) => collect(self::STATUS_ORDER)
            ->map(fn ($code) => [
                'code' => $code,
                'label' => $statuses[$code]->name ?? $code,
                'count' => $users->filter(fn (User $u) => $u->accountStatus?->code === $code)->count(),
            ])
            ->filter(fn ($row) => $row['count'] > 0)
            ->values();

        $adminActive = $admins->filter(fn (User $a) => $a->accountStatus?->code === 'ACTIVE')->count();
        $lockedAdmins = $admins->filter(fn (User $a) => $a->accountStatus?->code === 'LOCKED')->count();
        $panelistActive = $panelists->filter(fn (User $p) => $p->accountStatus?->code === 'ACTIVE')->count();

        $campuses = Campus::withCount([
            'colleges',
            'colleges as active_colleges_count' => fn ($q) => $q->where('is_active', true),
        ])->orderBy('name')->get();
        $colleges = College::all();

        $categories = PresentationCategory::with('categoryStatus')->get();
        $categories->each->refreshStatus();
        $activeCategories = $categories->filter(fn ($c) => ! $c->categoryStatus?->is_terminal)->count();

        $auditDaily = collect(range(0, self::ACTIVITY_DAYS - 1))->map(function ($i) use ($now) {
            $date = $now->copy()->subDays(self::ACTIVITY_DAYS - 1 - $i)->startOfDay();

            return [
                'label' => $date->format('M j'),
                'count' => AuditLog::whereBetween('created_at', [$date, $date->copy()->endOfDay()])->count(),
            ];
        });

        $auditToday = $auditDaily->last()['count'];
        $auditYesterday = $auditDaily->count() > 1 ? $auditDaily[$auditDaily->count() - 2]['count'] : 0;

        $recentAuditLogs = AuditLog::with('user.profile')->latest()->limit(8)->get();

        return view('super-admin.dashboard', [
            'firstName' => $user->profile->first_name ?? $user->username,
            'greeting' => match (true) {
                $now->hour < 12 => 'Good morning',
                $now->hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'academicYear' => AcademicYear::where('is_active', true)->first(),
            'semester' => Semester::where('is_active', true)->first(),

            'adminTotal' => $admins->count(),
            'adminActive' => $adminActive,
            'lockedAdmins' => $lockedAdmins,
            'adminBreakdown' => $breakdown($admins),

            'panelistTotal' => $panelists->count(),
            'panelistActive' => $panelistActive,
            'panelistBreakdown' => $breakdown($panelists),

            'campuses' => $campuses,
            'activeCampuses' => $campuses->where('is_active', true)->count(),
            'collegeTotal' => $colleges->count(),
            'activeColleges' => $colleges->where('is_active', true)->count(),

            'totalCategories' => $categories->count(),
            'activeCategories' => $activeCategories,

            'auditDaily' => $auditDaily,
            'auditToday' => $auditToday,
            'auditYesterday' => $auditYesterday,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }
}

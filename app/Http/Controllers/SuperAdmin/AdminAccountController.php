<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AccountStatus;
use App\Models\AdministratorProfile;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminAccountController extends Controller
{
    public function index()
    {
        $admins = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'ADMIN'))
            ->with(['profile', 'accountStatus', 'administratorProfile.college'])
            ->orderBy('username')
            ->get();

        return view('super-admin.administrators.index', compact('admins'));
    }

    public function create()
    {
        return view('super-admin.administrators.create', [
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateAdmin($request);

        $temporaryStatus = AccountStatus::where('code', 'TEMPORARY_CREDENTIALS_ISSUED')->firstOrFail();
        $adminRole = Role::where('code', 'ADMIN')->firstOrFail();
        $temporaryPassword = Str::password(12, symbols: false);

        $admin = DB::transaction(function () use ($validated, $temporaryStatus, $adminRole, $temporaryPassword, $request) {
            $user = User::create([
                'username' => $validated['username'],
                'email' => $validated['email'] ?? null,
                'password' => $temporaryPassword,
                'account_status_id' => $temporaryStatus->id,
                'must_change_password' => true,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $adminRole->id,
                'assigned_by' => $request->user()->id,
            ]);

            UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
            ]);

            AdministratorProfile::create([
                'user_id' => $user->id,
                'college_id' => $validated['college_id'],
                'employee_reference' => $validated['employee_reference'] ?? null,
            ]);

            return $user;
        });

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'ADMIN_ACCOUNT_CREATED',
            'entity_type' => User::class,
            'entity_id' => $admin->id,
            'new_values' => ['username' => $admin->username],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('super-admin.administrators.index')
            ->with('status', "Administrator \"{$admin->username}\" created.")
            ->with('credential_reveal', [
                'title' => 'Administrator Created',
                'rows' => [
                    ['label' => 'Username', 'value' => $admin->username],
                    ['label' => 'Temporary Password', 'value' => $temporaryPassword],
                ],
                'note' => 'This password is shown once and cannot be retrieved again. If lost, use Reset Password to issue a new one.',
            ]);
    }

    public function edit(User $admin)
    {
        $this->ensureIsAdmin($admin);

        $admin->load(['profile', 'administratorProfile']);

        return view('super-admin.administrators.edit', [
            'admin' => $admin,
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $admin)
    {
        $this->ensureIsAdmin($admin);

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($admin->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'college_id' => ['required', Rule::exists('colleges', 'id')],
            'employee_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $admin->update(['email' => $validated['email'] ?? null]);

        $admin->profile()->updateOrCreate([], [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
        ]);

        $admin->administratorProfile()->updateOrCreate([], [
            'college_id' => $validated['college_id'],
            'employee_reference' => $validated['employee_reference'] ?? null,
        ]);

        return redirect()->route('super-admin.administrators.index')->with('status', 'Administrator updated.');
    }

    public function activate(Request $request, User $admin)
    {
        $this->ensureIsAdmin($admin);

        $activeStatus = AccountStatus::where('code', 'ACTIVE')->firstOrFail();
        $admin->update(['account_status_id' => $activeStatus->id]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'ADMIN_ACCOUNT_ACTIVATED',
            'entity_type' => User::class,
            'entity_id' => $admin->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', "\"{$admin->username}\" activated.");
    }

    public function deactivate(Request $request, User $admin)
    {
        $this->ensureIsAdmin($admin);

        $inactiveStatus = AccountStatus::where('code', 'INACTIVE')->firstOrFail();
        $admin->update(['account_status_id' => $inactiveStatus->id]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'ADMIN_ACCOUNT_DEACTIVATED',
            'entity_type' => User::class,
            'entity_id' => $admin->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', "\"{$admin->username}\" deactivated.");
    }

    public function resetPassword(Request $request, User $admin)
    {
        $this->ensureIsAdmin($admin);

        $temporaryStatus = AccountStatus::where('code', 'TEMPORARY_CREDENTIALS_ISSUED')->firstOrFail();
        $temporaryPassword = Str::password(12, symbols: false);

        $admin->forceFill([
            'password' => $temporaryPassword,
            'account_status_id' => $temporaryStatus->id,
            'must_change_password' => true,
        ])->save();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'ADMIN_PASSWORD_RESET',
            'entity_type' => User::class,
            'entity_id' => $admin->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()
            ->with('status', "Password for \"{$admin->username}\" reset.")
            ->with('credential_reveal', [
                'title' => 'Password Reset',
                'rows' => [
                    ['label' => 'Username', 'value' => $admin->username],
                    ['label' => 'Temporary Password', 'value' => $temporaryPassword],
                ],
                'note' => 'This password is shown once and cannot be retrieved again.',
            ]);
    }

    private function validateAdmin(Request $request): array
    {
        return $request->validate([
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'college_id' => ['required', Rule::exists('colleges', 'id')],
            'employee_reference' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function ensureIsAdmin(User $user): void
    {
        abort_unless($user->hasRole('ADMIN'), 404);
    }
}

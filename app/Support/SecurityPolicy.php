<?php

namespace App\Support;

use App\Models\SystemSetting;
use Closure;

/**
 * The Super Admin's Security Settings, read from `system_settings`.
 *
 * Every setting is optional. A missing row keeps the system's built-in
 * behaviour: no login lockout, the session lifetime from .env, the
 * 8+ characters / upper / lower / number password rule, and passwords that
 * never expire. Nothing is seeded — the Super Admin decides.
 */
class SecurityPolicy
{
    public const LOCKOUT = 'login_lockout';                 // {max_attempts, minutes}
    public const SESSION_TIMEOUT = 'session_timeout_minutes'; // int
    public const PASSWORD_RULES = 'password_rules';         // {min_length, uppercase, lowercase, number, symbol}
    public const PASSWORD_MAX_AGE = 'password_max_age_days'; // int

    public const DEFAULT_PASSWORD_RULES = [
        'min_length' => 8,
        'uppercase' => true,
        'lowercase' => true,
        'number' => true,
        'symbol' => false,
    ];

    private static ?array $values = null;

    public static function get(string $key): mixed
    {
        if (self::$values === null) {
            self::$values = SystemSetting::whereIn('setting_key', [
                self::LOCKOUT, self::SESSION_TIMEOUT, self::PASSWORD_RULES, self::PASSWORD_MAX_AGE,
            ])->pluck('setting_value', 'setting_key')->all();
        }

        return self::$values[$key] ?? null;
    }

    /** Drop the per-request copy after a save. */
    public static function forget(): void
    {
        self::$values = null;
    }

    /** @return array{max_attempts:int, minutes:int}|null  null = no lockout */
    public static function lockout(): ?array
    {
        $value = self::get(self::LOCKOUT);

        if (! is_array($value) || empty($value['max_attempts']) || empty($value['minutes'])) {
            return null;
        }

        return ['max_attempts' => (int) $value['max_attempts'], 'minutes' => (int) $value['minutes']];
    }

    /** Minutes of inactivity before sign-out; null = the .env default. */
    public static function sessionTimeout(): ?int
    {
        $value = self::get(self::SESSION_TIMEOUT);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public static function hasCustomPasswordRules(): bool
    {
        return is_array(self::get(self::PASSWORD_RULES));
    }

    /** @return array{min_length:int, uppercase:bool, lowercase:bool, number:bool, symbol:bool} */
    public static function passwordRules(): array
    {
        $value = self::get(self::PASSWORD_RULES);

        if (! is_array($value)) {
            return self::DEFAULT_PASSWORD_RULES;
        }

        return [
            'min_length' => max(1, (int) ($value['min_length'] ?? self::DEFAULT_PASSWORD_RULES['min_length'])),
            'uppercase' => (bool) ($value['uppercase'] ?? false),
            'lowercase' => (bool) ($value['lowercase'] ?? false),
            'number' => (bool) ($value['number'] ?? false),
            'symbol' => (bool) ($value['symbol'] ?? false),
        ];
    }

    /** Days a password stays valid; null = never expires. */
    public static function passwordMaxAgeDays(): ?int
    {
        $value = self::get(self::PASSWORD_MAX_AGE);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Validation rules for a password the user sets themselves. */
    public static function passwordValidationRules(): array
    {
        $rules = self::passwordRules();

        $checks = [
            'uppercase' => ['/[A-Z]/', 'an uppercase letter'],
            'lowercase' => ['/[a-z]/', 'a lowercase letter'],
            'number' => ['/\d/', 'a number'],
            'symbol' => ['/[^A-Za-z0-9]/', 'a symbol'],
        ];

        $list = ['min:'.$rules['min_length']];

        foreach ($checks as $key => [$pattern, $label]) {
            if ($rules[$key]) {
                $list[] = function (string $attribute, mixed $value, Closure $fail) use ($pattern, $label) {
                    if (! preg_match($pattern, (string) $value)) {
                        $fail("The password must contain {$label}.");
                    }
                };
            }
        }

        return $list;
    }
}

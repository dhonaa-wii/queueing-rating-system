<?php

namespace App\Support;

/**
 * Links that land on the exact element to act on. `focus` names the target's
 * data-focus key(s); resources/views/partials/focus-flash-script.blade.php
 * scrolls to and blinks every match on arrival.
 */
class FocusLink
{
    /** A fragment (e.g. a tab) has to come after the query string. */
    public static function to(string $url, array $keys, string $fragment = ''): string
    {
        if ($keys === []) {
            return $url . $fragment;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'focus=' . implode(',', $keys) . $fragment;
    }
}

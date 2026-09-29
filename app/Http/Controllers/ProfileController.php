<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->profile()->updateOrCreate([], $validated);

        return back()->with('status', 'Profile updated.');
    }
}

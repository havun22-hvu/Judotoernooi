<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function show()
    {
        $organisator = Auth::guard('organisator')->user();
        $devices = $organisator->authDevices()
            ->where('is_active', true)
            ->orderByDesc('last_used_at')
            ->get();

        return view('organisator.account', compact('organisator', 'devices'));
    }

    public function update(Request $request)
    {
        $organisator = Auth::guard('organisator')->user();

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:organisators,email,' . $organisator->id,
            'telefoon' => 'nullable|string|max:20',
            'locale' => 'required|in:nl,en',
        ]);

        $organisator->update($validated);

        // Set locale for current session
        app()->setLocale($validated['locale']);
        session()->put('locale', $validated['locale']);

        return back()->with('success', __('Gegevens opgeslagen.'));
    }

    public function updatePassword(Request $request)
    {
        $organisator = Auth::guard('organisator')->user();

        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, $organisator->password)) {
            return back()->withErrors(['current_password' => __('Het huidige wachtwoord is onjuist.')]);
        }

        $organisator->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('password_success', __('Wachtwoord gewijzigd.'));
    }

    public function removeDevice(Request $request, int $id)
    {
        $organisator = Auth::guard('organisator')->user();

        $device = $organisator->authDevices()->where('id', $id)->first();

        if (!$device) {
            return back()->withErrors(['device' => __('Apparaat niet gevonden.')]);
        }

        $device->update(['is_active' => false]);

        return back()->with('device_success', __('Apparaat verwijderd.'));
    }
}

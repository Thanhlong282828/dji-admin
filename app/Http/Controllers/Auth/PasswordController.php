<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $password = is_array($validated) ? ($validated['password'] ?? null) : null;
        abort_unless(is_string($password), 422);

        $user->update([
            'password' => Hash::make($password),
        ]);

        return back()->with('status', 'password-updated');
    }
}

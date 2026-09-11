<?php

namespace Modules\Iam\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Iam\Application\Commands\DeleteOwnAccountCommand;
use Modules\Iam\Application\Commands\UpdateProfileCommand;

class ProfileController extends Controller
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new UpdateProfileCommand($user, $request->validated()));

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        Auth::logout();

        $this->commands->dispatch(new DeleteOwnAccountCommand($user));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

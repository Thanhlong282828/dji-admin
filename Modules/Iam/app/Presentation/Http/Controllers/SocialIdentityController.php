<?php

namespace Modules\Iam\Presentation\Http\Controllers;

use App\Models\User;
use App\Support\Identity\ExternalIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

final readonly class SocialIdentityController
{
    public function __construct(private ExternalIdentityService $identities) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->enabled($provider);
        $request->session()->put('social_identity_mode', $request->user() instanceof User ? 'link' : 'login');

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->enabled($provider);
        $external = Socialite::driver($provider)->user();
        $mode = $request->session()->pull('social_identity_mode', 'login');
        try {
            if ($mode === 'link') {
                $user = $request->user();
                abort_unless($user instanceof User, 401);
                $this->identities->link($user, $provider, $external);

                return to_route('account.show')->with('status', ucfirst($provider).' đã được liên kết.');
            }
            $user = $this->identities->linkedUser($provider, $external);
        } catch (ValidationException $exception) {
            $target = $mode === 'link' ? route('account.show') : route('login');

            return redirect($target)->withErrors($exception->errors());
        }
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function unlink(Request $request, string $provider): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->identities->unlink($user, $provider);

        return to_route('account.show')->with('status', ucfirst($provider).' đã được ngắt liên kết.');
    }

    private function enabled(string $provider): void
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);
        abort_unless(config('services.'.$provider.'.enabled') === true, 404);
    }
}

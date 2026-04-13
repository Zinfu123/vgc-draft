<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PrepareDiscordLinkRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DiscordController extends Controller
{
    private const string OAUTH_INTENT_KEY = 'discord_oauth_intent';

    private const string DISCORD_LINK_USER_ID_KEY = 'discord_link_user_id';

    /**
     * Show the "link Discord to existing account" page.
     */
    public function showLinkForm(Request $request): Response
    {
        return Inertia::render('auth/LinkDiscord', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Redirect the user to Discord's OAuth page.
     * Works for linking (logged in), login (guest), registration (?intent=register),
     * or guest link-after-password (?intent=link with session from prepare-link).
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->query('intent') === 'register') {
            $request->session()->put(self::OAUTH_INTENT_KEY, 'register');
            $request->session()->forget(self::DISCORD_LINK_USER_ID_KEY);
        } elseif ($request->query('intent') === 'link') {
            if (! $request->session()->has(self::DISCORD_LINK_USER_ID_KEY)) {
                return redirect()->route('discord.link-form')->withErrors([
                    'link_email' => 'Your Discord link session expired. Enter your email and password below, then try again.',
                ]);
            }
            $request->session()->put(self::OAUTH_INTENT_KEY, 'link');
        } else {
            $request->session()->forget(self::OAUTH_INTENT_KEY);
            $request->session()->forget(self::DISCORD_LINK_USER_ID_KEY);
        }

        return Socialite::driver('discord')->redirect();
    }

    /**
     * Verify email/password for an existing account, then continue to Discord OAuth to link.
     */
    public function prepareLink(PrepareDiscordLinkRequest $request): RedirectResponse|SymfonyResponse
    {
        $email = $request->validated('link_email');
        $password = $request->validated('link_password');

        if (! Auth::validate([
            'email' => $email,
            'password' => $password,
        ])) {
            throw ValidationException::withMessages([
                'link_email' => trans('auth.failed'),
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'link_email' => trans('auth.failed'),
            ]);
        }

        $request->session()->put(self::DISCORD_LINK_USER_ID_KEY, $user->id);

        $next = route('discord.redirect', ['intent' => 'link']);

        if ($request->header('X-Inertia')) {
            return Inertia::location($next);
        }

        return redirect()->to($next);
    }

    /**
     * Handle the callback from Discord.
     * - Logged in: link the Discord account to the current user.
     * - Guest: link after prepare-link, log in by discord_id, register, or show an error.
     */
    public function callback(): RedirectResponse
    {
        $discordUser = Socialite::driver('discord')->user();

        if (Auth::check()) {
            $this->linkAccount(Auth::user(), $discordUser);

            return redirect()->route('profile.edit')->with('status', 'discord-linked');
        }

        $intent = session()->pull(self::OAUTH_INTENT_KEY, 'login');

        if ($intent === 'link') {
            return $this->completeGuestDiscordLink($discordUser);
        }

        $user = User::where('discord_id', $discordUser->getId())->first();

        if ($user) {
            Auth::login($user, remember: true);

            return redirect()->intended(route('dashboard'));
        }

        if ($intent === 'register') {
            return $this->registerGuestFromDiscord($discordUser);
        }

        return redirect()->route('discord.link-form')->withErrors([
            'link_email' => 'No account is linked to that Discord account. If you have an existing account, enter your credentials below to connect it.',
        ]);
    }

    private function completeGuestDiscordLink(\Laravel\Socialite\Contracts\User $discordUser): RedirectResponse
    {
        $userId = session()->pull(self::DISCORD_LINK_USER_ID_KEY);

        if ($userId === null) {
            return redirect()->route('discord.link-form')->withErrors([
                'link_email' => 'Your Discord link session expired. Verify your email and password, then try again.',
            ]);
        }

        /** @var User|null $accountUser */
        $accountUser = User::query()->find($userId);

        if ($accountUser === null) {
            return redirect()->route('discord.link-form')->withErrors([
                'link_email' => 'Your Discord link session expired. Verify your email and password, then try again.',
            ]);
        }

        $discordId = $discordUser->getId();

        $other = User::query()
            ->where('discord_id', $discordId)
            ->where('id', '!=', $accountUser->id)
            ->exists();

        if ($other) {
            return redirect()->route('discord.link-form')->withErrors([
                'link_email' => 'This Discord account is already linked to a different user.',
            ]);
        }

        $this->linkAccount($accountUser, $discordUser);

        Auth::login($accountUser, remember: true);

        return redirect()->intended(route('dashboard'))->with('status', 'discord-linked');
    }

    private function linkAccount(User $user, \Laravel\Socialite\Contracts\User $discordUser): void
    {
        $user->discord_id = $discordUser->getId();
        $user->discord_username = $discordUser->getNickname() ?? $discordUser->getName();
        $user->save();
    }

    private function registerGuestFromDiscord(\Laravel\Socialite\Contracts\User $discordUser): RedirectResponse
    {
        $email = $discordUser->getEmail();

        if ($email === null || $email === '') {
            return redirect()->route('register')->withErrors([
                'email' => 'Discord did not provide an email. Allow email access for this app in Discord, or create an account with email below.',
            ]);
        }

        $email = strtolower($email);

        if (User::query()->where('email', $email)->exists()) {
            return redirect()->route('register')->withErrors([
                'email' => 'An account with this email already exists. Log in with email, use “Link Discord to an existing account” on the login page, or link Discord from profile after signing in.',
            ]);
        }

        $name = $discordUser->getNickname() ?? $discordUser->getName() ?? 'Trainer';

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::password(64)),
            'discord_id' => $discordUser->getId(),
            'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}

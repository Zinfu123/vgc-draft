<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PrepareDiscordLinkRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DiscordController extends Controller
{
    private const string DISCORD_LINK_USER_ID_KEY = 'discord_link_user_id';

    private const int OAUTH_STATE_TTL_MINUTES = 10;

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
     *
     * Discord OAuth is driven statelessly so the round-trip survives mobile flows
     * where the Discord app intercepts the authorize URL and the system browser
     * loses the originating session cookie. All flow context (intent and the
     * guest link user id) is carried through Discord via an encrypted, expiring
     * `state` parameter that only this app can mint or verify.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $queryIntent = $request->query('intent');
        $intent = 'login';
        $linkUserId = null;

        if ($queryIntent === 'register') {
            $intent = 'register';
        } elseif ($queryIntent === 'link') {
            $linkUserId = $request->session()->pull(self::DISCORD_LINK_USER_ID_KEY);

            if ($linkUserId === null) {
                return redirect()->route('discord.link-form')->withErrors([
                    'link_email' => 'Your Discord link session expired. Enter your email and password below, then try again.',
                ]);
            }

            $intent = 'link';
        }

        $state = $this->buildOAuthState($intent, $linkUserId === null ? null : (int) $linkUserId);

        return Socialite::driver('discord')
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
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
     *
     * - Logged in: link the Discord account to the current user.
     * - Guest: link after prepare-link, log in by discord_id, register, or show an error.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->stateless()->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'Discord authentication failed. Please try again.',
            ]);
        }

        $stateData = $this->parseOAuthState($request->query('state'));

        if (Auth::check()) {
            $this->linkAccount(Auth::user(), $discordUser);

            return redirect()->route('profile.edit')->with('status', 'discord-linked');
        }

        if ($stateData['intent'] === 'link') {
            return $this->completeGuestDiscordLink($discordUser, $stateData['link_user_id']);
        }

        $user = User::where('discord_id', $discordUser->getId())->first();

        if ($user) {
            Auth::login($user, remember: true);

            return redirect()->intended(route('dashboard'));
        }

        if ($stateData['intent'] === 'register') {
            return $this->registerGuestFromDiscord($discordUser);
        }

        return redirect()->route('discord.link-form')->withErrors([
            'link_email' => 'No account is linked to that Discord account. If you have an existing account, enter your credentials below to connect it.',
        ]);
    }

    private function completeGuestDiscordLink(\Laravel\Socialite\Contracts\User $discordUser, ?int $linkUserId): RedirectResponse
    {
        if ($linkUserId === null) {
            return redirect()->route('discord.link-form')->withErrors([
                'link_email' => 'Your Discord link session expired. Verify your email and password, then try again.',
            ]);
        }

        /** @var User|null $accountUser */
        $accountUser = User::query()->find($linkUserId);

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
        $user->discord_avatar_url = $discordUser->getAvatar() ?: null;
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
            'discord_avatar_url' => $discordUser->getAvatar() ?: null,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * @return non-empty-string
     */
    private function buildOAuthState(string $intent, ?int $linkUserId): string
    {
        return Crypt::encryptString(json_encode([
            'intent' => $intent,
            'link_user_id' => $linkUserId,
            'expires_at' => now()->addMinutes(self::OAUTH_STATE_TTL_MINUTES)->timestamp,
            'nonce' => Str::random(16),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{intent: 'login'|'register'|'link', link_user_id: ?int}
     */
    private function parseOAuthState(mixed $state): array
    {
        $default = ['intent' => 'login', 'link_user_id' => null];

        if (! is_string($state) || $state === '') {
            return $default;
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return $default;
        }

        if (! is_array($payload)) {
            return $default;
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);

        if ($expiresAt < now()->timestamp) {
            return $default;
        }

        $intent = $payload['intent'] ?? 'login';

        if (! in_array($intent, ['login', 'register', 'link'], strict: true)) {
            $intent = 'login';
        }

        $linkUserId = $payload['link_user_id'] ?? null;
        $linkUserId = is_int($linkUserId) ? $linkUserId : null;

        return ['intent' => $intent, 'link_user_id' => $linkUserId];
    }
}

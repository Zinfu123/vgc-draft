<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class DiscordController extends Controller
{
    /**
     * Redirect the user to Discord's OAuth page.
     * Works for both linking (logged in) and login (guest).
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('discord')->redirect();
    }

    /**
     * Handle the callback from Discord.
     * - Logged in: link the Discord account to the current user.
     * - Guest: log in by matching discord_id, or show an error.
     */
    public function callback(): RedirectResponse
    {
        $discordUser = Socialite::driver('discord')->user();

        if (Auth::check()) {
            $this->linkAccount(Auth::user(), $discordUser);

            return redirect()->route('profile.edit')->with('status', 'discord-linked');
        }

        $user = User::where('discord_id', $discordUser->getId())->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'No account is linked to that Discord account. Connect Discord from your profile settings after logging in.',
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Unlink Discord from the current user's account.
     */
    public function disconnect(): RedirectResponse
    {
        $user = Auth::user();
        $user->discord_id = null;
        $user->discord_username = null;
        $user->save();

        return redirect()->route('profile.edit')->with('status', 'discord-unlinked');
    }

    private function linkAccount(User $user, \Laravel\Socialite\Contracts\User $discordUser): void
    {
        $user->discord_id = $discordUser->getId();
        $user->discord_username = $discordUser->getNickname() ?? $discordUser->getName();
        $user->save();
    }
}

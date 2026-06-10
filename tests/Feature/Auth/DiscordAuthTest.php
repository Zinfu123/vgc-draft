<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function mockDiscordUser(string $id = '123456789', string $nickname = 'TestUser#1234', ?string $email = null): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getNickname')->andReturn($nickname);
    $socialiteUser->shouldReceive('getName')->andReturn($nickname);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getAvatar')->andReturn('');

    $provider = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('discord')->andReturn($provider);

    app()->instance(SocialiteFactory::class, $socialite);
}

/**
 * Build a Discord OAuth `state` payload identical to what DiscordController mints.
 * Used to drive the callback tests without depending on Socialite's session state.
 */
function discordOAuthState(string $intent = 'login', ?int $linkUserId = null): string
{
    return Crypt::encryptString(json_encode([
        'intent' => $intent,
        'link_user_id' => $linkUserId,
        'expires_at' => now()->addMinutes(10)->timestamp,
        'nonce' => 'test-nonce',
    ], JSON_THROW_ON_ERROR));
}

// ── Redirect ─────────────────────────────────────────────────────────────────

it('redirects to discord oauth with an encrypted state parameter', function () {
    $response = $this->get(route('discord.redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('discord.com');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($query['state'] ?? null)->toBeString()->not->toBeEmpty();

    $payload = json_decode(Crypt::decryptString($query['state']), associative: true);
    expect($payload['intent'])->toBe('login')
        ->and($payload['link_user_id'])->toBeNull();
});

it('embeds register intent in the oauth state when redirect includes intent register', function () {
    $response = $this->get(route('discord.redirect', ['intent' => 'register']));

    $response->assertRedirect();

    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $payload = json_decode(Crypt::decryptString($query['state']), associative: true);

    expect($payload['intent'])->toBe('register');
});

it('does not put oauth intent into the session', function () {
    $this->get(route('discord.redirect', ['intent' => 'register']));

    expect(session()->get('discord_oauth_intent'))->toBeNull();
});

it('redirects to link form when intent is link but prepare-link session is missing', function () {
    $response = $this->get(route('discord.redirect', ['intent' => 'link']));

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
});

it('embeds the prepare-link user id into the oauth state when intent is link', function () {
    $user = User::factory()->create();

    session(['discord_link_user_id' => $user->id]);

    $response = $this->get(route('discord.redirect', ['intent' => 'link']));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('discord.com');

    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $payload = json_decode(Crypt::decryptString($query['state']), associative: true);

    expect($payload['intent'])->toBe('link')
        ->and($payload['link_user_id'])->toBe($user->id);

    expect(session()->get('discord_link_user_id'))->toBeNull();
});

// ── Prepare link (guest) ──────────────────────────────────────────────────────

it('prepare link rejects invalid credentials', function () {
    User::factory()->create(['email' => 'exists@example.com']);

    $response = $this->from(route('discord.link-form'))->post(route('discord.prepare-link'), [
        'link_email' => 'exists@example.com',
        'link_password' => 'wrong-password',
    ]);

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
    $this->assertGuest();
});

it('prepare link stores user id and redirects toward discord oauth', function () {
    $user = User::factory()->create(['email' => 'trainer@example.com']);

    $response = $this->post(route('discord.prepare-link'), [
        'link_email' => 'trainer@example.com',
        'link_password' => 'password',
    ]);

    $response->assertRedirect(route('discord.redirect', ['intent' => 'link'], absolute: false));
    expect(session()->get('discord_link_user_id'))->toBe($user->id);
    $this->assertGuest();
});

it('prepare link uses inertia location for inertia requests so oauth is not followed via xhr', function () {
    $user = User::factory()->create(['email' => 'trainer@example.com']);

    $expectedLocation = route('discord.redirect', ['intent' => 'link']);

    $response = $this->post(route('discord.prepare-link'), [
        'link_email' => 'trainer@example.com',
        'link_password' => 'password',
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toBe($expectedLocation);
    expect(session()->get('discord_link_user_id'))->toBe($user->id);
    $this->assertGuest();
});

// ── Callback: failure path ────────────────────────────────────────────────────

it('redirects to login with an error when discord user retrieval fails', function () {
    $provider = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andThrow(new \RuntimeException('discord exploded'));

    $socialite = Mockery::mock(\Laravel\Socialite\Contracts\Factory::class);
    $socialite->shouldReceive('driver')->with('discord')->andReturn($provider);

    app()->instance(\Laravel\Socialite\Contracts\Factory::class, $socialite);

    $response = $this->get(route('discord.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

// ── Callback: link account (logged in) ───────────────────────────────────────

it('links discord to an existing logged-in account', function () {
    mockDiscordUser('999111222', 'Trainer#0001');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('discord.callback'));

    $response->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->discord_id)->toBe('999111222')
        ->and($user->discord_username)->toBe('Trainer#0001');
});

it('overwrites an existing discord link when connecting a new account', function () {
    mockDiscordUser('NEW111', 'NewTrainer#0002');

    $user = User::factory()->create([
        'discord_id' => 'OLD999',
        'discord_username' => 'OldTrainer#0001',
    ]);

    $this->actingAs($user)->get(route('discord.callback'));

    $user->refresh();
    expect($user->discord_id)->toBe('NEW111')
        ->and($user->discord_username)->toBe('NewTrainer#0002');
});

// ── Callback: login (guest) ───────────────────────────────────────────────────

it('logs in a guest user whose discord_id matches an existing account', function () {
    mockDiscordUser('777888999', 'KnownTrainer#0003');

    $user = User::factory()->create(['discord_id' => '777888999']);

    $response = $this->get(route('discord.callback'));

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('redirects to link form with an error when no account matches the discord id', function () {
    mockDiscordUser('UNKNOWN000', 'Ghost#0000');

    $response = $this->get(route('discord.callback'));

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
    $this->assertGuest();
});

// ── Callback: guest link after prepare-link ───────────────────────────────────

it('links discord and logs in when guest completed prepare link flow', function () {
    mockDiscordUser('LINK_DISCORD_1', 'Linker#1');

    $user = User::factory()->create(['email' => 'u@example.com', 'discord_id' => null]);

    $response = $this->get(route('discord.callback', ['state' => discordOAuthState('link', $user->id)]));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->discord_id)->toBe('LINK_DISCORD_1')
        ->and($user->discord_username)->toBe('Linker#1');
});

it('rejects guest link when discord is already linked to another user', function () {
    mockDiscordUser('TAKEN_DISC', 'Taken#1');

    $account = User::factory()->create(['discord_id' => null]);
    User::factory()->create(['discord_id' => 'TAKEN_DISC']);

    $response = $this->get(route('discord.callback', ['state' => discordOAuthState('link', $account->id)]));

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
    $this->assertGuest();

    $account->refresh();
    expect($account->discord_id)->toBeNull();
});

// ── Callback: register (guest, intent register) ─────────────────────────────

it('registers and logs in a new user when intent is register and discord email is new', function () {
    mockDiscordUser('NEW_DISCORD_1', 'Rookie#0001', 'rookie@example.com');

    $response = $this->get(route('discord.callback', ['state' => discordOAuthState('register')]));

    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'rookie@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->discord_id)->toBe('NEW_DISCORD_1')
        ->and($user->discord_username)->toBe('Rookie#0001')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('logs in an existing user when intent is register but discord id already exists', function () {
    mockDiscordUser('EXISTING_DISC', 'Existing#1', 'existing@example.com');

    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'discord_id' => 'EXISTING_DISC',
    ]);

    $response = $this->get(route('discord.callback', ['state' => discordOAuthState('register')]));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($existing);
    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
});

it('redirects to register with an error when intent is register but email is already taken', function () {
    mockDiscordUser('NEW_DISCORD_2', 'Dup#1', 'taken@example.com');

    User::factory()->create(['email' => 'taken@example.com', 'discord_id' => null]);

    $response = $this->get(route('discord.callback', ['state' => discordOAuthState('register')]));

    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('redirects to register with an error when intent is register but discord returns no email', function () {
    mockDiscordUser('NO_EMAIL_DISC', 'NoEmail#1', null);

    $response = $this->get(route('discord.callback', ['state' => discordOAuthState('register')]));

    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

// ── Callback: mobile resilience (no originating session) ─────────────────────

it('completes register intent on the callback even when the originating session is gone', function () {
    mockDiscordUser('MOBILE_DISCORD_1', 'Mobile#0001', 'mobile@example.com');

    $state = discordOAuthState('register');
    session()->flush();

    $response = $this->get(route('discord.callback', ['state' => $state]));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
    expect(User::query()->where('email', 'mobile@example.com')->exists())->toBeTrue();
});

it('completes guest discord link on the callback even when the originating session is gone', function () {
    mockDiscordUser('MOBILE_LINK_DISC', 'Mobile#0002');

    $user = User::factory()->create(['discord_id' => null]);

    $state = discordOAuthState('link', $user->id);
    session()->flush();

    $response = $this->get(route('discord.callback', ['state' => $state]));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->discord_id)->toBe('MOBILE_LINK_DISC');
});

it('falls back to login intent when the callback state is missing, malformed, or expired', function () {
    mockDiscordUser('NEW_USER', 'Nobody#0', 'new@example.com');

    $response = $this->get(route('discord.callback', ['state' => 'not-a-real-state']));

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
    $this->assertGuest();
    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('ignores expired oauth state and falls back to login intent', function () {
    mockDiscordUser('NEW_USER_EXP', 'NobodyExp#0', 'expired@example.com');

    $expiredState = Crypt::encryptString(json_encode([
        'intent' => 'register',
        'link_user_id' => null,
        'expires_at' => now()->subMinute()->timestamp,
        'nonce' => 'test',
    ], JSON_THROW_ON_ERROR));

    $response = $this->get(route('discord.callback', ['state' => $expiredState]));

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
    $this->assertGuest();
    expect(User::query()->where('email', 'expired@example.com')->exists())->toBeFalse();
});

// ── Link form ─────────────────────────────────────────────────────────────────

it('shows the link discord form page to guests', function () {
    $response = $this->get(route('discord.link-form'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('auth/LinkDiscord'));
});

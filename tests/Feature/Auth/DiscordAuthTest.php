<?php

use App\Models\User;
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
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('discord')->andReturn($provider);

    app()->instance(SocialiteFactory::class, $socialite);
}

// ── Redirect ─────────────────────────────────────────────────────────────────

it('redirects to discord oauth', function () {
    $response = $this->get(route('discord.redirect'));
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('discord.com');
});

it('stores register intent when redirect includes intent register', function () {
    session(['discord_link_user_id' => 999]);

    $this->get(route('discord.redirect', ['intent' => 'register']));

    expect(session()->get('discord_oauth_intent'))->toBe('register')
        ->and(session()->get('discord_link_user_id'))->toBeNull();
});

it('clears register intent when redirect has no intent', function () {
    session(['discord_oauth_intent' => 'register']);

    $this->get(route('discord.redirect'));

    expect(session()->get('discord_oauth_intent'))->toBeNull();
});

it('redirects to link form when intent is link but prepare-link session is missing', function () {
    $response = $this->get(route('discord.redirect', ['intent' => 'link']));

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
});

it('allows discord redirect with intent link when prepare-link session is present', function () {
    $user = User::factory()->create();

    session(['discord_link_user_id' => $user->id]);

    $response = $this->get(route('discord.redirect', ['intent' => 'link']));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('discord.com');
    expect(session()->get('discord_oauth_intent'))->toBe('link');
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

    session([
        'discord_link_user_id' => $user->id,
        'discord_oauth_intent' => 'link',
    ]);

    $response = $this->get(route('discord.callback'));

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

    session([
        'discord_link_user_id' => $account->id,
        'discord_oauth_intent' => 'link',
    ]);

    $response = $this->get(route('discord.callback'));

    $response->assertRedirect(route('discord.link-form'));
    $response->assertSessionHasErrors('link_email');
    $this->assertGuest();

    $account->refresh();
    expect($account->discord_id)->toBeNull();
});

// ── Callback: register (guest, intent register) ─────────────────────────────

it('registers and logs in a new user when intent is register and discord email is new', function () {
    mockDiscordUser('NEW_DISCORD_1', 'Rookie#0001', 'rookie@example.com');

    $this->get(route('discord.redirect', ['intent' => 'register']));
    $response = $this->get(route('discord.callback'));

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

    $this->get(route('discord.redirect', ['intent' => 'register']));
    $response = $this->get(route('discord.callback'));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($existing);
    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
});

it('redirects to register with an error when intent is register but email is already taken', function () {
    mockDiscordUser('NEW_DISCORD_2', 'Dup#1', 'taken@example.com');

    User::factory()->create(['email' => 'taken@example.com', 'discord_id' => null]);

    $this->get(route('discord.redirect', ['intent' => 'register']));
    $response = $this->get(route('discord.callback'));

    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('redirects to register with an error when intent is register but discord returns no email', function () {
    mockDiscordUser('NO_EMAIL_DISC', 'NoEmail#1', null);

    $this->get(route('discord.redirect', ['intent' => 'register']));
    $response = $this->get(route('discord.callback'));

    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

// ── Link form ─────────────────────────────────────────────────────────────────

it('shows the link discord form page to guests', function () {
    $response = $this->get(route('discord.link-form'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('auth/LinkDiscord'));
});

<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function mockDiscordUser(string $id = '123456789', string $nickname = 'TestUser#1234'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getNickname')->andReturn($nickname);
    $socialiteUser->shouldReceive('getName')->andReturn($nickname);

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

it('redirects to login with an error when no account matches the discord id', function () {
    mockDiscordUser('UNKNOWN000', 'Ghost#0000');

    $response = $this->get(route('discord.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

// ── Disconnect ────────────────────────────────────────────────────────────────

it('disconnects discord from the current user account', function () {
    $user = User::factory()->create([
        'discord_id' => '123456789',
        'discord_username' => 'Trainer#1234',
    ]);

    $response = $this->actingAs($user)->post(route('discord.disconnect'));

    $response->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->discord_id)->toBeNull()
        ->and($user->discord_username)->toBeNull();
});

it('requires authentication to disconnect discord', function () {
    $response = $this->post(route('discord.disconnect'));
    $response->assertRedirect('/login');
});

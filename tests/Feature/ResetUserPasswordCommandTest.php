<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('updates the password for an existing user', function () {
    $user = User::factory()->create();

    $this->artisan('user:reset-password', [
        'id' => $user->id,
        'password' => 'new-secret-password',
    ])->assertSuccessful();

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue();
});

it('fails when no user exists with the given id', function () {
    $this->artisan('user:reset-password', [
        'id' => 99999,
        'password' => 'any-password',
    ])->assertFailed();
});

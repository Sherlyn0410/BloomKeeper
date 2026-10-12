<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('every role can view their profile', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'name' => 'Mina Flores']);

    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Mina Flores');
})->with(['admin', 'staff', 'supplier']);

test('a user can update their name', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->put(route('profile.update'), ['name' => 'New Name', 'email' => $user->email])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->refresh())
        ->name->toBe('New Name')
        ->email_verified_at->not->toBeNull();
});

test('changing email requires verifying the new address', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
        ->assertRedirect(route('verification.notice'));

    expect($user->refresh())
        ->email->toBe('new@example.com')
        ->email_verified_at->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('the email must be unique', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.update'), ['name' => $user->name, 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

test('a user can change their password with the current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.password'), ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

test('changing the password requires the correct current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.password'), ['current_password' => 'wrong-password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
        ->assertSessionHasErrorsIn('password', 'current_password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('unapproved users cannot open the profile page', function () {
    $this->actingAs(User::factory()->create(['approval_status' => 'pending']))
        ->get(route('profile.edit'))
        ->assertForbidden();
});

<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('a guest can view the login and signup pages', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account');
});

test('a user can sign up and must wait for administrator approval', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Mina Flores',
        'email' => 'mina@example.com',
        'role' => 'supplier',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertRedirectToRoute('login');
    $this->assertGuest();
    $this->assertDatabaseHas('users', [
        'name' => 'Mina Flores',
        'email' => 'mina@example.com',
        'role' => 'supplier',
        'approval_status' => 'pending',
    ]);
});

test('a user can resend the email verification notification', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'A verification link has been sent to your email address.');
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('signup does not allow admin role creation', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Mina Flores',
        'email' => 'mina@example.com',
        'role' => 'admin',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertSessionHasErrors('role');
    $this->assertGuest();
});

test('signup validates required fields and password confirmation', function () {
    $response = $this->post(route('register.store'), [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
    $this->assertGuest();
});

test('a user can sign in with valid credentials', function () {
    $user = User::factory()->create([
        'password' => 'secret-password',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $response->assertRedirectToRoute('dashboard');
    $this->assertAuthenticatedAs($user);
});

test('the dashboard cannot be cached', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('Pragma'))->toBe('no-cache');
});

test('invalid login credentials are rejected', function () {
    $user = User::factory()->create([
        'password' => 'secret-password',
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('pending users cannot sign in', function () {
    $user = User::factory()->create([
        'password' => 'secret-password',
        'approval_status' => 'pending',
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('an authenticated user can sign out', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('logout'));

    $response->assertRedirectToRoute('login');
    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirectToRoute('login');
});

test('a guest can request a password reset link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertRedirect(route('password.request'));
    $response->assertSessionHas('status', 'We sent a password reset link to your email address.');
    Notification::assertSentTo($user, ResetPassword::class);
});

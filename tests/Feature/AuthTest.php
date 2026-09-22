<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('a guest can view the login and signup pages', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account');
});

test('a user can sign up and is taken to the dashboard', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Mina Flores',
        'email' => 'mina@example.com',
        'role' => 'supplier',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertRedirectToRoute('dashboard');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'name' => 'Mina Flores',
        'email' => 'mina@example.com',
        'role' => 'supplier',
    ]);
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

test('an authenticated user can sign out', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->post(route('logout'));

    $response->assertRedirectToRoute('login');
    $this->assertGuest();
});

test('a guest can request a password reset link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertRedirect(route('password.request'));
    $response->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);
});

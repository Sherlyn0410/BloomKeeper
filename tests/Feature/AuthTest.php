<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;

test('a guest can view the login and signup pages', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account');
});

test('a user can sign up and must wait for administrator approval', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Mina Flores',
        'email' => 'mina@example.com',
        'role' => 'supplier',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertRedirectToRoute('verification.notice');
    $this->assertAuthenticated();
    Notification::assertSentTo(User::where('email', 'mina@example.com')->first(), VerifyEmail::class);
    $this->assertDatabaseHas('users', [
        'name' => 'Mina Flores',
        'email' => 'mina@example.com',
        'role' => 'supplier',
        'approval_status' => 'pending',
    ]);
});

test('a staff signup stores the staff role', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Staff Member',
        'email' => 'staff@example.com',
        'role' => 'staff',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'staff@example.com',
        'role' => 'staff',
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

test('an unverified user is sent to email verification after login', function () {
    $user = User::factory()->unverified()->create([
        'password' => 'secret-password',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $response->assertRedirectToRoute('verification.notice');
    $this->assertAuthenticatedAs($user);
});

test('a verified but unapproved user cannot enter the dashboard', function () {
    $user = User::factory()->create([
        'approval_status' => 'pending',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirectToRoute('login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
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

test('declined users cannot sign in', function () {
    $user = User::factory()->create([
        'password' => 'secret-password',
        'approval_status' => 'declined',
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

test('a user can reset their password with a valid reset link', function () {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('brand-new-password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBeNull();
});

test('signing in never keeps the user remembered', function () {
    $user = User::factory()->create(['email' => 'remember@example.com', 'remember_token' => null]);

    $response = $this->post(route('login.store'), ['email' => 'remember@example.com', 'password' => 'password', 'remember' => '1']);

    $response->assertRedirect();
    expect(collect($response->headers->getCookies())->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_')))->toBeFalse()
        ->and($user->refresh()->remember_token)->toBeNull();
});

test('staff are not sent to an admin page left over from an earlier sign-in attempt', function () {
    User::factory()->create(['email' => 'staff@example.com', 'role' => 'staff']);

    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => 'staff@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

test('users are returned to the page they were trying to open when they are allowed to see it', function (string $role, string $page) {
    User::factory()->create(['email' => 'user@example.com', 'role' => $role]);

    $this->get(route($page))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => 'user@example.com', 'password' => 'password'])
        ->assertRedirect(route($page));
})->with([
    'admin to an admin page' => ['admin', 'admin.inventory'],
    'staff to a staff page' => ['staff', 'staff.orders.index'],
]);

test('suppliers are not sent to a staff page after signing in', function () {
    User::factory()->create(['email' => 'supplier@example.com', 'role' => 'supplier']);

    $this->get(route('staff.orders.index'))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => 'supplier@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

test('sign up still completes when the verification email cannot be sent', function () {
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Mail server timed out'));

    $this->post(route('register.store'), [
        'name' => 'Nadia Florist',
        'email' => 'nadia@example.com',
        'role' => 'staff',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', "Your account was created, but we couldn't send the verification email just now. Please use Resend verification email.");

    expect(User::where('email', 'nadia@example.com')->exists())->toBeTrue();
});

test('resending the verification email reports a failure instead of crashing', function () {
    $user = User::factory()->unverified()->create();
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Mail server timed out'));

    $this->actingAs($user)->post(route('verification.send'))
        ->assertRedirect()
        ->assertSessionHas('status', "We couldn't send the verification email just now. Please try again in a moment.");
});

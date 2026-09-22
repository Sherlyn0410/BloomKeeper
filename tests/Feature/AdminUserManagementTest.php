<?php

use App\Models\User;

test('an administrator can view the overview and user list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['approval_status' => 'pending']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Pending approval');

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Manage users');
});

test('non administrators cannot access user management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('an administrator can approve a pending user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['approval_status' => 'pending']);

    $this->actingAs($admin)
        ->patch(route('admin.users.approve', $user))
        ->assertRedirect();

    expect($user->refresh()->approval_status)->toBe('approved');
});

test('an administrator can suspend and restore a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['approval_status' => 'approved']);

    $this->actingAs($admin)->patch(route('admin.users.suspend', $user));
    expect($user->refresh()->approval_status)->toBe('suspended');

    $this->actingAs($admin)->patch(route('admin.users.restore', $user));
    expect($user->refresh()->approval_status)->toBe('approved');
});

test('an administrator cannot suspend their own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patch(route('admin.users.suspend', $admin))
        ->assertSessionHasErrors('user');

    expect($admin->refresh()->approval_status)->toBe('approved');
});

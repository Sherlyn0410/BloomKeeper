<?php

use App\Models\User;

test('an administrator can view the overview and user list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $recentAccount = User::factory()->create(['name' => 'New Staff Member', 'role' => 'staff', 'approval_status' => 'pending']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Pending approval')
        ->assertSee($recentAccount->name)
        ->assertSee('Approve')
        ->assertSee(route('admin.users.approve', $recentAccount))
        ->assertSee(route('admin.users', ['role' => 'staff']))
        ->assertDontSee($admin->email);

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

test('an administrator can manage a staff account lifecycle', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $staff = User::factory()->create(['name' => 'Staff Operator', 'email' => 'staff.operator@example.com', 'role' => 'staff']);
    expect($staff->role)->toBe('staff');

    $this->actingAs($admin)->put(route('admin.users.update', $staff), [
        'name' => 'Updated Staff Operator',
        'email' => 'updated.staff@example.com',
        'role' => 'staff',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect();

    $staff->refresh();
    expect($staff->name)->toBe('Updated Staff Operator');

    $this->actingAs($admin)->patch(route('admin.users.toggle-status', $staff))->assertRedirect();
    expect($staff->refresh()->approval_status)->toBe('suspended');

    $this->actingAs($admin)->patch(route('admin.users.toggle-status', $staff))->assertRedirect();
    expect($staff->refresh()->approval_status)->toBe('approved');

    $this->actingAs($admin)->get(route('admin.users', ['role' => 'staff']))->assertOk()->assertSee('Updated Staff Operator');

    $this->actingAs($admin)->get(route('admin.users', ['role' => 'staff', 'search' => 'Updated Staff']))
        ->assertOk()
        ->assertSee('Updated Staff Operator');

    $this->actingAs($admin)->get(route('admin.users', ['role' => 'staff', 'search' => 'not-found']))
        ->assertOk()
        ->assertSee('No staff accounts found.');

    $supplier = User::factory()->create(['name' => 'Supplier Partner', 'role' => 'supplier']);

    $this->actingAs($admin)->get(route('admin.users', ['role' => 'supplier']))
        ->assertOk()
        ->assertSee('Suppliers')
        ->assertSee($supplier->name);
});

test('an administrator can decline and restore a pending user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['approval_status' => 'pending']);

    $this->actingAs($admin)
        ->patch(route('admin.users.decline', $user))
        ->assertRedirect();

    expect($user->refresh()->approval_status)->toBe('declined');

    $this->actingAs($admin)->patch(route('admin.users.restore', $user));

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

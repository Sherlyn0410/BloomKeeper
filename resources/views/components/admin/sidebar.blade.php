@props(['mobile' => false])

@php
    $usersRole = request()->routeIs('admin.users') ? request('role', 'staff') : null;

    $navigation = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => request()->routeIs('admin.dashboard')],
        ['label' => 'Inventory', 'children' => [
            ['label' => 'Stock list', 'route' => 'admin.inventory', 'active' => request()->routeIs('admin.inventory', 'admin.operations')],
            ['label' => 'Record wastage', 'route' => 'staff.wastage.create', 'active' => request()->routeIs('staff.wastage.create')],
            ['label' => 'Wastage history', 'route' => 'admin.wastage', 'active' => request()->routeIs('admin.wastage', 'staff.wastage.edit')],
        ]],
        ['label' => 'Orders', 'children' => [
            ['label' => 'All orders', 'route' => 'admin.orders', 'active' => request()->routeIs('admin.orders', 'staff.orders.index', 'staff.orders.edit')],
            ['label' => 'Create order', 'route' => 'staff.orders.create', 'active' => request()->routeIs('staff.orders.create')],
            ['label' => 'Customers', 'route' => 'staff.customers.index', 'active' => request()->routeIs('staff.customers.*')],
        ]],
        ['label' => 'Purchase Orders', 'route' => 'admin.purchase-orders', 'active' => request()->routeIs('admin.purchase-orders')],
        ['label' => 'Reports', 'children' => [
            ['label' => 'Sales report', 'route' => 'admin.reports', 'active' => request()->routeIs('admin.reports', 'admin.reports.export')],
            ['label' => 'Wastage report', 'route' => 'admin.reports.wastage', 'active' => request()->routeIs('admin.reports.wastage*')],
        ]],
        ['label' => 'Manage Users', 'children' => [
            ['label' => 'Staff', 'route' => 'admin.users', 'params' => ['role' => 'staff'], 'active' => $usersRole === 'staff'],
            ['label' => 'Suppliers', 'route' => 'admin.users', 'params' => ['role' => 'supplier'], 'active' => $usersRole === 'supplier'],
        ]],
    ];

@endphp

@if ($mobile)
    <x-mobile-nav :navigation="$navigation" />
@else
    <x-sidebar :navigation="$navigation" subtitle="Admin console" role-label="Administrator" home-route="admin.dashboard" />
@endif

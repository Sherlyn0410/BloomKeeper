@props(['mobile' => false])

@php
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
    ];

    if (auth()->user()->can('supply-orders')) {
        $navigation[] = ['label' => 'Purchase orders', 'route' => 'supplier.purchase-orders.index', 'active' => request()->routeIs('supplier.purchase-orders.*')];
    }

    if (auth()->user()->can('manage-customers')) {
        $navigation = [
            ...$navigation,
            ['label' => 'Orders', 'children' => [
                ['label' => 'All orders', 'route' => 'staff.orders.index', 'active' => request()->routeIs('staff.orders.index', 'staff.orders.edit')],
                ['label' => 'Create order', 'route' => 'staff.orders.create', 'active' => request()->routeIs('staff.orders.create')],
                ['label' => 'Customers', 'route' => 'staff.customers.index', 'active' => request()->routeIs('staff.customers.*')],
            ]],
            ['label' => 'Record wastage', 'route' => 'staff.wastage.create', 'active' => request()->routeIs('staff.wastage.*')],
        ];
    }
@endphp

<x-sidebar :navigation="$navigation" subtitle="{{ ucfirst(auth()->user()->role) }} workspace" role-label="{{ ucfirst(auth()->user()->role) }}" home-route="dashboard" :mobile="$mobile" />

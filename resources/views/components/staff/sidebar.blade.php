@props(['mobile' => false])

@php
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
    ];

    if (auth()->user()->can('supply-orders')) {
        $navigation[] = ['label' => 'Purchase orders', 'route' => 'supplier.purchase-orders.index', 'active' => request()->routeIs('supplier.purchase-orders.*')];
        $navigation[] = ['label' => 'Returns', 'route' => 'supplier.stock-returns.index', 'active' => request()->routeIs('supplier.stock-returns.*')];
    }

    if (auth()->user()->can('manage-customers')) {
        $navigation = [
            ...$navigation,
            ['label' => 'Inventory', 'route' => 'staff.inventory', 'active' => request()->routeIs('staff.inventory')],
            ['label' => 'Orders', 'children' => [
                ['label' => 'All orders', 'route' => 'staff.orders.index', 'active' => request()->routeIs('staff.orders.index', 'staff.orders.edit')],
                ['label' => 'Create order', 'route' => 'staff.orders.create', 'active' => request()->routeIs('staff.orders.create')],
                ['label' => 'Customers', 'route' => 'staff.customers.index', 'active' => request()->routeIs('staff.customers.*')],
            ]],
            ['label' => 'Purchase orders', 'route' => 'staff.purchase-orders', 'active' => request()->routeIs('staff.purchase-orders')],
            ['label' => 'Record wastage', 'route' => 'staff.wastage.create', 'active' => request()->routeIs('staff.wastage.*')],
            ['label' => 'Stock returns', 'route' => 'staff.stock-returns', 'active' => request()->routeIs('staff.stock-returns')],
        ];
    }

    if (auth()->user()->can('use-workspace')) {
        $navigation[] = ['label' => 'Messages', 'route' => 'messages.index', 'active' => request()->routeIs('messages.*'), 'badge' => auth()->user()->unreadMessageCount()];
    }
@endphp

<x-sidebar :navigation="$navigation" subtitle="{{ ucfirst(auth()->user()->role) }} workspace" role-label="{{ ucfirst(auth()->user()->role) }}" home-route="dashboard" :mobile="$mobile" />

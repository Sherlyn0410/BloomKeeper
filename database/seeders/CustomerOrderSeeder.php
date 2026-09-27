<?php

namespace Database\Seeders;

use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CustomerOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $creator = User::query()->where('role', 'admin')->first() ?? User::query()->first();

        if (! $creator) {
            throw new \RuntimeException('Seed at least one user before seeding customer orders.');
        }

        $orders = [
            [
                'customer_name' => 'Aisha Rahman',
                'customer_email' => 'aisha.rahman@example.com',
                'customer_phone' => '012-345 6789',
                'collection_date' => Carbon::today()->addDays(1),
                'status' => 'pending',
                'items' => [
                    ['flower_type' => 'Roses', 'quantity' => 12, 'unit_price' => 2.50],
                    ['flower_type' => 'Baby\'s Breath', 'quantity' => 3, 'unit_price' => 1.40],
                ],
            ],
            [
                'customer_name' => 'Daniel Tan',
                'customer_email' => 'daniel.tan@example.com',
                'customer_phone' => '016-222 3344',
                'collection_date' => Carbon::today()->addDays(2),
                'status' => 'pending',
                'items' => [
                    ['flower_type' => 'Sunflowers', 'quantity' => 6, 'unit_price' => 2.10],
                ],
            ],
            [
                'customer_name' => 'Priya Nair',
                'customer_email' => 'priya.nair@example.com',
                'customer_phone' => '017-889 1020',
                'collection_date' => Carbon::today(),
                'status' => 'ready',
                'items' => [
                    ['flower_type' => 'Lilies', 'quantity' => 5, 'unit_price' => 3.25],
                    ['flower_type' => 'Orchids', 'quantity' => 2, 'unit_price' => 4.75],
                ],
            ],
            [
                'customer_name' => 'Wong Mei Ling',
                'customer_email' => null,
                'customer_phone' => '019-456 7788',
                'collection_date' => Carbon::today()->addDays(4),
                'status' => 'pending',
                'items' => [
                    ['flower_type' => 'Tulips', 'quantity' => 10, 'unit_price' => 1.80],
                    ['flower_type' => 'Roses', 'quantity' => 6, 'unit_price' => 2.50],
                ],
            ],
            [
                'customer_name' => 'Hafiz Ismail',
                'customer_email' => 'hafiz.ismail@example.com',
                'customer_phone' => null,
                'collection_date' => Carbon::today()->subDays(1),
                'status' => 'completed',
                'items' => [
                    ['flower_type' => 'Roses', 'quantity' => 24, 'unit_price' => 2.50],
                ],
            ],
            [
                'customer_name' => 'Chloe Lim',
                'customer_email' => 'chloe.lim@example.com',
                'customer_phone' => '011-2233 4455',
                'collection_date' => Carbon::today()->subDays(3),
                'status' => 'completed',
                'items' => [
                    ['flower_type' => 'Orchids', 'quantity' => 3, 'unit_price' => 4.75],
                    ['flower_type' => 'Baby\'s Breath', 'quantity' => 2, 'unit_price' => 1.40],
                ],
            ],
            [
                'customer_name' => 'Ravi Kumar',
                'customer_email' => 'ravi.kumar@example.com',
                'customer_phone' => '013-667 8899',
                'collection_date' => Carbon::today()->subDays(2),
                'status' => 'cancelled',
                'items' => [
                    ['flower_type' => 'Sunflowers', 'quantity' => 12, 'unit_price' => 2.10],
                ],
            ],
            [
                'customer_name' => 'Nurul Huda',
                'customer_email' => 'nurul.huda@example.com',
                'customer_phone' => '018-332 1100',
                'collection_date' => Carbon::today()->addDays(7),
                'status' => 'pending',
                'items' => [
                    ['flower_type' => 'Lilies', 'quantity' => 8, 'unit_price' => 3.25],
                    ['flower_type' => 'Tulips', 'quantity' => 8, 'unit_price' => 1.80],
                    ['flower_type' => 'Baby\'s Breath', 'quantity' => 4, 'unit_price' => 1.40],
                ],
            ],
        ];

        foreach ($orders as $order) {
            $items = $order['items'];
            unset($order['items']);

            $customerOrder = CustomerOrder::query()->firstOrCreate([
                'customer_name' => $order['customer_name'],
                'collection_date' => $order['collection_date'],
            ], [...$order, 'created_by' => $creator->id]);

            if ($customerOrder->wasRecentlyCreated) {
                $customerOrder->items()->createMany($items);
            }
        }
    }
}

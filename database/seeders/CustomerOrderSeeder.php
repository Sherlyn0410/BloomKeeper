<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
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
        $creators = User::query()->whereIn('role', ['admin', 'staff'])->where('approval_status', 'approved')->orderBy('id')->get();

        if ($creators->isEmpty()) {
            throw new \RuntimeException('Seed at least one admin or staff user before seeding customer orders.');
        }

        $customers = collect([
            ['name' => 'Aisha Rahman', 'email' => 'aisha.rahman@example.com', 'phone' => '0123456789'],
            ['name' => 'Daniel Tan', 'email' => 'daniel.tan@example.com', 'phone' => '0162223344'],
            ['name' => 'Priya Nair', 'email' => 'priya.nair@example.com', 'phone' => '01112345678'],
            ['name' => 'Wong Mei Ling', 'email' => null, 'phone' => '0194567788'],
            ['name' => 'Hafiz Ismail', 'email' => 'hafiz.ismail@example.com', 'phone' => '01198765432'],
        ])->mapWithKeys(fn (array $customer, int $index): array => [
            $customer['name'] => Customer::query()->firstOrCreate(
                ['name' => $customer['name'], 'phone' => $customer['phone']],
                [...$customer, 'created_by' => $creators[$index % $creators->count()]->id],
            ),
        ]);

        $unitPrices = InventoryItem::query()->orderBy('date_received')->pluck('unit_price', 'flower_type');

        $orders = [
            ['customer' => 'Aisha Rahman', 'collection_date' => Carbon::today()->addDay(), 'collection_time' => '10:30', 'status' => 'pending', 'payment_status' => 'paid',
                'notes' => 'Card: "Happy Anniversary, love you always." Wrap in kraft paper.',
                'items' => ['Roses' => 12, 'Baby\'s Breath' => 3]],
            ['customer' => 'Daniel Tan', 'collection_date' => Carbon::today(), 'collection_time' => '17:00', 'status' => 'ready', 'payment_status' => 'paid',
                'notes' => 'Office opening stand. Ribbon: "Congratulations on your grand opening".',
                'items' => ['Sunflowers' => 10, 'Tulips' => 6]],
            ['customer' => 'Priya Nair', 'collection_date' => Carbon::today()->addDays(3), 'collection_time' => null, 'status' => 'pending', 'payment_status' => 'unpaid',
                'notes' => 'Pastel tones only, no lilies (allergy).',
                'items' => ['Orchids' => 3, 'Tulips' => 10]],
            ['customer' => 'Wong Mei Ling', 'collection_date' => Carbon::today()->addDays(2), 'collection_time' => '15:00', 'status' => 'pending', 'payment_status' => 'unpaid',
                'notes' => 'Condolence arrangement in white. Card: "With deepest sympathy".',
                'items' => ['Lilies' => 6, 'Baby\'s Breath' => 4]],
            ['customer' => 'Hafiz Ismail', 'collection_date' => Carbon::today()->addDays(5), 'collection_time' => '11:00', 'status' => 'pending', 'payment_status' => 'paid',
                'notes' => 'Proposal bouquet. Keep it a surprise, do not call before collection.',
                'items' => ['Roses' => 24]],
            ['customer' => 'Aisha Rahman', 'collection_date' => Carbon::today()->subDays(4), 'collection_time' => '12:00', 'status' => 'completed', 'payment_status' => 'paid',
                'notes' => 'Get-well bouquet for a hospital visit.',
                'items' => ['Orchids' => 2, 'Tulips' => 6]],
            ['customer' => 'Daniel Tan', 'collection_date' => Carbon::today()->subDays(2), 'collection_time' => '09:00', 'status' => 'cancelled', 'payment_status' => 'unpaid',
                'notes' => 'Cancelled by customer, event postponed.',
                'items' => ['Lilies' => 8]],
            ['customer' => 'Priya Nair', 'collection_date' => Carbon::today()->subDay(), 'collection_time' => '16:30', 'status' => 'completed', 'payment_status' => 'paid',
                'notes' => 'Birthday bouquet. Card: "Happy 30th, Kavya!"',
                'items' => ['Sunflowers' => 5, 'Roses' => 6]],
        ];

        foreach ($orders as $index => $order) {
            $customer = $customers->get($order['customer']);

            $customerOrder = CustomerOrder::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'notes' => $order['notes'],
            ], [
                'order_number' => CustomerOrder::nextOrderNumber(),
                'created_by' => $creators[$index % $creators->count()]->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'collection_date' => $order['collection_date'],
                'collection_time' => $order['collection_time'],
                'status' => $order['status'],
                'payment_status' => $order['payment_status'],
            ]);

            if ($customerOrder->wasRecentlyCreated) {
                foreach ($order['items'] as $flowerType => $quantity) {
                    $customerOrder->items()->create([
                        'flower_type' => $flowerType,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrices[$flowerType] ?? 0,
                    ]);
                }
            }
        }
    }
}

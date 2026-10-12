<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $highlandFlorals = $this->supplier('Highland Florals Sdn Bhd', 'orders@highlandflorals.example.com');
        $petalWholesale = $this->supplier('Petal Wholesale Trading', 'sales@petalwholesale.example.com');

        $items = [
            ['flower_type' => 'Roses', 'quantity' => 42, 'unit_price' => 2.50, 'cost_price' => 1.20, 'date_received' => Carbon::today()->subDays(2), 'shelf_life_days' => 7, 'low_stock_threshold' => 10, 'supplier_id' => $highlandFlorals->id, 'notes' => 'Red and pink, Cameron Highlands grown.'],
            ['flower_type' => 'Tulips', 'quantity' => 4, 'unit_price' => 1.80, 'cost_price' => 0.95, 'date_received' => Carbon::today()->subDays(1), 'shelf_life_days' => 7, 'low_stock_threshold' => 8, 'supplier_id' => $petalWholesale->id, 'notes' => 'Imported from the Netherlands.'],
            ['flower_type' => 'Lilies', 'quantity' => 18, 'unit_price' => 3.25, 'cost_price' => 1.70, 'date_received' => Carbon::today()->subDays(6), 'shelf_life_days' => 7, 'low_stock_threshold' => 5, 'supplier_id' => $highlandFlorals->id, 'notes' => 'Remove pollen before arranging.'],
            ['flower_type' => 'Sunflowers', 'quantity' => 26, 'unit_price' => 2.10, 'cost_price' => 1.00, 'date_received' => Carbon::today()->subDays(3), 'shelf_life_days' => 10, 'low_stock_threshold' => 6, 'supplier_id' => $petalWholesale->id, 'notes' => null],
            ['flower_type' => 'Baby\'s Breath', 'quantity' => 12, 'unit_price' => 1.40, 'cost_price' => 0.60, 'date_received' => Carbon::today()->subDays(1), 'shelf_life_days' => 5, 'low_stock_threshold' => 4, 'supplier_id' => $highlandFlorals->id, 'notes' => 'Popular bouquet filler.'],
            ['flower_type' => 'Orchids', 'quantity' => 9, 'unit_price' => 4.75, 'cost_price' => 2.80, 'date_received' => Carbon::today()->subDays(4), 'shelf_life_days' => 14, 'low_stock_threshold' => 3, 'supplier_id' => $petalWholesale->id, 'notes' => 'Phalaenopsis stems, keep away from cold air.'],
            ['flower_type' => 'Carnations', 'quantity' => 35, 'unit_price' => 1.60, 'cost_price' => 0.70, 'date_received' => Carbon::today()->subDays(2), 'shelf_life_days' => 14, 'low_stock_threshold' => 10, 'supplier_id' => $highlandFlorals->id, 'notes' => null],
            ['flower_type' => 'Chrysanthemums', 'quantity' => 22, 'unit_price' => 1.50, 'cost_price' => 0.65, 'date_received' => Carbon::today()->subDays(5), 'shelf_life_days' => 12, 'low_stock_threshold' => 8, 'supplier_id' => $petalWholesale->id, 'notes' => 'White and yellow mix.'],
            ['flower_type' => 'Hydrangeas', 'quantity' => 6, 'unit_price' => 5.50, 'cost_price' => 3.20, 'date_received' => Carbon::today()->subDays(3), 'shelf_life_days' => 6, 'low_stock_threshold' => 6, 'supplier_id' => $highlandFlorals->id, 'notes' => 'Needs plenty of water.'],
            ['flower_type' => 'Eucalyptus', 'quantity' => 30, 'unit_price' => 1.20, 'cost_price' => 0.45, 'date_received' => Carbon::today()->subDays(1), 'shelf_life_days' => 14, 'low_stock_threshold' => 8, 'supplier_id' => $petalWholesale->id, 'notes' => 'Silver dollar greenery.'],
        ];

        foreach ($items as $item) {
            InventoryItem::query()->firstOrCreate(['flower_type' => $item['flower_type']], $item);
        }
    }

    private function supplier(string $name, string $email): User
    {
        $supplier = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'role' => 'supplier',
            'approval_status' => 'approved',
            'password' => Hash::make(Str::password(32)),
        ]);

        if ($supplier->email_verified_at === null) {
            $supplier->forceFill(['email_verified_at' => now()])->save();
        }

        return $supplier;
    }
}

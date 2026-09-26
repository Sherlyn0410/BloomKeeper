<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['flower_type' => 'Roses', 'quantity' => 42, 'unit_price' => 2.50, 'date_received' => Carbon::today()->subDays(2), 'shelf_life_days' => 7, 'low_stock_threshold' => 10],
            ['flower_type' => 'Tulips', 'quantity' => 4, 'unit_price' => 1.80, 'date_received' => Carbon::today()->subDays(1), 'shelf_life_days' => 7, 'low_stock_threshold' => 8],
            ['flower_type' => 'Lilies', 'quantity' => 18, 'unit_price' => 3.25, 'date_received' => Carbon::today()->subDays(5), 'shelf_life_days' => 7, 'low_stock_threshold' => 5],
            ['flower_type' => 'Sunflowers', 'quantity' => 26, 'unit_price' => 2.10, 'date_received' => Carbon::today()->subDays(3), 'shelf_life_days' => 10, 'low_stock_threshold' => 6],
            ['flower_type' => 'Baby\'s Breath', 'quantity' => 12, 'unit_price' => 1.40, 'date_received' => Carbon::today()->subDays(1), 'shelf_life_days' => 5, 'low_stock_threshold' => 4],
            ['flower_type' => 'Orchids', 'quantity' => 9, 'unit_price' => 4.75, 'date_received' => Carbon::today()->subDays(4), 'shelf_life_days' => 14, 'low_stock_threshold' => 3],
        ];

        foreach ($items as $item) {
            InventoryItem::query()->firstOrCreate([
                'flower_type' => $item['flower_type'],
                'date_received' => $item['date_received'],
            ], $item);
        }
    }
}

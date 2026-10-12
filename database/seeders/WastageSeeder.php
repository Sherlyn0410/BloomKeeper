<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Database\Seeder;

class WastageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->where('role', 'admin')->first() ?? User::query()->first();

        if (! $user) {
            throw new \RuntimeException('Seed at least one user before seeding wastage.');
        }

        $records = [
            ['flower_type' => 'Lilies', 'quantity' => 4, 'reason' => 'expired', 'notes' => 'Petals browning, past their best.', 'days_ago' => 1],
            ['flower_type' => 'Roses', 'quantity' => 3, 'reason' => 'damaged', 'notes' => 'Stems snapped during delivery unpacking.', 'days_ago' => 2],
            ['flower_type' => 'Hydrangeas', 'quantity' => 1, 'reason' => 'other', 'notes' => 'Wilted after the cooler was left open overnight.', 'days_ago' => 0],
        ];

        foreach ($records as $record) {
            $item = InventoryItem::query()->where('flower_type', $record['flower_type'])->first();

            if (! $item || StockAdjustment::query()->where('type', 'wastage')->where('notes', $record['notes'])->exists()) {
                continue;
            }

            $adjustment = $item->recordWastage($user, $record['quantity'], $record['reason'], $record['notes']);
            $adjustment->forceFill(['created_at' => now()->subDays($record['days_ago']), 'updated_at' => now()->subDays($record['days_ago'])])->save();
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\StockReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds the return record only; use StockReturn::request() when the stock movement matters.
 *
 * @extends Factory<StockReturn>
 */
class StockReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $supplier = User::factory()->create(['role' => 'supplier']);
        $batch = InventoryItem::create([
            'flower_type' => fake()->randomElement(['Roses', 'Lilies', 'Tulips']), 'quantity' => 20, 'unit_price' => 3.00, 'cost_price' => 1.50,
            'date_received' => today(), 'shelf_life_days' => 7, 'low_stock_threshold' => 5, 'supplier_id' => $supplier->id,
        ]);

        return [
            'inventory_item_id' => $batch->id,
            'supplier_id' => $supplier->id,
            'created_by' => User::factory(),
            'flower_type' => $batch->flower_type,
            'quantity' => fake()->numberBetween(1, 10),
            'unit_cost' => $batch->cost_price,
            'reason' => fake()->randomElement(array_keys(StockReturn::REASONS)),
            'notes' => null,
            'status' => 'requested',
        ];
    }

    public function accepted(): static
    {
        return $this->state(['status' => 'accepted', 'responded_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected', 'supplier_note' => 'Arrived in good condition.', 'responded_at' => now()]);
    }
}

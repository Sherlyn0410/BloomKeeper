<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\InventoryItem;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffOrderController extends Controller
{
    public function create(): View
    {
        return view('staff.orders-form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateOrder($request);

        $order = DB::transaction(function () use ($data, $request): CustomerOrder {
            $customer = $this->resolveCustomer($data, $request);

            $order = CustomerOrder::create([
                ...$this->orderAttributes($data, $customer),
                'created_by' => $request->user()->id,
                'status' => 'pending',
            ]);

            $this->saveItems($order, $data['items']);
            $order->syncStock($request->user());

            return $order;
        });

        return redirect()->route('staff.orders.create')->with('status', "Order {$order->order_number} for {$order->customer_name} has been created.");
    }

    public function edit(CustomerOrder $customerOrder): View
    {
        return view('staff.orders-form', [...$this->formData(), 'order' => $customerOrder->load('items')]);
    }

    public function update(Request $request, CustomerOrder $customerOrder): RedirectResponse
    {
        $data = $this->validateOrder($request, $customerOrder);

        DB::transaction(function () use ($data, $request, $customerOrder): void {
            $customer = $this->resolveCustomer($data, $request);
            $previousStatus = $customerOrder->status;

            $customerOrder->update([
                ...$this->orderAttributes($data, $customer),
                'status' => $data['status'],
            ]);

            $this->saveItems($customerOrder, $data['items']);

            $customerOrder->status === 'cancelled'
                ? $customerOrder->applyStatusChangeToStock($request->user(), $previousStatus)
                : $customerOrder->syncStock($request->user());
        });

        return $this->redirectToOrderList($request)->with('status', "Order {$customerOrder->order_number} has been updated.");
    }

    public function destroy(Request $request, CustomerOrder $customerOrder): RedirectResponse
    {
        DB::transaction(function () use ($customerOrder, $request): void {
            $customerOrder->releaseStock($request->user());
            $customerOrder->delete();
        });

        return $this->redirectToOrderList($request)->with('status', "Order {$customerOrder->order_number} has been deleted.");
    }

    /**
     * @return array{customers: EloquentCollection<int, Customer>, flowerStock: Collection<string, int>, flowerPrices: Collection<string, string>}
     */
    private function formData(): array
    {
        return [
            'customers' => Customer::query()->orderBy('name')->get(),
            'flowerStock' => InventoryItem::query()->selectRaw('flower_type, SUM(quantity) as available')
                ->groupBy('flower_type')->orderBy('flower_type')->pluck('available', 'flower_type'),
            'flowerPrices' => InventoryItem::query()->orderBy('date_received')->pluck('unit_price', 'flower_type'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrder(Request $request, ?CustomerOrder $order = null): array
    {
        $withStatus = $order !== null;

        $isNewCustomer = $request->input('customer_id') === 'new';

        return $request->validate([
            'customer_id' => ['required', $isNewCustomer ? Rule::in(['new']) : Rule::exists('customers', 'id')],
            'new_customer.name' => [Rule::requiredIf($isNewCustomer), 'nullable', 'string', 'max:255'],
            'new_customer.phone' => [Rule::requiredIf($isNewCustomer), 'nullable', 'string', 'max:30', 'regex:/^(?:[\s-]*\d){9,11}[\s-]*$/'],
            'new_customer.email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')],
            'collection_date' => ['required', 'date', function (string $attribute, mixed $value, Closure $fail) use ($order): void {
                $date = Carbon::parse($value)->startOfDay();
                $isUnchangedDate = $order !== null && $date->equalTo($order->collection_date);

                if ($date->lte(today()) && ! $isUnchangedDate) {
                    $fail('The collection date must be after today.');
                }
            }],
            'collection_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_status' => ['required', Rule::in(CustomerOrder::PAYMENT_STATUSES)],
            'status' => [Rule::requiredIf($withStatus), Rule::in(CustomerOrder::STATUSES)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.flower_type' => ['required', 'string', Rule::in(InventoryItem::query()->distinct()->pluck('flower_type'))],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'new_customer.phone.regex' => 'The customer phone number must have 9 to 11 digits.',
        ], [
            'new_customer.name' => 'customer name',
            'new_customer.phone' => 'customer phone',
            'new_customer.email' => 'customer email',
            'items.*.flower_type' => 'flower type',
            'items.*.quantity' => 'quantity',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data, Request $request): Customer
    {
        return $data['customer_id'] === 'new'
            ? Customer::create([...$data['new_customer'], 'created_by' => $request->user()->id])
            : Customer::query()->findOrFail($data['customer_id']);
    }

    /**
     * Customer details are copied onto the order so it keeps a record even if the customer changes later.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function orderAttributes(array $data, Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'collection_date' => $data['collection_date'],
            'collection_time' => $data['collection_time'] ?? null,
            'notes' => $data['notes'] ?? null,
            'payment_status' => $data['payment_status'],
        ];
    }

    /**
     * Replace the order's items, keeping the original price and cost for flowers already on the order.
     *
     * @param  array<int, array{flower_type: string, quantity: int|string}>  $items
     */
    private function saveItems(CustomerOrder $order, array $items): void
    {
        $existingPrices = $order->items()->pluck('unit_price', 'flower_type');
        $existingCosts = $order->items()->pluck('unit_cost', 'flower_type');
        $currentPrices = InventoryItem::query()->orderBy('date_received')->pluck('unit_price', 'flower_type');
        $currentCosts = InventoryItem::query()->orderBy('date_received')->pluck('cost_price', 'flower_type');

        $order->items()->delete();

        foreach ($items as $item) {
            $order->items()->create([
                'flower_type' => $item['flower_type'],
                'quantity' => $item['quantity'],
                'unit_price' => $existingPrices[$item['flower_type']] ?? $currentPrices[$item['flower_type']] ?? 0,
                'unit_cost' => $existingCosts[$item['flower_type']] ?? $currentCosts[$item['flower_type']] ?? 0,
            ]);
        }
    }

    private function redirectToOrderList(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role === 'admin' ? 'admin.orders' : 'staff.orders.index');
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    public function createSale(int $userId, array $items): Sale
    {
        return DB::transaction(function () use ($items, $userId): Sale {
            $products = Product::query()
                ->whereKey(collect($items)->pluck('product_id')->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sale = Sale::create(['user_id' => $userId, 'total' => '0.00']);
            $totalCents = 0;

            foreach ($items as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected products is no longer available.',
                    ]);
                }

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$product->name}.",
                    ]);
                }

                $unitPriceCents = $this->toCents($product->price);
                $subtotalCents = $unitPriceCents * $item['quantity'];

                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $this->fromCents($unitPriceCents),
                    'subtotal' => $this->fromCents($subtotalCents),
                ]);

                $product->decrement('stock', $item['quantity']);
                $totalCents += $subtotalCents;
            }

            $sale->update(['total' => $this->fromCents($totalCents)]);

            return $sale->load(['items.product']);
        });
    }

    /** @return array{from: string, to: string, orders: int, revenue: string} */
    public function report(string $from, string $to): array
    {
        $sales = Sale::query()->whereBetween('created_at', [
            CarbonImmutable::parse($from)->startOfDay(),
            CarbonImmutable::parse($to)->endOfDay(),
        ]);

        return [
            'from' => $from,
            'to' => $to,
            'orders' => (clone $sales)->count(),
            'revenue' => (string) (clone $sales)->sum('total'),
        ];
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function fromCents(int $amount): string
    {
        return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }
}

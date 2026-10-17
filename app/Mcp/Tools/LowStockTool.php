<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Find products with low available stock.')]
class LowStockTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $threshold = (int) $request->get('threshold', 10);

        if ($threshold < 0 || $threshold > 100000) {
            return Response::error('The stock threshold must be between 0 and 100000.');
        }

        $products = Product::query()
            ->where('stock', '<', $threshold)
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'sku', 'stock']);

        return Response::structured([
            'threshold' => $threshold,
            'products' => $products->toArray(),
            'count' => $products->count(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'threshold' => $schema->integer()->description('Stock threshold; default is 10'),
        ];
    }
}

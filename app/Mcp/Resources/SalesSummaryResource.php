<?php

namespace App\Mcp\Resources;

use App\Models\Product;
use App\Models\Sale;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('sales://summary')]
#[Description('Current sales and inventory summary.')]
class SalesSummaryResource extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        return Response::text((string) json_encode([
            'total_products' => Product::count(),
            'low_stock_products' => Product::query()->where('stock', '<', 10)->count(),
            'total_sales' => Sale::count(),
            'total_revenue' => (string) Sale::sum('total'),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}

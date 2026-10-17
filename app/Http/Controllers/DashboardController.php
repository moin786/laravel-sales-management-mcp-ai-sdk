<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'summary' => [
                'products' => Product::count(),
                'lowStock' => Product::query()->where('stock', '<', 10)->count(),
                'orders' => Sale::count(),
                'revenue' => (string) Sale::sum('total'),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sales/index', [
            'products' => Product::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'price', 'stock']),
            'sales' => Sale::query()->with('items.product:id,name')->latest()->limit(20)->get(),
        ]);
    }

    public function store(StoreSaleRequest $request, SalesService $salesService): RedirectResponse
    {
        $salesService->createSale($request->user()->id, $request->validated('items'));

        return to_route('sales.index')->with('success', 'Sale completed.');
    }
}

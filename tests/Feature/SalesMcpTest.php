<?php

use App\Mcp\Servers\SalesServer;
use App\Mcp\Tools\SalesReportTool;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(LazilyRefreshDatabase::class);

test('the sales report MCP tool returns a structured report for a valid date range', function () {
    $user = User::factory()->create();

    Sale::factory()->for($user)->create([
        'total' => '1250.50',
        'created_at' => '2026-10-17 12:00:00',
    ]);

    SalesServer::tool(SalesReportTool::class, [
        'from' => '2026-10-17',
        'to' => '2026-10-17',
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('from', '2026-10-17')
            ->where('to', '2026-10-17')
            ->where('orders', 1)
            ->where('revenue', '1250.5')
            ->etc());
});

<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\SalesSummaryResource;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\LowStockTool;
use App\Mcp\Tools\SalesReportTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Sales Management MCP')]
#[Version('1.0.0')]
#[Instructions('Read-only access to product inventory and sales reports.')]
class SalesServer extends Server
{
    protected array $tools = [
        ListProductsTool::class,
        LowStockTool::class,
        SalesReportTool::class,
    ];

    protected array $resources = [
        SalesSummaryResource::class,
    ];

    protected array $prompts = [
        //
    ];
}

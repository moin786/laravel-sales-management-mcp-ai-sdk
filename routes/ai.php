<?php

use App\Mcp\Servers\SalesServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('sales', SalesServer::class);

Mcp::web('/mcp/sales', SalesServer::class)
    ->middleware(['auth', 'verified'])
    ->name('mcp.sales');

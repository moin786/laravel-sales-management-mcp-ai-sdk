<?php

namespace App\Mcp\Tools;

use App\Services\SalesService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get sales revenue and order count for a date range.')]
class SalesReportTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, SalesService $salesService): Response|ResponseFactory
    {
        $from = $request->get('from');
        $to = $request->get('to');

        if (! $this->isValidDate($from) || ! $this->isValidDate($to) || $from > $to) {
            return Response::error('Provide a valid date range with from and to in YYYY-MM-DD format.');
        }

        return Response::structured($salesService->report($from, $to));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Start date in YYYY-MM-DD format')->required(),
            'to' => $schema->string()->description('End date in YYYY-MM-DD format')->required(),
        ];
    }

    private function isValidDate(mixed $date): bool
    {
        if (! is_string($date) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
            return false;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $date)->format('Y-m-d') === $date;
        } catch (\Throwable) {
            return false;
        }
    }
}

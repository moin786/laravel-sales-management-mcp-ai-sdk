# Building a Sales Management Platform with Laravel MCP, Laravel AI SDK, Inertia, and React

## From operational sales data to a grounded AI assistant

Modern sales tools should do more than record transactions. They should make reliable, current business information easy to understand. This project delivers a focused sales management application with product inventory, sale processing, reporting, and an AI Sales Assistant that answers questions using live application data.

The application is built with Laravel, Inertia v3, React, Laravel MCP, and the official Laravel AI SDK. The important design choice is that the assistant does not guess sales figures or receive unrestricted database access. Instead, it calls a small, read-only Model Context Protocol (MCP) server that exposes purpose-built inventory and reporting tools.

## What the application does

Authenticated users can:

- Maintain a product catalogue with SKU, BDT price, and stock.
- Complete sales while automatically calculating totals and decrementing stock.
- View a dashboard containing product, low-stock, order, and revenue metrics.
- Review the latest completed sales.
- Ask an AI assistant questions such as “Which products need restocking?” or “What were sales on 2026-10-17?”

The business flow is deliberately simple:

```text
React / Inertia UI
        │
        ├── Product and sales routes ──> Laravel controllers ──> SalesService / Eloquent
        │
        └── AI chat JSON route ──> SalesAssistantAgent ──> local Sales MCP server
                                                           │
                                                           └── read-only tools ──> sales data
```

## The sales foundation

Before adding AI, the project establishes a dependable source of truth for the assistant to query.

### Database design

Three migrations model the transactional sales domain:

| File | Responsibility |
| --- | --- |
| `database/migrations/2026_10_17_141948_create_products_table.php` | Creates products with a unique SKU, decimal price, stock count, and timestamps. |
| `database/migrations/2026_10_17_141949_create_sales_table.php` | Creates a sale header linked to the user, stores the total, and indexes `created_at` for report ranges. |
| `database/migrations/2026_10_17_141950_create_sale_items_table.php` | Creates sale lines with product, quantity, unit price, and subtotal. |

Prices use `decimal(12, 2)` rather than floating-point columns. This is essential for currency values such as BDT 1,250.50.

### Eloquent models

The models in `app/Models/Product.php`, `app/Models/Sale.php`, and `app/Models/SaleItem.php` define the catalogue and order relationships:

- A product has many sale items.
- A sale belongs to a user and has many sale items.
- A sale item belongs to both a sale and a product.

Each currency attribute is cast with `decimal:2`, while stock and quantity are cast as integers. The models also use factories, enabling concise, realistic tests.

### Transactional sale processing

`app/Services/SalesService.php` is the application’s most important business service. Its `createSale()` method runs inside a database transaction and locks the selected products before checking availability. This prevents concurrent checkout requests from overselling the same stock.

For each line, the service:

1. Validates that the product still exists and has sufficient stock.
2. Converts the product price to integer cents.
3. Creates an immutable sale item with its unit price and subtotal.
4. Decrements the product stock.
5. Updates the sale total using the final integer-cent amount.

The companion `report()` method receives an inclusive date range and returns the number of orders and revenue. That method is the reporting boundary used by the MCP sales-report tool.

### HTTP layer and validation

The Laravel controllers keep request handling small and delegate business work to the appropriate layer:

| File | Responsibility |
| --- | --- |
| `app/Http/Controllers/DashboardController.php` | Supplies aggregate metrics to the dashboard. |
| `app/Http/Controllers/ProductController.php` | Lists products and creates new catalogue items. |
| `app/Http/Controllers/SaleController.php` | Supplies the sales desk with products and recent orders, then delegates checkout to `SalesService`. |
| `app/Http/Requests/StoreProductRequest.php` | Validates product name, unique SKU, non-negative decimal price, and stock. |
| `app/Http/Requests/StoreSaleRequest.php` | Validates sale lines, existing distinct products, and positive quantities. |

`routes/web.php` protects the dashboard, catalogue, sales desk, and AI chat behind the `auth` and `verified` middleware. The AI chat POST route also applies a ten-requests-per-minute throttle.

## Building the Laravel MCP server

MCP is the bridge between the AI model and the application’s live data. Rather than exposing raw SQL or write operations, the server publishes a narrow contract: search products, find low stock, report sales, and read a compact summary.

### Server registration and transport

`routes/ai.php` registers the same `SalesServer` in two ways:

```php
Mcp::local('sales', SalesServer::class);

Mcp::web('/mcp/sales', SalesServer::class)
    ->middleware(['auth', 'verified'])
    ->name('mcp.sales');
```

The local registration lets the Laravel AI agent launch the server through `php artisan mcp:start sales` over standard input/output. The protected web registration makes the MCP server available to authenticated browser or external MCP clients without making sales data public.

### The MCP server class

`app/Mcp/Servers/SalesServer.php` is the registry for the domain-specific MCP surface.

```php
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
}
```

Its attributes provide a human-readable name, version, and an explicit read-only instruction. This makes the server self-describing for MCP clients and documents the intended security boundary.

### MCP tool files

Each tool has a JSON schema, validates its own input where necessary, queries only the data it needs, and returns structured content for reliable model consumption.

| File | Tool capability | Details |
| --- | --- | --- |
| `app/Mcp/Tools/ListProductsTool.php` | Product lookup | Accepts an optional product-name search, returns up to 50 products ordered by name and ID. |
| `app/Mcp/Tools/LowStockTool.php` | Replenishment insight | Accepts a threshold, guards it between 0 and 100,000, and returns up to 100 products whose stock is below it. |
| `app/Mcp/Tools/SalesReportTool.php` | Date-range reporting | Requires `from` and `to` in `YYYY-MM-DD`, rejects invalid or inverted ranges, then calls `SalesService::report()`. |

The tools return `Response|ResponseFactory`. This distinction matters: `Response::structured()` produces Laravel MCP’s `ResponseFactory`, whereas validation failures use `Response::error()`. Declaring the union return type allows both valid structured data and safe error messages to travel through the MCP protocol.

For example, the report tool has a compact, model-friendly input contract:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'from' => $schema->string()->description('Start date in YYYY-MM-DD format')->required(),
        'to' => $schema->string()->description('End date in YYYY-MM-DD format')->required(),
    ];
}
```

### MCP resource file

`app/Mcp/Resources/SalesSummaryResource.php` publishes the `sales://summary` resource. It returns a compact JSON text document containing total products, low-stock product count, total sales, and total revenue. Resources are useful for a broad read-only snapshot, while tools are better for parameterized questions such as date-range reporting.

## Adding the Laravel AI SDK agent

The Laravel AI SDK provides the conversational agent layer, provider abstraction, conversation persistence, fakes for testing, and MCP-tool integration.

### Package configuration and conversation storage

`config/ai.php` defines the default provider as OpenAI and reads the credential exclusively from `OPENAI_API_KEY`. The value belongs in the local environment and must never be sent to the browser or committed to source control.

The published AI migration, `database/migrations/2026_10_17_143007_create_agent_conversations_table.php`, creates two tables:

- `agent_conversations` stores a conversation ID, participant ownership, title, and timestamps.
- `agent_conversation_messages` stores message content, tool steps, usage, metadata, status, and the participant context.

The `User` model uses the Laravel AI conversation relationship so each chat can be associated with its authenticated owner.

### The sales assistant agent

`app/Ai/Agents/SalesAssistantAgent.php` is intentionally small, but it establishes the application’s AI policy:

```php
#[MaxSteps(5)]
class SalesAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;
}
```

Its role is defined by four pieces:

1. `Promptable` gives the class the `prompt()` API.
2. `Conversational` and `RemembersConversations` persist and reload user conversation context.
3. `HasTools` enables model tool calls.
4. `#[MaxSteps(5)]` limits the number of model/tool steps, containing cost and runaway execution.

The agent instructions establish its behaviour: it is a professional Bangladesh-based sales assistant; it uses MCP tools whenever live product, inventory, or sales data is required; it never invents values; it presents money in BDT; and it never claims to write data.

The `tools()` method connects the agent to the local MCP process:

```php
return Client::local(PHP_BINARY, [base_path('artisan'), 'mcp:start', 'sales'])->tools();
```

Laravel AI SDK wraps these MCP tools for the selected model. The model can therefore ask for structured sales data, but cannot directly execute arbitrary database operations.

### AI chat controller and request validation

`app/Http/Controllers/AiChatController.php` turns a chat request into a guarded agent prompt:

- `index()` renders the Inertia chat page.
- `store()` validates input, verifies ownership when a previous conversation ID is supplied, then starts or continues a conversation as the authenticated user.
- It returns only the answer text and conversation ID as JSON.
- Provider or MCP failures are reported internally and returned to the browser as a safe, generic 503 message.

The ownership check is especially important. It uses `ConversationStore` and `VerifiesConversationOwnership` before continuing a conversation. A user who supplies another user’s UUID receives a 404 response rather than access to a conversation they do not own.

`app/Http/Requests/SendAiChatRequest.php` limits a message to a required string of at most 4,000 characters and accepts only a nullable UUID conversation ID. This keeps malformed data out of the agent pipeline.

## The React and Inertia experience

The frontend lives in `resources/js/pages` and uses Inertia for page navigation and form handling. Wayfinder-generated route helpers are imported instead of hard-coded URLs, so route changes remain type-aware and centrally generated.

### Dashboard view

`resources/js/pages/dashboard.tsx` receives the dashboard summary prop and renders four responsive metric cards:

- Products
- Low stock
- Orders
- Revenue

Currency is formatted with `Intl.NumberFormat('en-BD', { currency: 'BDT' })`, keeping the displayed values appropriate for the target market.

### Product management view

`resources/js/pages/products/index.tsx` combines a product-creation form and inventory table. It uses Inertia’s `useForm` for field state, server validation errors, submission state, and reset-after-success behaviour. The inventory table highlights products below ten units and includes an empty state for a new catalogue.

### Sales desk view

`resources/js/pages/sales/index.tsx` provides a focused checkout interaction. It shows selectable in-stock products, accepts a quantity, posts the selected item to the sales route, and displays the 20 most recent completed sales. The server—not the browser—calculates the final amount and changes inventory, preserving the transactional guarantee.

### AI chat view

`resources/js/pages/ai-chat.tsx` is a dedicated conversational workspace. It manages:

- The visible user and assistant messages.
- The conversation ID returned by the backend.
- A new-chat action that resets only local conversation state.
- A loading indicator while the agent is processing a request.
- Clear error feedback when the endpoint rejects or cannot process a request.

It sends JSON to the generated `ai-chat.store` URL, includes the CSRF token from the page metadata, and uses same-origin credentials. The page intentionally calls a JSON endpoint with `fetch`, avoiding a full Inertia page visit for every individual message.

`resources/js/components/app-sidebar.tsx` completes the navigation work by adding the **AI Assistant** entry beside Dashboard, Products, and Sales.

## Reliability, security, and test coverage

The project treats AI responses as a product feature with normal application safeguards:

- **Least privilege:** MCP tools are read-only and narrowly scoped.
- **Grounded answers:** agent instructions require tool use for live facts and prohibit invented figures.
- **Conversation isolation:** a conversation must belong to the authenticated user before it can be continued.
- **Request controls:** authenticated and verified routes, CSRF protection, validation, and chat rate limiting protect the endpoint.
- **Financial correctness:** checkout uses database transactions, row locking, and integer-cent arithmetic.
- **Provider safety:** API keys remain server-side in environment configuration.

The test suite includes:

| Test file | Coverage |
| --- | --- |
| `tests/Feature/SalesManagementTest.php` | Authentication, product creation and SKU uniqueness, exact sale totals, stock deduction, and insufficient-stock rollback behaviour. |
| `tests/Feature/AiChatTest.php` | AI chat authentication, input validation, persisted fake-agent response, and cross-user conversation protection. |
| `tests/Feature/SalesMcpTest.php` | A live MCP server test that verifies the sales report tool returns structured data for a valid date range. |

## What this architecture makes possible

This implementation is a practical baseline for safe operational AI. The assistant has useful access to current business facts, but its scope is intentional: it can inspect inventory and sales data without altering it. Future improvements can add streaming responses, richer multi-item checkout, date presets, role-based reporting, queued analytics, and additional MCP tools—all without giving the model direct control over the core sales tables.

The result is a sales application where Laravel remains the system of record, MCP remains the controlled data boundary, and Laravel AI SDK turns those trusted capabilities into a natural conversation.

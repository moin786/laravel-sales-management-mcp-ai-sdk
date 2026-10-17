<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guests are redirected from sales management', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
    $this->get(route('sales.index'))->assertRedirect(route('login'));
});

test('an authenticated user can create a product', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Wireless Mouse',
            'sku' => 'MOUSE-001',
            'price' => '1250.50',
            'stock' => 25,
        ])
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', [
        'name' => 'Wireless Mouse',
        'sku' => 'MOUSE-001',
        'price' => '1250.50',
        'stock' => 25,
    ]);
});

test('a product request rejects an existing SKU', function () {
    $user = User::factory()->create();
    Product::factory()->create(['sku' => 'DUPLICATE-SKU']);

    $this->actingAs($user)
        ->from(route('products.index'))
        ->post(route('products.store'), [
            'name' => 'Another product',
            'sku' => 'DUPLICATE-SKU',
            'price' => '1.00',
            'stock' => 1,
        ])
        ->assertRedirect(route('products.index'))
        ->assertSessionHasErrors('sku');
});

test('a sale deducts stock and stores exact line totals', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => '199.99', 'stock' => 4]);

    $this->actingAs($user)
        ->post(route('sales.store'), ['items' => [['product_id' => $product->id, 'quantity' => 3]]])
        ->assertRedirect(route('sales.index'));

    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
    $this->assertDatabaseHas('sales', ['user_id' => $user->id, 'total' => '599.97']);
    $this->assertDatabaseHas('sale_items', ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => '199.99', 'subtotal' => '599.97']);
});

test('a sale with insufficient stock does not create an order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 1]);

    $this->actingAs($user)
        ->from(route('sales.index'))
        ->post(route('sales.store'), ['items' => [['product_id' => $product->id, 'quantity' => 2]]])
        ->assertRedirect(route('sales.index'))
        ->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0);
    expect($product->fresh()->stock)->toBe(1);
});

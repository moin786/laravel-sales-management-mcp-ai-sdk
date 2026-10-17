<?php

use App\Ai\Agents\SalesAssistantAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guests are redirected from the AI chat page', function () {
    $this->get(route('ai-chat.index'))->assertRedirect(route('login'));
});

test('an authenticated user can view the AI chat page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai-chat.index'))
        ->assertOk();
});

test('the chat endpoint requires a message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai-chat.store'), ['message' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('the chat endpoint returns a persisted assistant response', function () {
    $user = User::factory()->create();
    SalesAssistantAgent::fake(['You have two products below the stock threshold.']);

    $this->actingAs($user)
        ->postJson(route('ai-chat.store'), ['message' => 'Which products need restocking?'])
        ->assertOk()
        ->assertJsonPath('message', 'You have two products below the stock threshold.')
        ->assertJsonPath('conversation_id', fn (string $conversationId): bool => $conversationId !== '');

    SalesAssistantAgent::assertPrompted('Which products need restocking?');
    expect($user->conversations()->count())->toBe(1);
});

test('a user cannot continue another user\'s conversation', function () {
    $conversationOwner = User::factory()->create();
    $otherUser = User::factory()->create();
    SalesAssistantAgent::fake(['A sales answer.']);

    $conversationId = $this->actingAs($conversationOwner)
        ->postJson(route('ai-chat.store'), ['message' => 'What sold today?'])
        ->json('conversation_id');

    $this->actingAs($otherUser)
        ->postJson(route('ai-chat.store'), [
            'message' => 'Tell me more.',
            'conversation_id' => $conversationId,
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Conversation not found.');
});

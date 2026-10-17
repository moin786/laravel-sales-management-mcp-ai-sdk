<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SalesAssistantAgent;
use App\Http\Requests\SendAiChatRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\VerifiesConversationOwnership;
use Laravel\Ai\Models\Conversation;
use Throwable;

class AiChatController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ai-chat');
    }

    public function store(
        SendAiChatRequest $request,
        SalesAssistantAgent $salesAssistant,
        ConversationStore $conversationStore,
    ): JsonResponse {
        $validated = $request->validated();
        $conversationId = $validated['conversation_id'] ?? null;

        if ($conversationId !== null && $conversationStore instanceof VerifiesConversationOwnership && ! $conversationStore->conversationBelongsTo(
            $conversationId,
            Conversation::participantType($request->user()),
            Conversation::participantKey($request->user()),
        )) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $response = $salesAssistant
                ->continueOrStart($conversationId, as: $request->user())
                ->prompt($validated['message']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The sales assistant is temporarily unavailable. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'message' => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }
}

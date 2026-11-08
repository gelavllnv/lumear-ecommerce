<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderChatApiController extends Controller
{
    public function logisticsChat(Request $request): JsonResponse
    {
        $rider = $request->attributes->get('rider_api_rider');
        $riderUserId = (int) $request->attributes->get('rider_api_user_id');
        $provider = DB::table('logistics_providers')
            ->join('users', 'users.id', '=', 'logistics_providers.user_id')
            ->where('logistics_providers.id', $rider->logistics_provider_id)
            ->where('logistics_providers.status', 'approved')
            ->select('logistics_providers.id', 'logistics_providers.name', 'users.id as user_id')
            ->first();

        abort_unless($provider, 404, 'Your Logistics hub could not be found.');

        $latest = $this->logisticsMessagesQuery((int) $provider->id, $riderUserId, (int) $provider->user_id)
            ->orderByDesc('id')
            ->first();
        $unreadCount = $this->logisticsMessagesQuery((int) $provider->id, $riderUserId, (int) $provider->user_id)
            ->where('sender_id', (int) $provider->user_id)
            ->where('recipient_id', $riderUserId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'chat' => [
                'hub_name' => $provider->name,
                'last_message' => $latest?->body ?? '',
                'last_message_at' => $latest?->created_at,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function logisticsMessages(Request $request): JsonResponse
    {
        [$providerId, $providerUserId, $riderUserId] = $this->logisticsChatParticipants($request);

        $this->logisticsMessagesQuery($providerId, $riderUserId, $providerUserId)
            ->where('sender_id', $providerUserId)
            ->where('recipient_id', $riderUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        $messages = $this->logisticsMessagesQuery($providerId, $riderUserId, $providerUserId)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message): array => [
                'text' => $message->body,
                'mine' => (int) $message->sender_id === $riderUserId,
                'date' => $message->created_at,
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function sendLogisticsMessage(Request $request): JsonResponse
    {
        [$providerId, $providerUserId, $riderUserId] = $this->logisticsChatParticipants($request);
        $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);
        $body = trim($data['text']);
        abort_if($body === '', 422, 'Message cannot be empty.');

        DB::table('logistics_messages')->insert([
            'logistics_provider_id' => $providerId,
            'sender_id' => $riderUserId,
            'recipient_id' => $providerUserId,
            'body' => $body,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true], 201);
    }

    public function threads(Request $request): JsonResponse
    {
        $rider = $request->attributes->get('rider_api_rider');
        $riderUserId = (int) $request->attributes->get('rider_api_user_id');
        $conversations = Conversation::query()
            ->with(['buyer', 'shipment'])
            ->where('rider_id', $rider->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->whereNull('rider_deleted_at')
            ->get()
            ->groupBy('buyer_id');

        $threads = $conversations->map(function ($buyerConversations) use ($riderUserId): array {
            /** @var Conversation $conversation */
            $conversation = $buyerConversations->sortByDesc('id')->first();
            $conversationIds = $buyerConversations->pluck('id');
            $latest = DB::table('messages')
                ->whereIn('conversation_id', $conversationIds)
                ->orderByDesc('id')
                ->first();

            return [
                    'id' => (int) $conversation->id,
                    'buyer_id' => (int) $conversation->buyer_id,
                    'buyer_name' => $conversation->buyer?->name ?? 'Buyer',
                    'tracking_code' => $conversation->shipment?->tracking_code ?? '',
                    'status' => str_replace('_', ' ', $conversation->shipment?->status ?? 'assigned'),
                    'last_message' => $latest?->body ?? '',
                    'last_message_at' => $latest?->created_at
                        ?? $conversation->last_message_at?->toDateTimeString(),
                    'unread_count' => DB::table('messages')
                        ->whereIn('conversation_id', $conversationIds)
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $riderUserId)
                        ->count(),
            ];
        })->sortByDesc('last_message_at')->values();

        return response()->json(['chats' => $threads]);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeRider($request, $conversation);

        $rider = $request->attributes->get('rider_api_rider');
        Conversation::query()
            ->where('rider_id', $rider->id)
            ->where('buyer_id', $conversation->buyer_id)
            ->whereNull('rider_deleted_at')
            ->update(['rider_deleted_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeRider($request, $conversation);
        $conversationIds = $this->conversationIdsFor($conversation);
        DB::table('messages')
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->attributes->get('rider_api_user_id'))
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json([
            'messages' => DB::table('messages')
                ->whereIn('conversation_id', $conversationIds)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn ($message): array => [
                'text' => $message->body,
                'mine' => (int) $message->sender_id === (int) $request->attributes->get('rider_api_user_id'),
                'date' => $message->created_at,
            ]),
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeRider($request, $conversation);
        $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);
        $body = trim($data['text']);
        abort_if($body === '', 422, 'Message cannot be empty.');

        DB::transaction(function () use ($conversation, $request, $body): void {
            $conversationIds = $this->conversationIdsFor($conversation);
            $conversation->messages()->create([
                'sender_id' => $request->attributes->get('rider_api_user_id'),
                'body' => $body,
            ]);

            Conversation::query()
                ->whereIn('id', $conversationIds)
                ->update([
                    'last_message_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return response()->json(['success' => true]);
    }

    private function authorizeRider(Request $request, Conversation $conversation): void
    {
        $rider = $request->attributes->get('rider_api_rider');
        abort_unless((int) $conversation->rider_id === (int) $rider->id, 403);
        abort_if($conversation->rider_deleted_at !== null, 404);
    }

    /** @return array<int, int> */
    private function conversationIdsFor(Conversation $conversation): array
    {
        return Conversation::query()
            ->where('buyer_id', $conversation->buyer_id)
            ->where('rider_id', $conversation->rider_id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array{int, int, int} */
    private function logisticsChatParticipants(Request $request): array
    {
        $rider = $request->attributes->get('rider_api_rider');
        $provider = DB::table('logistics_providers')
            ->where('id', $rider->logistics_provider_id)
            ->where('status', 'approved')
            ->first(['id', 'user_id']);

        abort_unless($provider, 404, 'Your Logistics hub could not be found.');

        return [(int) $provider->id, (int) $provider->user_id, (int) $request->attributes->get('rider_api_user_id')];
    }

    private function logisticsMessagesQuery(int $providerId, int $riderUserId, int $providerUserId): Builder
    {
        return DB::table('logistics_messages')
            ->where('logistics_provider_id', $providerId)
            ->where(function ($query) use ($riderUserId, $providerUserId): void {
                $query->where(function ($pair) use ($riderUserId, $providerUserId): void {
                    $pair->where('sender_id', $riderUserId)
                        ->where('recipient_id', $providerUserId);
                })->orWhere(function ($pair) use ($riderUserId, $providerUserId): void {
                    $pair->where('sender_id', $providerUserId)
                        ->where('recipient_id', $riderUserId);
                });
            });
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatApiController extends Controller
{
    public function threads(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Conversation::query()
            ->with(['buyer', 'seller', 'rider.user', 'shipment'])
            ->with(['messages' => fn ($messages) => $messages->latest()->limit(1)])
            ->orderByDesc('last_message_at');

        if ($user->isBuyer()) {
            $query->where('buyer_id', $user->id);
        } elseif ($user->isSeller()) {
            $query->where('seller_id', $user->seller?->id);
        } else {
            abort(403, 'Buyer or seller access is required.');
        }

        $conversations = $query->get();
        $threads = collect();

        if ($user->isBuyer()) {
            foreach ($conversations->whereNotNull('rider_id')->groupBy('rider_id') as $riderConversations) {
                $conversation = $riderConversations->sortByDesc('id')->first();
                $conversationIds = $riderConversations->pluck('id');
                $latestMessage = DB::table('messages')
                    ->whereIn('conversation_id', $conversationIds)
                    ->orderByDesc('id')
                    ->first();

                $threads->push([
                    'id' => (string) $conversation->id,
                    'type' => 'rider',
                    'name' => $conversation->rider?->user?->name ?? 'Delivery rider',
                    'tracking_code' => $conversation->shipment?->tracking_code ?? '',
                    'status' => $conversation->shipment?->status ?? 'assigned',
                    'last_message' => $latestMessage?->body ?? '',
                    'last_message_at' => $latestMessage?->created_at
                        ?? $conversation->last_message_at?->toDateTimeString(),
                    'unread_count' => DB::table('messages')
                        ->whereIn('conversation_id', $conversationIds)
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                ]);
            }
        }

        foreach ($conversations->whereNull('rider_id') as $conversation) {
            $message = $conversation->messages->first();

            $threads->push([
                'id' => (string) $conversation->id,
                'type' => $user->isSeller() ? 'buyer' : 'seller',
                'name' => $user->isSeller()
                    ? ($conversation->buyer?->name ?? 'Buyer')
                    : ($conversation->seller?->name ?? 'Shop'),
                'tracking_code' => '',
                'status' => '',
                'last_message' => $message?->body ?? '',
                'last_message_at' => $message?->created_at?->toDateTimeString()
                    ?? $conversation->last_message_at?->toDateTimeString(),
                'unread_count' => 0,
            ]);
        }

        $threads = $threads->sortByDesc('last_message_at')->values();

        return response()->json(['threads' => $threads]);
    }

    public function openSeller(Request $request): JsonResponse
    {
        abort_unless($request->user()->isBuyer(), 403);
        $data = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);
        $seller = Seller::query()->findOrFail($data['seller_id']);
        abort_unless($seller->isApproved() && $seller->products()->whereKey($data['product_id'])->exists(), 422);
        $conversation = Conversation::firstOrCreate([
            'buyer_id' => $request->user()->id,
            'seller_id' => $seller->id,
        ]);

        return response()->json(['id' => (string) $conversation->id, 'name' => $seller->name]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);

        $conversationIds = $this->conversationIdsFor($conversation);
        DB::table('messages')
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json([
            'messages' => DB::table('messages')
                ->whereIn('conversation_id', $conversationIds)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn ($message): array => [
                'text' => $message->body,
                'mine' => (int) $message->sender_id === (int) $request->user()->id,
                'date' => $message->created_at,
            ]),
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);
        $body = trim($data['text']);
        abort_if($body === '', 422, 'Message cannot be empty.');

        DB::transaction(function () use ($conversation, $request, $body): void {
            $conversationIds = $this->conversationIdsFor($conversation);
            $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $body,
            ]);

            Conversation::query()
                ->whereIn('id', $conversationIds)
                ->update([
                    'last_message_at' => now(),
                    'rider_deleted_at' => null,
                    'updated_at' => now(),
                ]);
        });

        return response()->json(['success' => true]);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        $user = $request->user();
        $isParticipant = $conversation->buyer_id === $user->id
            || ($user->isSeller() && $conversation->seller_id === $user->seller?->id);
        abort_unless($isParticipant, 403);
    }

    /** @return array<int, int> */
    private function conversationIdsFor(Conversation $conversation): array
    {
        if (!$conversation->rider_id) {
            return [(int) $conversation->id];
        }

        return Conversation::query()
            ->where('buyer_id', $conversation->buyer_id)
            ->where('rider_id', $conversation->rider_id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}

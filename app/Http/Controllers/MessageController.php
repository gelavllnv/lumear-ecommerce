<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function inbox(Request $request)
    {
        $user = $request->user();
        $query = Conversation::with(['buyer', 'seller.owner'])->orderByDesc('last_message_at');
        $conversations = $user->isSeller()
            ? $query->where('seller_id', $user->seller?->id)->get()
            : $query->where('buyer_id', $user->id)->get();

        return view('messages.inbox', compact('conversations'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->load(['buyer', 'seller.owner', 'messages.sender', 'messages.product.images']);
        $conversation->messages()->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)->update(['read_at' => now()]);

        return view('messages.show', compact('conversation'));
    }

    public function start(Request $request, Seller $seller)
    {
        abort_unless($request->user()->isBuyer() && $seller->isApproved(), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'product_id' => ['required', 'integer', 'exists:products,id']]);
        abort_unless($seller->products()->whereKey($data['product_id'])->exists(), 422, 'The selected product does not belong to this seller.');

        $conversation = DB::transaction(function () use ($request, $seller, $data): Conversation {
            $conversation = Conversation::firstOrCreate(['buyer_id' => $request->user()->id, 'seller_id' => $seller->id]);
            $conversation->messages()->create(['sender_id' => $request->user()->id, 'product_id' => $data['product_id'], 'body' => $data['body']]);
            $conversation->update(['last_message_at' => now()]);
            return $conversation;
        });

        return redirect()->route('messages.show', $conversation)->with('status', 'Message sent to '.$seller->name.'.');
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['body']]);
        $conversation->update(['last_message_at' => now()]);

        return back();
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $user = $request->user();
        $isParticipant = $conversation->buyer_id === $user->id || ($user->isSeller() && $conversation->seller_id === $user->seller?->id);
        abort_unless($isParticipant, 403);
    }
}

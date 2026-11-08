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
        $query = Conversation::with([
            'buyer',
            'seller.owner',
            'rider.user',
            'shipment',
            'messages' => fn ($messages) => $messages->latest()->with('attachments'),
        ])->orderByDesc('last_message_at');
        $conversations = $user->isSeller()
            ? $query->where('seller_id', $user->seller?->id)->get()
            : $query->where('buyer_id', $user->id)->get();

        return view('messages.inbox', compact('conversations'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->load(['buyer', 'seller.owner', 'messages.sender', 'messages.product.images', 'messages.attachments']);
        $conversation->messages()->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)->update(['read_at' => now()]);

        return view('messages.show', compact('conversation'));
    }

    public function start(Request $request, Seller $seller)
    {
        abort_unless($request->user()->isBuyer() && $seller->isApproved(), 403);
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000'], 'product_id' => ['required', 'integer', 'exists:products,id']], ['body.required' => 'Write a message before sending it.', 'body.max' => 'A chat message can contain up to 1,000 characters.']);
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
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:5', 'required_without:body'],
            'attachments.*' => ['file', 'mimetypes:image/jpeg,image/png', 'max:5120'],
        ], [
            'body.required_without' => 'Write a message or attach at least one image before sending it.',
            'body.max' => 'A chat message can contain up to 1,000 characters.',
            'attachments.required_without' => 'Write a message or attach at least one image before sending it.',
            'attachments.max' => 'You can send up to 5 images in one message.',
            'attachments.*.mimetypes' => 'Chat attachments must be JPG or PNG images no larger than 5 MB.',
            'attachments.*.max' => 'Chat attachments must be JPG or PNG images no larger than 5 MB.',
        ]);

        DB::transaction(function () use ($conversation, $request, $data): void {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $data['body'] ?? '',
            ]);

            foreach ($request->file('attachments', []) as $attachment) {
                $path = $attachment->storePublicly('message-attachments', 'public');
                $message->attachments()->create(['path' => $path]);
            }

            $conversation->update(['last_message_at' => now()]);
        });

        return back()->with('status', 'Message sent.');
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $user = $request->user();
        $isParticipant = $conversation->buyer_id === $user->id || ($user->isSeller() && $conversation->seller_id === $user->seller?->id);
        abort_unless($isParticipant, 403);
    }
}

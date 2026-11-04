<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsMessagingApiController extends Controller
{
    private function providerId(): int
    {
        $id = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless($id, 403, 'Approved Logistics account required.');

        return (int) $id;
    }

    private function contacts(int $providerId)
    {
        $riders = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->select(
                'u.id',
                'u.name',
                'u.email'
            )
            ->get()
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'Rider',
                'area' => 'Assigned Rider',
            ]);

        $sellers = DB::table('seller_orders as so')
            ->join('users as u', 'u.id', '=', 'so.seller_id')
            ->where('so.logistics_provider_id', $providerId)
            ->select(
                'u.id',
                'u.name',
                'u.email'
            )
            ->distinct()
            ->get()
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'Seller',
                'area' => 'Connected Seller',
            ]);

        return $riders
            ->concat($sellers)
            ->unique('id')
            ->filter(fn ($contact) =>
                $contact['id'] !== (int) Auth::id()
            )
            ->values();
    }

    private function allowedContact(
        int $providerId,
        int $recipientId
    ): void {
        $allowed = $this->contacts($providerId)
            ->contains('id', $recipientId);

        if (!$allowed) {
            throw ValidationException::withMessages([
                'recipient_id' =>
                    'This contact is not associated with your Logistics account.',
            ]);
        }
    }

    private function messagesQuery(
        int $providerId,
        int $contactId
    ) {
        $me = (int) Auth::id();

        return DB::table('logistics_messages')
            ->where('logistics_provider_id', $providerId)
            ->where(function ($query) use ($me, $contactId) {
                $query
                    ->where(function ($q) use ($me, $contactId) {
                        $q->where('sender_id', $me)
                            ->where('recipient_id', $contactId);
                    })
                    ->orWhere(function ($q) use ($me, $contactId) {
                        $q->where('sender_id', $contactId)
                            ->where('recipient_id', $me);
                    });
            });
    }

    public function index()
    {
        $providerId = $this->providerId();
        $me = (int) Auth::id();

        $contacts = $this->contacts($providerId);

        $conversations = $contacts->map(function ($contact) use (
            $providerId,
            $me
        ) {
            $contactId = $contact['id'];

            $latest = $this->messagesQuery(
                $providerId,
                $contactId
            )
                ->orderByDesc('id')
                ->first();

            $unread = $this->messagesQuery(
                $providerId,
                $contactId
            )
                ->where('recipient_id', $me)
                ->whereNull('read_at')
                ->count();

            return [
                'contactId' => $contactId,
                'unread' => $unread,
                'latest' => $latest ? [
                    'id' => (int) $latest->id,
                    'sender' => (int) $latest->sender_id === $me
                        ? 'me'
                        : 'them',
                    'text' => $latest->body,
                    'time' => $latest->created_at,
                ] : null,
            ];
        })
            ->filter(fn ($chat) => $chat['latest'] !== null)
            ->sortByDesc(fn ($chat) =>
                $chat['latest']['id']
            )
            ->values();

        return response()->json([
            'success' => true,
            'contacts' => $contacts,
            'conversations' => $conversations,
        ]);
    }

    public function show(int $contactId)
    {
        $providerId = $this->providerId();

        $this->allowedContact($providerId, $contactId);

        $me = (int) Auth::id();

        $messages = $this->messagesQuery(
            $providerId,
            $contactId
        )
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message) => [
                'id' => (int) $message->id,
                'sender' => (int) $message->sender_id === $me
                    ? 'me'
                    : 'them',
                'text' => $message->body,
                'time' => $message->created_at,
                'read' => $message->read_at !== null,
            ]);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'body' => 'required|string|min:1|max:2000',
        ]);

        $providerId = $this->providerId();

        $recipientId = (int) $data['recipient_id'];

        $this->allowedContact($providerId, $recipientId);

        $body = trim($data['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Message cannot be empty.',
            ]);
        }

        $id = DB::table('logistics_messages')
            ->insertGetId([
                'logistics_provider_id' => $providerId,
                'sender_id' => Auth::id(),
                'recipient_id' => $recipientId,
                'body' => $body,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'id' => $id,
        ], 201);
    }

    public function markRead(int $contactId)
    {
        $providerId = $this->providerId();

        $this->allowedContact($providerId, $contactId);

        $me = (int) Auth::id();

        $updated = $this->messagesQuery(
            $providerId,
            $contactId
        )
            ->where('sender_id', $contactId)
            ->where('recipient_id', $me)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
        ]);
    }
}

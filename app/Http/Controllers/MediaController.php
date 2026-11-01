<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(Request $request, string $path): StreamedResponse
    {
        abort_unless(
            str_starts_with($path, 'product-images/')
                || str_starts_with($path, 'seller-assets/')
                || str_starts_with($path, 'message-attachments/'),
            404,
        );

        if (str_starts_with($path, 'message-attachments/')) {
            $attachment = MessageAttachment::with('message.conversation')->where('path', $path)->firstOrFail();
            $conversation = $attachment->message->conversation;
            $user = $request->user();
            $isParticipant = $user && (
                $conversation->buyer_id === $user->id
                || ($user->isSeller() && $conversation->seller_id === $user->seller?->id)
            );

            abort_unless($isParticipant, 403);
        }

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}

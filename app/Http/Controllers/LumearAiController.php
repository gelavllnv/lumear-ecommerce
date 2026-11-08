<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LumearAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class LumearAiController extends Controller
{
    public function riderAsk(Request $request, LumearAiService $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'string', 'max:16000'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,txt'],
        ]);

        $history = json_decode($data['history'] ?? '[]', true);
        if (! is_array($history)) {
            $history = [];
        }

        $userId = (int) $request->attributes->get('rider_api_user_id');
        $user = User::query()->findOrFail($userId);

        try {
            $answer = $assistant->reply(
                $user,
                trim($data['question']),
                $history,
                $request->file('attachment'),
                true,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Lumear AI is temporarily unavailable. Please try again later.',
            ], 503);
        }

        return response()->json(['answer' => $answer]);
    }
}

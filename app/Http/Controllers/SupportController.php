<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\LumearAiService;
use Throwable;

class SupportController extends Controller
{
    public function index(): View
    {
        return view('support.index');
    }

    public function ask(Request $request, LumearAiService $assistant): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ], [
            'question.required' => 'Tell us what you need help with.',
            'question.max' => 'Please keep your support question to 500 characters or fewer.',
        ]);

        $question = trim($data['question']);

        if ($question === '') {
            return back()->withErrors(['question' => 'Tell us what you need help with.'])->withInput();
        }

        try {
            $answer = $assistant->reply($request->user(), $question);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['question' => 'Lumear AI is temporarily unavailable. Please try again later.'])
                ->withInput();
        }

        return back()->with('support_answer', [
            'title' => 'Lumear AI',
            'body' => $answer,
            'action_label' => 'Back to Help Center',
            'action_url' => route('support.index'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(): View
    {
        return view('support.index');
    }

    public function ask(Request $request): RedirectResponse
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

        return back()->with('support_answer', $this->answerFor($question));
    }

    /** @return array{title: string, body: string, action_label: string, action_url: string} */
    private function answerFor(string $question): array
    {
        $normalizedQuestion = mb_strtolower($question);

        if ($this->containsAny($normalizedQuestion, ['cash on delivery', 'cod', 'payment', 'pay'])) {
            return [
                'title' => 'Cash on Delivery',
                'body' => 'Lumear currently supports Cash on Delivery only. You pay the courier when the order is delivered. No card, e-wallet, or bank payment is taken online.',
                'action_label' => 'Open my orders',
                'action_url' => route('orders'),
            ];
        }

        if ($this->containsAny($normalizedQuestion, ['order', 'track', 'shipping', 'courier', 'received', 'delivery status', 'where is'])) {
            return [
                'title' => 'Track an order',
                'body' => 'Order updates appear in My Orders. Once a delivered parcel is in your hands, select Confirm receipt to complete the order and unlock product ratings.',
                'action_label' => 'Open my orders',
                'action_url' => route('orders'),
            ];
        }

        if ($this->containsAny($normalizedQuestion, ['address', 'delivery area', 'postal code', 'barangay'])) {
            return [
                'title' => 'Delivery address',
                'body' => 'Add a complete recipient name, Philippine mobile number, street, barangay, city, province, and postal code before placing an order.',
                'action_label' => 'Manage delivery address',
                'action_url' => route('buyer.address.create'),
            ];
        }

        if ($this->containsAny($normalizedQuestion, ['seller', 'chat', 'message', 'reply'])) {
            return [
                'title' => 'Contact a seller',
                'body' => 'Open a product and select Message seller. Your conversation keeps the product card attached, and you can send up to five JPG or PNG images, each no larger than 5 MB.',
                'action_label' => 'Open messages',
                'action_url' => route('messages.index'),
            ];
        }

        return [
            'title' => 'Try one of these topics',
            'body' => 'Ask about COD payment, delivery addresses, order tracking, seller messages, or product questions. For a product-specific question, open the product and message its seller.',
            'action_label' => 'Browse products',
            'action_url' => route('shop'),
        ];
    }

    /** @param array<int, string> $needles */
    private function containsAny(string $question, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($question, $needle)) {
                return true;
            }
        }

        return false;
    }
}

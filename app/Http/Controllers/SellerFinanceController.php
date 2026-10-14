<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\SellerPayoutAccount;
use App\Services\AuditService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class SellerFinanceController extends Controller
{
    public function __construct(private readonly WalletService $wallets, private readonly AuditService $audit) {}

    public function index(Request $request)
    {
        $seller = $this->seller($request);
        $wallet = $this->wallets->accountFor($seller);
        $orders = $seller->sellerOrders()->with('items.product')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('product_id'), fn ($query) => $query->whereHas('items', fn ($items) => $items->where('product_id', $request->integer('product_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()->paginate(30)->withQueryString();
        $products = $seller->products()->orderBy('name')->get(['id', 'name']);
        $filteredNet = $orders->getCollection()->where('status', '!=', 'cancelled')->sum(fn ($order) => $order->subtotal_minor + $order->shipping_fee_minor - $order->commission_minor);

        return view('seller.earnings', [
            'seller' => $seller,
            'wallet' => $wallet->load(['journals' => fn ($query) => $query->latest('occurred_at')->limit(20)]),
            'payoutAccount' => $seller->payoutAccounts()->where('is_default', true)->first(),
            'payouts' => $seller->payoutRequests()->latest()->take(10)->get(),
            'orders' => $orders,
            'products' => $products,
            'filteredNet' => $filteredNet,
        ]);
    }

    public function requestPayout(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:100']]);
        $account = $seller->payoutAccounts()->where('is_default', true)->first();
        abort_unless($account, 422, 'Add manual payout details before requesting a withdrawal.');
        /** @var SellerPayoutAccount $account */
        $payout = $this->wallets->requestPayout($seller, $account, (int) round(((float) $data['amount']) * 100));
        $this->audit->record($request, 'seller.payout_requested', $payout, null, ['amount_minor' => $payout->amount_minor]);

        return back()->with('status', 'Your manual payout request was sent to the finance queue.');
    }

    private function seller(Request $request): Seller
    {
        abort_unless($request->user()?->isSeller(), 403);

        return $request->user()->seller ?? abort(403, 'Seller profile required.');
    }
}

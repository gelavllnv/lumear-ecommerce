<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use App\Models\Seller;
use App\Services\AuditService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SellerReturnController extends Controller
{
    public function __construct(private readonly WalletService $wallets, private readonly AuditService $audit) {}

    public function index(Request $request)
    {
        $seller = $this->seller($request);
        $returns = ReturnRequest::with(['buyer', 'orderItem', 'evidence', 'sellerOrder.order'])
            ->whereHas('sellerOrder', fn ($query) => $query->where('seller_id', $seller->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()->paginate(30)->withQueryString();
        return view('seller.returns.index', compact('returns'));
    }

    public function update(Request $request, ReturnRequest $returnRequest)
    {
        $seller = $this->seller($request);
        abort_unless($returnRequest->sellerOrder()->where('seller_id', $seller->id)->exists(), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'min:10', 'max:300'],
        ], ['reason.min' => 'A rejection reason must contain 10 to 300 characters.']);
        if ($data['decision'] === 'reject' && blank($data['reason'] ?? null)) {
            return back()->withErrors(['reason' => 'Provide a rejection reason containing 10 to 300 characters.']);
        }

        DB::transaction(function () use ($returnRequest, $data, $request): void {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($returnRequest->id);
            if ($locked->status !== 'requested') {
                abort(422, 'This return request has already been resolved or escalated.');
            }
            if ($data['decision'] === 'approve') {
                $locked->update(['status' => 'approved', 'pickup_status' => 'awaiting_assignment', 'seller_response' => null, 'responded_at' => now()]);
                $this->wallets->reverseForReturn($locked->sellerOrder, $request->user()->id);
            } else {
                $locked->update(['status' => 'rejected', 'seller_response' => trim($data['reason']), 'responded_at' => now()]);
            }
        });
        $returnRequest->refresh();
        $this->audit->record($request, 'seller.return_request_updated', $returnRequest, null, ['status' => $returnRequest->status]);
        return back()->with('status', 'Return request '.$returnRequest->status.'.');
    }

    private function seller(Request $request): Seller
    {
        abort_unless($request->user()?->isSeller(), 403);
        return $request->user()->seller ?? abort(403, 'Seller profile required.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerKycSubmission;
use App\Models\SellerPayoutRequest;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SecureDocumentStorage;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(private readonly AuditService $audit, private readonly WalletService $wallets, private readonly SecureDocumentStorage $documents) {}

    public function dashboard()
    {
        $pendingSellers = Seller::with(['user', 'pickupAddress'])->where('status', 'pending')->latest()->get();

        return view('admin.dashboard', [
            'userCount' => User::count(),
            'approvedSellerCount' => Seller::where('status', 'approved')->count(),
            'pendingSellerCount' => $pendingSellers->count(),
            'orderCount' => Order::count(),
            'salesMinor' => Order::where('payment_status', 'paid')->sum('total_minor'),
            'pendingSellers' => $pendingSellers,
            'pendingKyc' => SellerKycSubmission::with('seller.owner')->where('status', 'pending')->latest()->take(8)->get(),
            'pendingPayouts' => SellerPayoutRequest::with(['seller', 'account'])->whereIn('status', ['requested', 'approved'])->latest()->take(8)->get(),
            'recentOrders' => Order::with(['buyer', 'sellerOrders.seller'])->latest()->take(8)->get(),
            'flaggedProducts' => Product::with(['seller.owner', 'category', 'images'])->where('moderation_status', 'flagged')->latest()->take(20)->get(),
        ]);
    }

    public function updateSeller(Request $request, Seller $seller)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'suspended'])], 'rejection_reason' => ['nullable', 'string', 'max:3000', 'required_if:status,rejected']]);
        if ($data['status'] === 'approved') {
            abort_unless($seller->kycSubmissions()->where('status', 'approved')->exists(), 422, 'Approve the seller KYC submission before approving the store.');
        }
        $before = $seller->only(['status', 'rejection_reason']);
        $seller->update(['status' => $data['status'], 'rejection_reason' => $data['status'] === 'rejected' ? $data['rejection_reason'] : null]);
        $this->audit->record($request, 'admin.seller_status_changed', $seller, $before, $seller->only(['status', 'rejection_reason']));
        return back()->with('status', "{$seller->name} is now {$data['status']}.");
    }

    public function reviewKyc(Request $request, SellerKycSubmission $kyc)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'rejection_reason' => ['nullable', 'string', 'max:3000', 'required_if:status,rejected']]);
        if ($data['status'] === 'approved') {
            $seller = $kyc->seller;
            abort_unless($seller->owner->hasVerifiedEmail(), 422, 'The seller must verify their email first.');
            abort_unless($seller->pickup_address_id && $seller->agreementAcceptances()->where('version', 'seller-terms-1.0')->exists(), 422, 'The seller must complete pickup address and seller agreement onboarding before KYC approval.');
        }
        $before = $kyc->only(['status', 'rejection_reason']);
        $kyc->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === 'rejected' ? $data['rejection_reason'] : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $this->audit->record($request, 'admin.kyc_reviewed', $kyc, $before, $kyc->only(['status', 'rejection_reason']));

        return back()->with('status', "KYC submission {$data['status']}." );
    }

    public function downloadKycDocument(Request $request, SellerKycSubmission $kyc, string $document)
    {
        abort_unless(in_array($document, ['government_id', 'business_permit', 'business_registration', 'bir_certificate', 'selfie'], true), 404);
        $path = $kyc->{"{$document}_path"};
        abort_unless($path, 404);

        $this->audit->record($request, 'admin.kyc_document_downloaded', $kyc, null, ['document' => $document]);

        return $this->documents->download($path, "kyc-{$kyc->id}-{$document}");
    }

    public function updatePayout(Request $request, SellerPayoutRequest $payout)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'paid', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:3000', 'required_if:status,rejected'],
            'payment_reference' => ['nullable', 'string', 'max:255', 'required_if:status,paid'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $before = $payout->only(['status', 'reason', 'payment_reference']);
        if ($data['status'] === 'rejected') {
            $this->wallets->rejectPayout($payout, $request->user()->id, $data['reason']);
        } elseif ($data['status'] === 'paid') {
            $proofPath = $request->file('proof') ? $this->documents->store($request->file('proof'), 'payout-proofs') : null;
            $this->wallets->markPayoutPaid($payout, $request->user()->id, $data['payment_reference'], $proofPath);
        } else {
            $payout->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'reason' => null]);
        }
        $payout->refresh();
        $this->audit->record($request, 'admin.payout_updated', $payout, $before, $payout->only(['status', 'reason', 'payment_reference']));

        return back()->with('status', "Payout #{$payout->id} is now {$payout->status}.");
    }

    public function moderateProduct(Request $request, Product $product)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'remove_and_suspend_seller'])],
            'reason' => ['nullable', 'string', 'max:3000', 'required_if:decision,remove_and_suspend_seller'],
        ]);
        $before = $product->only(['is_active', 'moderation_status', 'moderation_reason']);
        if ($data['decision'] === 'approve') {
            $product->update(['is_active' => true, 'moderation_status' => 'active', 'moderation_reason' => null]);
        } else {
            $product->update(['is_active' => false, 'moderation_status' => 'removed', 'moderation_reason' => $data['reason']]);
            $product->seller->update(['status' => 'suspended', 'rejection_reason' => 'Suspended after prohibited listing review: '.$data['reason']]);
        }
        $this->audit->record($request, 'admin.product_moderated', $product, $before, $product->only(['is_active', 'moderation_status', 'moderation_reason']));

        return back()->with('status', $data['decision'] === 'approve' ? 'Listing approved and restored.' : 'Listing removed and the seller was suspended.');
    }
}

<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerPayoutAccount;
use App\Models\SellerPayoutRequest;
use App\Models\WalletAccount;
use App\Models\WalletJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function accountFor(Seller $seller): WalletAccount
    {
        return WalletAccount::firstOrCreate(['seller_id' => $seller->id]);
    }

    /** Record the net COD sale as pending; it cannot be withdrawn yet. */
    public function recordPendingSale(SellerOrder $sellerOrder): void
    {
        $account = $this->accountFor($sellerOrder->seller);
        $netMinor = $sellerOrder->subtotal_minor + $sellerOrder->shipping_fee_minor - $sellerOrder->commission_minor;

        $account->increment('pending_minor', $netMinor);

        WalletJournal::create([
            'transaction_uuid' => (string) Str::uuid(),
            'wallet_account_id' => $account->id,
            'seller_order_id' => $sellerOrder->id,
            'entry_type' => 'cod_sale_pending',
            'pending_delta' => $netMinor,
            'reference' => $sellerOrder->order->reference,
            'metadata' => ['commission_minor' => $sellerOrder->commission_minor],
            'occurred_at' => now(),
        ]);
    }

    public function requestPayout(Seller $seller, SellerPayoutAccount $payoutAccount, int $amountMinor): SellerPayoutRequest
    {
        return DB::transaction(function () use ($seller, $payoutAccount, $amountMinor): SellerPayoutRequest {
            $account = WalletAccount::where('seller_id', $seller->id)->lockForUpdate()->firstOrCreate(['seller_id' => $seller->id]);

            if ($amountMinor < 10000) {
                throw ValidationException::withMessages(['amount' => 'The minimum withdrawal is ₱100.00.']);
            }

            if ($account->available_minor < $amountMinor) {
                throw ValidationException::withMessages(['amount' => 'The requested amount exceeds your available balance.']);
            }

            $account->decrement('available_minor', $amountMinor);
            $request = SellerPayoutRequest::create([
                'seller_id' => $seller->id,
                'seller_payout_account_id' => $payoutAccount->id,
                'amount_minor' => $amountMinor,
            ]);

            WalletJournal::create([
                'transaction_uuid' => (string) Str::uuid(),
                'wallet_account_id' => $account->id,
                'entry_type' => 'payout_reserved',
                'available_delta' => -$amountMinor,
                'reference' => 'PAYOUT-'.$request->id,
                'metadata' => ['payout_request_id' => $request->id],
                'occurred_at' => now(),
            ]);

            return $request;
        });
    }

    /** Release one delivered COD sale after an administrator confirms remittance. */
    public function releaseCodEarnings(SellerOrder $sellerOrder, int $adminId, string $remittanceReference): void
    {
        DB::transaction(function () use ($sellerOrder, $adminId, $remittanceReference): void {
            $sellerOrder = SellerOrder::with('seller')->lockForUpdate()->findOrFail($sellerOrder->id);
            if (! in_array($sellerOrder->status, ['delivered', 'completed'], true)) {
                throw ValidationException::withMessages(['seller_order' => 'Only delivered COD orders can be released.']);
            }

            $alreadyReleased = WalletJournal::where('seller_order_id', $sellerOrder->id)->where('entry_type', 'cod_sale_released')->exists();
            if ($alreadyReleased) {
                throw ValidationException::withMessages(['seller_order' => 'This COD earning has already been released.']);
            }

            $wallet = WalletAccount::where('seller_id', $sellerOrder->seller_id)->lockForUpdate()->firstOrCreate(['seller_id' => $sellerOrder->seller_id]);
            $netMinor = $sellerOrder->subtotal_minor + $sellerOrder->shipping_fee_minor - $sellerOrder->commission_minor;
            if ($wallet->pending_minor < $netMinor) {
                throw ValidationException::withMessages(['seller_order' => 'The pending wallet balance does not match this order.']);
            }

            $wallet->decrement('pending_minor', $netMinor);
            $wallet->increment('available_minor', $netMinor);
            WalletJournal::create([
                'transaction_uuid' => (string) Str::uuid(),
                'wallet_account_id' => $wallet->id,
                'seller_order_id' => $sellerOrder->id,
                'entry_type' => 'cod_sale_released',
                'available_delta' => $netMinor,
                'pending_delta' => -$netMinor,
                'reference' => $remittanceReference,
                'metadata' => ['released_by' => $adminId],
                'occurred_at' => now(),
            ]);
        });
    }

    public function rejectPayout(SellerPayoutRequest $payout, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($payout, $adminId, $reason): void {
            $payout = SellerPayoutRequest::lockForUpdate()->findOrFail($payout->id);
            if (! in_array($payout->status, ['requested', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'Only requested or approved payouts can be rejected.']);
            }

            $wallet = WalletAccount::where('seller_id', $payout->seller_id)->lockForUpdate()->firstOrFail();
            $wallet->increment('available_minor', $payout->amount_minor);
            $payout->update(['status' => 'rejected', 'reason' => $reason, 'reviewed_by' => $adminId, 'reviewed_at' => now()]);

            WalletJournal::create([
                'transaction_uuid' => (string) Str::uuid(),
                'wallet_account_id' => $wallet->id,
                'entry_type' => 'payout_reversed',
                'available_delta' => $payout->amount_minor,
                'reference' => 'PAYOUT-'.$payout->id,
                'metadata' => ['reason' => $reason],
                'occurred_at' => now(),
            ]);
        });
    }

    public function markPayoutPaid(SellerPayoutRequest $payout, int $adminId, string $reference, ?string $proofPath = null): void
    {
        DB::transaction(function () use ($payout, $adminId, $reference, $proofPath): void {
            $payout = SellerPayoutRequest::lockForUpdate()->findOrFail($payout->id);
            if (! in_array($payout->status, ['requested', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'Only requested or approved payouts can be marked paid.']);
            }

            $payout->update([
                'status' => 'paid',
                'payment_reference' => $reference,
                'proof_path' => $proofPath,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'paid_at' => now(),
            ]);
        });
    }
}

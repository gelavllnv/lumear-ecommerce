<?php

namespace App\Http\Controllers;

use App\Models\BuyerSellerVoucher;
use App\Models\SellerVoucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerVoucherController extends Controller
{
    public function claim(Request $request, SellerVoucher $voucher): RedirectResponse
    {
        abort_unless($request->user()?->isBuyer(), 403);

        DB::transaction(function () use ($request, $voucher): void {
            $voucher = SellerVoucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if (! $voucher->is_claimable || ! $voucher->isActive()) {
                throw ValidationException::withMessages(['voucher' => 'This voucher is no longer available to claim.']);
            }

            BuyerSellerVoucher::firstOrCreate(
                ['buyer_id' => $request->user()->id, 'seller_voucher_id' => $voucher->id],
                ['claimed_at' => now(), 'use_count' => 0],
            );
        });

        return back()->with('status', "Voucher {$voucher->code} added to your vouchers.");
    }
}

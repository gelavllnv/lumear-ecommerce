<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Seller extends Model
{
    protected $guarded = [];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Kept as a readable alias for storefront and dashboard views. */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function onboardingSteps(): HasMany
    {
        return $this->hasMany(SellerOnboardingStep::class);
    }

    public function kycSubmissions(): HasMany
    {
        return $this->hasMany(SellerKycSubmission::class);
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(SellerPayoutAccount::class);
    }

    public function walletAccount(): HasOne
    {
        return $this->hasOne(WalletAccount::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(SellerPayoutRequest::class);
    }

    public function agreementAcceptances(): HasMany
    {
        return $this->hasMany(SellerAgreementAcceptance::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Category;
use App\Models\Seller;
use App\Models\SellerAgreementAcceptance;
use App\Models\SellerKycSubmission;
use App\Models\SellerOnboardingStep;
use App\Models\SellerPayoutAccount;
use App\Services\AuditService;
use App\Services\SecureDocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SellerOnboardingController extends Controller
{
    public function __construct(private readonly AuditService $audit, private readonly SecureDocumentStorage $documents) {}

    public function show(Request $request)
    {
        $seller = $this->seller($request);
        $steps = $this->steps($seller);

        return view('seller.onboarding', [
            'seller' => $seller,
            'steps' => $steps,
            'kyc' => $seller->kycSubmissions()->latest()->first(),
            'payoutAccount' => $seller->payoutAccounts()->where('is_default', true)->first(),
            'categories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function submitKyc(Request $request)
    {
        $seller = $this->seller($request);
        $businessType = $seller->profile?->business_type;
        $data = $request->validate([
            'tax_number' => ['required', 'string', 'max:100'],
            'government_id' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'business_permit' => [Rule::requiredIf($businessType !== 'individual'), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'selfie' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $kyc = DB::transaction(function () use ($request, $seller, $data): SellerKycSubmission {
            $submission = SellerKycSubmission::create([
                'seller_id' => $seller->id,
                'business_type' => $businessType,
                'tax_number' => $data['tax_number'],
                'government_id_path' => $this->documents->store($request->file('government_id'), "seller-kyc/{$seller->id}"),
                'business_permit_path' => $request->file('business_permit') ? $this->documents->store($request->file('business_permit'), "seller-kyc/{$seller->id}") : null,
                'selfie_path' => $request->file('selfie') ? $this->documents->store($request->file('selfie'), "seller-kyc/{$seller->id}") : null,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
            $seller->profile()->updateOrCreate([], ['tax_number' => $data['tax_number']]);
            // A corrected submission reopens a rejected application for admin review.
            if ($seller->status === 'rejected') {
                $seller->update(['status' => 'pending', 'rejection_reason' => null]);
            }
            SellerOnboardingStep::updateOrCreate(['seller_id' => $seller->id, 'step' => 'kyc_submitted'], ['completed_at' => now()]);

            return $submission;
        });

        $this->audit->record($request, 'seller.kyc_submitted', $kyc, null, ['status' => 'pending']);

        return back()->with('status', 'Your documents were submitted securely for review.');
    }

    public function saveStoreProfile(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'pickup_recipient' => ['required', 'string', 'max:255'],
            'pickup_phone' => ['required', 'string', 'max:255'],
            'pickup_line1' => ['required', 'string', 'max:255'],
            'pickup_barangay' => ['required', 'string', 'max:255'],
            'pickup_city' => ['required', 'string', 'max:255'],
            'pickup_province' => ['required', 'string', 'max:255'],
            'pickup_postal_code' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($seller, $data): void {
            $addressValues = [
                'user_id' => $seller->user_id,
                'label' => 'Shop pickup address',
                'recipient' => $data['pickup_recipient'],
                'phone' => $data['pickup_phone'],
                'line1' => $data['pickup_line1'],
                'barangay' => $data['pickup_barangay'],
                'city' => $data['pickup_city'],
                'province' => $data['pickup_province'],
                'postal_code' => $data['pickup_postal_code'],
                'is_default' => true,
            ];
            $address = $seller->pickup_address_id ? Address::findOrFail($seller->pickup_address_id) : new Address();
            $address->fill($addressValues)->save();
            if ($seller->category_id && $seller->category_id !== (int) $data['category_id']) {
                abort(422, 'Your store category is locked after it has been selected. Contact an administrator if it was assigned incorrectly.');
            }
            $seller->update(['name' => $data['business_name'], 'category_id' => $data['category_id'], 'pickup_address_id' => $address->id]);
            $seller->profile()->updateOrCreate([], ['business_name' => $data['business_name']]);
            SellerOnboardingStep::updateOrCreate(['seller_id' => $seller->id, 'step' => 'store_profile'], ['completed_at' => now()]);
        });

        $this->audit->record($request, 'seller.store_profile_updated', $seller, null, ['name' => $data['business_name']]);

        return back()->with('status', 'Store and pickup profile saved.');
    }

    public function savePayoutAccount(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate([
            'type' => ['required', 'in:bank,e_wallet'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
        ]);

        $account = DB::transaction(function () use ($seller, $data): SellerPayoutAccount {
            $seller->payoutAccounts()->where('is_default', true)->update(['is_default' => false]);
            $account = $seller->payoutAccounts()->create([...$data, 'is_default' => true]);
            SellerOnboardingStep::updateOrCreate(['seller_id' => $seller->id, 'step' => 'payout_details'], ['completed_at' => now()]);

            return $account;
        });

        $this->audit->record($request, 'seller.payout_account_added', $account, null, ['type' => $account->type]);

        return back()->with('status', 'Manual payout details saved securely.');
    }

    public function acceptAgreement(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate(['agreement' => ['accepted']]);
        $acceptance = SellerAgreementAcceptance::firstOrCreate([
            'seller_id' => $seller->id,
            'version' => 'seller-terms-1.0',
        ], [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accepted_at' => now(),
        ]);
        SellerOnboardingStep::updateOrCreate(['seller_id' => $seller->id, 'step' => 'seller_agreement'], ['completed_at' => now()]);
        $this->audit->record($request, 'seller.agreement_accepted', $acceptance, null, ['version' => $acceptance->version]);

        return back()->with('status', 'Seller agreement accepted.');
    }

    private function seller(Request $request): Seller
    {
        abort_unless($request->user()?->isSeller(), 403);

        return $request->user()->seller ?? abort(403, 'Seller profile required.');
    }

    /** @return array<int, array{label: string, complete: bool}> */
    private function steps(Seller $seller): array
    {
        $completed = $seller->onboardingSteps()->whereNotNull('completed_at')->pluck('step')->all();

        return [
            ['label' => 'Store profile', 'complete' => in_array('store_profile', $completed, true)],
            ['label' => 'KYC submitted', 'complete' => in_array('kyc_submitted', $completed, true)],
            ['label' => 'Payout details', 'complete' => in_array('payout_details', $completed, true)],
            ['label' => 'Seller agreement', 'complete' => in_array('seller_agreement', $completed, true)],
            ['label' => 'First product', 'complete' => in_array('first_product', $completed, true)],
        ];
    }
}

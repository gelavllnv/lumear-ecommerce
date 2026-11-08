<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SellerOrder;
use App\Models\OrderTimeline;
use App\Notifications\MarketplaceAlert;
use App\Services\LogisticsWalletService;
use App\Services\RiderJobPolicy;
use App\Services\RiderPayrollService;
use App\Services\RiderRemittanceService;
use App\Services\RiderTaskAssignmentService;
use App\Support\LogisticsNotification;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiderMobileApiController extends Controller
{
    public function sendPasswordResetCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($data['email']));
        $user = User::query()->where('email', $email)->whereHas('rider')->first();

        if ($user) {
            $code = (string) random_int(1000, 9999);
            DB::table('rider_password_reset_codes')->updateOrInsert(
                ['email' => $email],
                [
                    'code_hash' => Hash::make($code),
                    'reset_token_hash' => null,
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(10),
                    'verified_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            Mail::raw("Your Lumear Rider password reset code is {$code}. It expires in 10 minutes.", function ($message) use ($email): void {
                $message->to($email)->subject('Lumear Rider password reset code');
            });
        }

        return response()->json([
            'message' => 'If a rider account exists for this email, a reset code has been sent.',
        ]);
    }

    public function verifyPasswordResetCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:4'],
        ]);
        $email = Str::lower(trim($data['email']));
        $reset = DB::table('rider_password_reset_codes')->where('email', $email)->first();

        if (! $reset || now()->greaterThan($reset->expires_at) || $reset->verified_at !== null || $reset->attempts >= 5) {
            throw ValidationException::withMessages(['code' => 'The code is invalid or expired. Request a new code.']);
        }

        if (! Hash::check($data['code'], $reset->code_hash)) {
            DB::table('rider_password_reset_codes')->where('email', $email)->increment('attempts');
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }

        $resetToken = Str::random(64);
        DB::table('rider_password_reset_codes')->where('email', $email)->update([
            'reset_token_hash' => hash('sha256', $resetToken),
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['reset_token' => $resetToken]);
    }

    public function resetForgottenPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = Str::lower(trim($data['email']));
        $reset = DB::table('rider_password_reset_codes')->where('email', $email)->first();

        if (! $reset || ! $reset->verified_at || now()->greaterThan($reset->expires_at)
            || ! hash_equals((string) $reset->reset_token_hash, hash('sha256', $data['reset_token']))) {
            throw ValidationException::withMessages(['email' => 'Password reset session is invalid or expired. Request a new code.']);
        }

        $user = User::query()->where('email', $email)->whereHas('rider')->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'No rider account was found for this email.']);
        }

        DB::transaction(function () use ($user, $data, $email): void {
            $user->update(['password' => Hash::make($data['password'])]);
            DB::table('rider_api_tokens')->where('user_id', $user->id)->delete();
            DB::table('rider_password_reset_codes')->where('email', $email)->delete();
        });

        return response()->json(['message' => 'Your rider password has been reset.']);
    }

    private function rider(Request $request): object
    {
        return $request->attributes->get('rider_api_rider');
    }

    private function profile(object $rider): array
    {
        $user = DB::table('users')->where('id', $rider->user_id)->first();
        $application = DB::table('logistics_rider_applications')
            ->where('user_id', $rider->user_id)
            ->where('status', 'approved')
            ->first();

        return [
            'id' => $rider->id,
            'rider_id' => 'RDR-' . str_pad((string) $rider->id, 3, '0', STR_PAD_LEFT),
            'name' => $rider->name ?: $user->name,
            'email' => $rider->email ?: $user->email,
            'phone' => $rider->phone ?: ($user->phone ?? ''),
            'birthday' => ($rider->date_of_birth ?? null) ?: ($application->date_of_birth ?? null) ?: ($user->date_of_birth ?? ''),
            'sex' => ($rider->sex ?? null) ?: ($application->sex ?? ''),
            'region' => ($rider->region ?? null) ?: ($application->region ?? ''),
            'province' => ($rider->province ?? null) ?: ($application->province ?? ''),
            'municipality' => ($rider->municipality ?? null) ?: ($application->municipality ?? ''),
            'municipality_psgc_code' => ($rider->municipality_psgc_code ?? null) ?: ($application->municipality_psgc_code ?? ''),
            'postal_code' => ($rider->postal_code ?? null) ?: ($application->postal_code ?? ''),
            'barangay' => ($rider->barangay ?? null) ?: ($application->barangay ?? ''),
            'street' => ($rider->street ?? null) ?: ($application->street ?? ''),
            'house_unit' => ($rider->house_unit ?? null) ?: ($application->house_unit ?? ''),
            'vehicle_type' => $rider->vehicle_type,
            'job_type' => $rider->job_type ?? '',
            'license_number' => ($rider->license_no ?? null) ?: ($application->license_no ?? ''),
            'plate_number' => ($rider->plate_no ?? null) ?: ($application->plate_no ?? ''),
            'delivered_count' => DB::table('shipments')->where('rider_id', $rider->id)->where('status', 'delivered')->count(),
            'member_days' => max(0, (int) \Illuminate\Support\Carbon::parse($rider->created_at)->diffInDays(now())),
            'rating' => null,
            'license_expiry' => $rider->license_expires_at,
            'status' => $rider->management_status ?? 'Offline',
            'shift_start_time' => $rider->shift_start_time,
            'shift_end_time' => $rider->shift_end_time,
            'is_available' => (bool) $rider->is_active && $rider->management_status === 'Available',
            'provider' => DB::table('logistics_providers')->where('id', $rider->logistics_provider_id)->value('name'),
        ];
    }

    public function document(Request $request, string $type): StreamedResponse
    {
        $documentFields = [
            'driver_license' => 'driver_license',
            'or_document' => 'or_document',
            'cr_document' => 'cr_document',
        ];
        abort_unless(isset($documentFields[$type]), 404);

        $rider = $this->rider($request);
        $application = DB::table('logistics_rider_applications')
            ->where('user_id', $rider->user_id)
            ->where('status', 'approved')
            ->first();

        $riderDocuments = json_decode((string) ($rider->document_paths ?? ''), true);
        $applicationDocuments = json_decode(
            (string) ($application->document_paths ?? ''),
            true
        );
        $documents = array_replace(
            is_array($applicationDocuments) ? $applicationDocuments : [],
            is_array($riderDocuments) ? $riderDocuments : [],
        );
        $path = $documents[$documentFields[$type]] ?? null;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404, 'Document not found.');

        return Storage::disk('local')->download($path);
    }

    public function updateDocuments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'driver_license' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'or_document' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'cr_document' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'license_expiry' => ['sometimes', 'nullable', 'date'],
        ]);

        $rider = $this->rider($request);
        $riderDocuments = json_decode((string) ($rider->document_paths ?? ''), true);
        $approvedApplication = DB::table('logistics_rider_applications')
            ->where('user_id', $rider->user_id)
            ->where('status', 'approved')
            ->latest('id')
            ->first();
        $applicationDocuments = json_decode((string) ($approvedApplication->document_paths ?? ''), true);
        $documents = array_replace(
            is_array($applicationDocuments) ? $applicationDocuments : [],
            is_array($riderDocuments) ? $riderDocuments : [],
        );
        $replacedDocuments = [];

        foreach (['driver_license', 'or_document', 'cr_document'] as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $oldPath = $documents[$field] ?? null;
            if (is_string($oldPath) && str_starts_with($oldPath, "riders/{$rider->id}/documents/")) {
                $replacedDocuments[] = $oldPath;
            }

            $documents[$field] = $request->file($field)->store(
                "riders/{$rider->id}/documents",
                'local'
            );
        }

        $updates = ['updated_at' => now()];
        if ($documents !== []) {
            $updates['document_paths'] = json_encode($documents);
        }
        if (array_key_exists('license_expiry', $data)) {
            $updates['license_expires_at'] = $data['license_expiry'];
        }

        DB::table('riders')->where('id', $rider->id)->update($updates);
        if ($replacedDocuments !== []) {
            Storage::disk('local')->delete($replacedDocuments);
        }

        return response()->json([
            'success' => true,
            'rider' => $this->profile(DB::table('riders')->where('id', $rider->id)->first()),
        ]);
    }

    private function caBundlePath(): string
    {
        $path = config('services.google.ca_bundle');

        if (! is_string($path) || ! is_readable($path)) {
            throw new \RuntimeException('Google sign-in requires a readable CA certificate bundle for secure HTTPS connections.');
        }

        return $path;
    }

    public function googleLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['id_token' => ['required', 'string', 'max:8192']]);
        $clientId = config('services.google.mobile_client_id');
        abort_unless(filled($clientId), 503, 'Google sign-in is not configured on the server.');

        $client = new GoogleClient(['client_id' => $clientId]);
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => $this->caBundlePath()]));
        $claims = $client->verifyIdToken($data['id_token']);
        abort_unless(is_array($claims), 401, 'Google identity verification failed.');
        abort_unless(in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true), 401, 'Invalid Google token issuer.');
        abort_unless(($claims['aud'] ?? null) === $clientId, 401, 'Invalid Google token audience.');
        abort_unless(filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN), 401, 'Your Google email address must be verified.');
        abort_unless(isset($claims['email']), 401, 'Google account information is incomplete.');

        $user = User::whereRaw('LOWER(email) = ?', [Str::lower((string) $claims['email'])])->first();
        if (! $user) {
            return response()->json([
                'action' => 'signup',
                'name' => (string) ($claims['name'] ?? ''),
                'email' => Str::lower((string) $claims['email']),
            ]);
        }
        abort_unless(! $user->is_suspended, 403, 'This account is currently unavailable.');
        $rider = DB::table('riders')->where('user_id', $user->id)->first();
        if (! $rider) {
            $application = DB::table('logistics_rider_applications')->where('user_id', $user->id)->latest('id')->first();
            if ($application && $application->status === 'pending') {
                return response()->json(['action' => 'pending']);
            }
            abort(403, 'This email already has a Lumear account but no rider application. Sign in with that account before applying as a rider.');
        }
        abort_unless($rider->management_status !== 'Suspended' && DB::table('logistics_providers')->where('id', $rider->logistics_provider_id)->where('status', 'approved')->exists(), 403, 'This rider account is currently unavailable.');

        DB::table('rider_api_tokens')->where('user_id', $user->id)->delete();
        $plainToken = Str::random(80);
        DB::table('rider_api_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['token' => $plainToken, 'rider' => $this->profile($rider)]);
    }

    public function register(Request $request)
    {
        $adultCutoff = now()->subYears(18)->toDateString();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'google_id_token' => ['nullable', 'string', 'max:8192'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'max:30'],
            'sex' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'birthday' => ['required', 'date', "before_or_equal:$adultCutoff"],
            'region' => ['required', 'string', 'max:150'],
            'province' => ['required', 'string', 'max:150'],
            'municipality' => ['required', 'string', 'max:150'],
            'municipality_psgc_code' => ['required', 'string', 'max:20'],
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/'],
            'logistics_provider_id' => [
                'required',
                'integer',
                Rule::exists('logistics_providers', 'id')->where('status', 'approved'),
            ],
            'barangay' => ['required', 'string', 'max:150'],
            'street' => ['required', 'string', 'max:150'],
            'house_unit' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['required', Rule::in(['Motorcycle', 'Tricycle', 'Van', 'Truck'])],
            'job_type' => ['required', Rule::in(app(RiderJobPolicy::class)->jobsFor($request->string('vehicle_type')->toString()))],
            'license_number' => ['required', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:100'],
            'driver_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'or_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'cr_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
        ], [
            'birthday.before_or_equal' => 'Riders must be at least 18 years old.',
        ]);

        if (filled($data['google_id_token'] ?? null)) {
            $clientId = config('services.google.mobile_client_id');
            abort_unless(filled($clientId), 503, 'Google sign-in is not configured on the server.');
            $client = new GoogleClient(['client_id' => $clientId]);
            $client->setHttpClient(new \GuzzleHttp\Client(['verify' => $this->caBundlePath()]));
            $claims = $client->verifyIdToken($data['google_id_token']);
            abort_unless(is_array($claims) && ($claims['aud'] ?? null) === $clientId, 401, 'Google identity verification failed.');
            abort_unless(filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN), 401, 'Your Google email address must be verified.');
            abort_unless(Str::lower((string) $claims['email']) === Str::lower($data['email']), 422, 'Use the same verified Google email in your rider application.');
        }

        $provider = DB::table('logistics_providers')
            ->where('id', $data['logistics_provider_id'])
            ->where('status', 'approved')
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages([
                'logistics_provider_id' => 'Please choose an approved logistics hub.',
            ]);
        }

        $paths = [];
        foreach (['driver_license', 'or_document', 'cr_document'] as $field) {
            $paths[$field] = $request->file($field)->store('riders/applications', 'local');
        }

        DB::transaction(function () use ($data, $provider, $paths): void {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'phone' => trim($data['phone']),
                'date_of_birth' => $data['birthday'],
                'is_suspended' => false,
            ]);

            DB::table('logistics_rider_applications')->insert([
                'user_id' => $user->id,
                'logistics_provider_id' => $provider->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'sex' => $data['sex'] ?? null,
                'date_of_birth' => $data['birthday'],
                'region' => $data['region'],
                'province' => $data['province'],
                'municipality' => $data['municipality'],
                'municipality_psgc_code' => $data['municipality_psgc_code'],
                'postal_code' => $data['postal_code'],
                'barangay' => $data['barangay'],
                'street' => $data['street'],
                'house_unit' => $data['house_unit'] ?? null,
                'vehicle_type' => $data['vehicle_type'],
                'job_type' => $data['job_type'],
                'plate_no' => $data['plate_number'],
                'license_no' => $data['license_number'],
                'document_paths' => json_encode($paths),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Your rider application was sent to the assigned logistics provider for review.',
        ], 201);
    }

    public function logisticsHubs(): JsonResponse
    {
        $hubs = DB::table('logistics_providers')
            ->where('status', 'approved')
            ->select('id', 'name', 'municipality_name', 'province_name')
            ->orderBy('name')
            ->get();

        return response()->json(['logistics_hubs' => $hubs]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', Str::lower(trim($data['email'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid rider email or password.']);
        }

        $rider = DB::table('riders')->where('user_id', $user->id)->first();
        if (! $rider) {
            $pending = DB::table('logistics_rider_applications')->where('user_id', $user->id)->value('status');
            return response()->json(['message' => $pending === 'pending' ? 'Your rider application is still pending logistics approval.' : 'This account is not an approved rider account.'], 403);
        }
        if ($user->is_suspended || $rider->management_status === 'Suspended'
            || ! DB::table('logistics_providers')->where('id', $rider->logistics_provider_id)->where('status', 'approved')->exists()) {
            return response()->json(['message' => 'This rider account is currently unavailable.'], 403);
        }

        DB::table('rider_api_tokens')->where('user_id', $user->id)->delete();
        $plainToken = Str::random(80);
        DB::table('rider_api_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['token' => $plainToken, 'rider' => $this->profile($rider)]);
    }

    public function me(Request $request)
    {
        return response()->json(['rider' => $this->profile($this->rider($request))]);
    }

    public function updateProfile(Request $request)
    {
        $rider = $this->rider($request);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($rider->user_id)],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'birthday' => ['sometimes', 'required', 'date', 'before:today'],
            'sex' => ['sometimes', 'nullable', Rule::in(['Male', 'Female', 'Other'])],
            'region' => ['sometimes', 'nullable', 'string', 'max:150'],
            'province' => ['sometimes', 'nullable', 'string', 'max:150'],
            'municipality' => ['sometimes', 'nullable', 'string', 'max:150'],
            'postal_code' => ['sometimes', 'required', 'regex:/^[0-9]{4}$/'],
            'barangay' => ['sometimes', 'nullable', 'string', 'max:150'],
            'street' => ['sometimes', 'required', 'string', 'max:150'],
            'house_unit' => ['nullable', 'string', 'max:100'],
            'license_number' => ['sometimes', 'required', 'string', 'max:100'],
            'plate_number' => ['sometimes', 'required', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($data, $rider): void {
            $userUpdates = array_intersect_key($data, array_flip(['name', 'email', 'phone']));
            if (array_key_exists('birthday', $data)) {
                $userUpdates['date_of_birth'] = $data['birthday'];
            }
            if ($userUpdates !== []) {
                DB::table('users')->where('id', $rider->user_id)->update([...$userUpdates, 'updated_at' => now()]);
            }
            $riderUpdates = array_intersect_key($data, array_flip([
                'region', 'province', 'municipality', 'postal_code', 'barangay', 'street', 'house_unit', 'sex',
            ]));
        if (array_key_exists('sex', $data)) {
            $riderUpdates['sex'] = $data['sex'];
        }
            foreach (['email', 'phone'] as $field) {
                if (array_key_exists($field, $data)) {
                    $riderUpdates[$field] = $data[$field];
                }
            }
            if (array_key_exists('name', $data)) {
                $riderUpdates['name'] = trim($data['name']);
            }
            if (array_key_exists('birthday', $data)) {
                $riderUpdates['date_of_birth'] = $data['birthday'];
            }
            if (array_key_exists('license_number', $data)) {
                $riderUpdates['license_no'] = $data['license_number'];
            }
            if (array_key_exists('plate_number', $data)) {
                $riderUpdates['plate_no'] = $data['plate_number'];
            }
            if ($riderUpdates !== []) {
                DB::table('riders')->where('id', $rider->id)->update([...$riderUpdates, 'updated_at' => now()]);
            }
        });

        $updatedRider = DB::table('riders')->where('id', $rider->id)->first();

        return response()->json(['rider' => $this->profile($updatedRider)]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $rider = $this->rider($request);
        $user = User::findOrFail($rider->user_id);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['success' => true]);
    }

    public function availability(Request $request)
    {
        $data = $request->validate(['available' => ['required', 'boolean']]);
        $rider = $this->rider($request);
        DB::table('riders')->where('id', $rider->id)->update([
            'is_active' => $data['available'],
            'management_status' => $data['available'] ? 'Available' : 'Offline',
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function shipments(Request $request)
    {
        $rider = $this->rider($request);
        if (! app(RiderJobPolicy::class)->canDeliver($rider)) {
            return response()->json(['shipments' => []]);
        }
        $shipments = DB::table('shipments as s')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->join('users as buyer', 'buyer.id', '=', 'o.buyer_id')
            ->leftJoin('sellers as store', 'store.id', '=', 'so.seller_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'store.user_id')
            ->where('s.rider_id', $rider->id)
            ->where('s.logistics_provider_id', $rider->logistics_provider_id)
            ->select('s.*', 'so.status as seller_order_status', 'o.reference as order_reference', 'o.shipping_address', 'o.payment_method', 'buyer.name as buyer_name', 'buyer.phone as buyer_phone', 'seller.name as seller_name')
            ->selectSub(
                DB::table('delivery_events')
                    ->selectRaw('1')
                    ->whereColumn('delivery_events.shipment_id', 's.id')
                    ->where('delivery_events.status', 'picked_up')
                    ->limit(1),
                'pickup_confirmed'
            )
            ->selectSub(
                DB::table('delivery_events')
                    ->select('occurred_at')
                    ->whereColumn('delivery_events.shipment_id', 's.id')
                    ->where('delivery_events.status', 'picked_up')
                    ->orderBy('occurred_at')
                    ->limit(1),
                'picked_up_at'
            )
            ->latest('s.updated_at')->get()->map(function ($shipment) {
                $status = $shipment->status;
                if (
                    in_array($status, ['in_transit', 'out_for_delivery'], true)
                    && $shipment->pickup_confirmed === null
                ) {
                    $status = 'dispatched';
                } elseif ($status === 'delivered' && $shipment->seller_order_status !== 'completed') {
                    $status = 'awaiting_buyer_confirmation';
                }

                return [
                'id' => $shipment->id,
                'tracking_code' => $shipment->tracking_code,
                'status' => $status,
                'order_reference' => $shipment->order_reference,
                'buyer_name' => (json_decode($shipment->shipping_address, true) ?: [])['recipient'] ?? $shipment->buyer_name,
                'buyer_phone' => (json_decode($shipment->shipping_address, true) ?: [])['phone'] ?? $shipment->buyer_phone,
                'seller_name' => $shipment->seller_name,
                'shipping_address' => json_decode($shipment->shipping_address, true) ?: $shipment->shipping_address,
                'cod_amount_minor' => $shipment->cod_amount_minor,
                'fee_minor' => $shipment->fee_minor,
                'payment_method' => $shipment->payment_method,
                'updated_at' => $shipment->updated_at,
                'picked_up_at' => $shipment->picked_up_at,
                'attempts' => $shipment->attempts,
                'received_by' => $shipment->received_by,
                'proof_image_url' => filled($shipment->proof_path)
                    ? '/rider/shipments/'.$shipment->id.'/proof'
                    : null,
                ];
            });

        return response()->json(['shipments' => $shipments]);
    }

    public function uploadShipmentProof(Request $request, int $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'proof' => ['required', 'image', 'max:10240'],
            'received_by' => ['required', 'string', 'max:255'],
        ]);
        $rider = $this->rider($request);
        $shipment = DB::table('shipments')
            ->where('id', $shipmentId)
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $rider->logistics_provider_id)
            ->first();
        abort_unless($shipment, 404, 'Shipment not found.');
        abort_unless(in_array($shipment->status, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered'], true), 422, 'Proof can only be saved for an active delivery.');

        $path = $request->file('proof')->store('delivery-proofs/'.$rider->id, 'local');
        if ($shipment->proof_path && Storage::disk('local')->exists($shipment->proof_path)) {
            Storage::disk('local')->delete($shipment->proof_path);
        }
        DB::table('shipments')->where('id', $shipment->id)->update([
            'proof_path' => $path,
            'received_by' => trim($data['received_by']),
            'updated_at' => now(),
        ]);

        return response()->json([
            'proof_image_url' => '/rider/shipments/'.$shipment->id.'/proof',
            'received_by' => trim($data['received_by']),
        ]);
    }

    public function shipmentProof(Request $request, int $shipmentId): StreamedResponse
    {
        $rider = $this->rider($request);
        $shipment = DB::table('shipments')
            ->where('id', $shipmentId)
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $rider->logistics_provider_id)
            ->first();
        abort_unless($shipment && $shipment->proof_path && Storage::disk('local')->exists($shipment->proof_path), 404, 'Proof of delivery not found.');

        return Storage::disk('local')->response($shipment->proof_path);
    }

    public function payroll(Request $request, RiderPayrollService $service): JsonResponse
    {
        $rider = $this->rider($request);
        $providerId = (int) $rider->logistics_provider_id;
        $today = now('Asia/Manila');
        $periodStart = $today->copy()->startOfMonth()->toDateString();
        $periodEnd = $today->toDateString();

        $preview = $service->calculatePayroll(
            (int) $rider->id,
            $providerId,
            $periodStart,
            $periodEnd,
        );

        $payrolls = DB::table('rider_payrolls')
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $providerId)
            ->where('status', '!=', 'void')
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->limit(24)
            ->get([
                'id',
                'period_start',
                'period_end',
                'paid_days',
                'successful_deliveries',
                'base_salary_minor',
                'commission_minor',
                'gross_pay_minor',
                'status',
                'paid_at',
                'payment_reference',
                'remarks',
            ])
            ->map(static fn (object $payroll): array => [
                'id' => (int) $payroll->id,
                'period_start' => $payroll->period_start,
                'period_end' => $payroll->period_end,
                'paid_days' => (int) $payroll->paid_days,
                'successful_deliveries' => (int) $payroll->successful_deliveries,
                'base_salary_minor' => (int) $payroll->base_salary_minor,
                'commission_minor' => (int) $payroll->commission_minor,
                'gross_pay_minor' => (int) $payroll->gross_pay_minor,
                'status' => $payroll->status,
                'paid_at' => $payroll->paid_at,
                'payment_reference' => $payroll->payment_reference,
                'remarks' => $payroll->remarks,
            ]);

        return response()->json([
            'provider_name' => DB::table('logistics_providers')
                ->where('id', $providerId)
                ->value('name'),
            'current_period' => [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'paid_days' => $preview['paid_days'],
                'successful_deliveries' => $preview['successful_deliveries'],
                'daily_rate_minor' => $preview['daily_rate_minor'],
                'delivery_rate_minor' => $preview['delivery_rate_minor'],
                'shipping_fee_minor' => LogisticsWalletService::SHIPPING_FEE_MINOR,
                'logistics_share_minor' => LogisticsWalletService::LOGISTICS_SHARE_MINOR,
                'base_salary_minor' => $preview['base_salary_minor'],
                'commission_minor' => $preview['commission_minor'],
                'gross_pay_minor' => $preview['gross_pay_minor'],
            ],
            'payrolls' => $payrolls,
        ]);
    }

    public function syncPayrollDeliveries(
        Request $request,
        RiderPayrollService $service,
    ): JsonResponse {
        $rider = $this->rider($request);
        $credited = $service->syncDeliveredShipments(
            (int) $rider->id,
            (int) $rider->logistics_provider_id,
        );

        return response()->json([
            'success' => true,
            'credited_deliveries' => $credited,
        ]);
    }

    public function remittances(Request $request, RiderRemittanceService $service): JsonResponse
    {
        $rider = $this->rider($request);
        $providerId = (int) $rider->logistics_provider_id;
        $providerName = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->value('name') ?? 'Logistics Hub';
        $balance = $service->getRiderBalance((int) $rider->id, $providerId);
        $pendingMinor = (int) DB::table('rider_remittances')
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $providerId)
            ->where('status', 'pending_verification')
            ->sum('submitted_amount_minor');

        $history = DB::table('rider_remittances')
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $providerId)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id',
                'remittance_number',
                'payment_method',
                'payment_provider',
                'transaction_reference',
                'submitted_amount_minor',
                'receipt_path',
                'status',
                'submitted_at',
                'verified_at',
                'verification_remarks',
                'rejection_reason',
            ])
            ->map(function (object $remittance): array {
                return [
                    'id' => (int) $remittance->id,
                    'remittance_number' => $remittance->remittance_number,
                    'payment_method' => $remittance->payment_method,
                    'method_label' => $remittance->payment_method === 'other'
                        && str_starts_with($remittance->transaction_reference, 'HANDOFF-')
                        ? 'Cash Handoff'
                        : strtoupper(str_replace('_', ' ', $remittance->payment_method)),
                    'payment_provider' => $remittance->payment_provider,
                    'transaction_reference' => $remittance->transaction_reference,
                    'amount_minor' => (int) $remittance->submitted_amount_minor,
                    'has_receipt' => $remittance->receipt_path !== null,
                    'status' => $remittance->status,
                    'submitted_at' => $remittance->submitted_at,
                    'verified_at' => $remittance->verified_at,
                    'verification_remarks' => $remittance->verification_remarks,
                    'rejection_reason' => $remittance->rejection_reason,
                ];
            });

        return response()->json([
            'balance' => $balance,
            'provider_name' => $providerName,
            'pending_minor' => $pendingMinor,
            'available_to_submit_minor' => $pendingMinor > 0
                ? 0
                : (int) $balance['outstanding_minor'],
            'history' => $history,
        ]);
    }

    public function syncCodRemittances(Request $request, RiderRemittanceService $service): JsonResponse
    {
        $rider = $this->rider($request);
        $shipmentIds = DB::table('shipments')
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $rider->logistics_provider_id)
            ->where('status', 'delivered')
            ->where('cod_collected', true)
            ->where('cod_amount_minor', '>', 0)
            ->orderBy('id')
            ->pluck('id');

        $created = 0;
        foreach ($shipmentIds as $shipmentId) {
            if ($service->createCodObligation((int) $shipmentId)) {
                $created++;
            }
        }

        return response()->json(['created_obligations' => $created]);
    }

    public function riderNotifications(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $user = User::query()->findOrFail($rider->user_id);
        $notifications = $user->unreadNotifications()
            ->latest()
            ->limit(30)
            ->get();

        $response = $notifications->map(function (object $notification): array {
            $data = is_array($notification->data)
                ? $notification->data
                : (array) $notification->data;

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? 'Lumear update',
                'message' => $data['body'] ?? $data['message'] ?? '',
                'created_at' => $notification->created_at,
            ];
        });

        if ($notifications->isNotEmpty()) {
            $user->unreadNotifications()
                ->whereIn('id', $notifications->pluck('id'))
                ->update(['read_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['notifications' => $response]);
    }

    public function submitRemittance(Request $request, RiderRemittanceService $service): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        $rider = $this->rider($request);
        $providerId = (int) $rider->logistics_provider_id;
        $providerName = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->value('name');
        abort_unless($providerName, 403, 'Your Logistics hub could not be found.');

        $transactionReference = 'HANDOFF-' . strtoupper((string) Str::ulid());
        $id = $service->submitCashHandoff(
            (int) $rider->id,
            $providerId,
            'other',
            $transactionReference,
            (int) $data['amount_minor'],
            null,
            $providerName,
        );

        $remittance = DB::table('rider_remittances')
            ->where('id', $id)
            ->first(['remittance_number']);

        return response()->json([
            'message' => 'Cash handoff submitted. Waiting for your hub to confirm receipt.',
            'remittance_id' => $id,
            'remittance_number' => $remittance?->remittance_number,
            'transaction_reference' => $transactionReference,
            'provider_name' => $providerName,
        ], 201);
    }

    public function remittanceReceipt(Request $request, int $remittanceId)
    {
        $rider = $this->rider($request);
        $remittance = DB::table('rider_remittances')
            ->where('id', $remittanceId)
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $rider->logistics_provider_id)
            ->first();

        abort_unless($remittance && $remittance->receipt_path, 404);
        abort_if(str_contains($remittance->receipt_path, '..'), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($remittance->receipt_path), 404);

        return $disk->download(
            $remittance->receipt_path,
            'remittance-receipt-' . $remittanceId . '.' . pathinfo($remittance->receipt_path, PATHINFO_EXTENSION),
        );
    }

    public function confirmShipmentPickupScan(Request $request, int $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'waybill' => ['required', 'string', 'max:180'],
        ]);
        $waybillPayload = trim($data['waybill']);
        $decodedPayload = json_decode($waybillPayload, true);
        if (is_string($decodedPayload)) {
            $waybillPayload = trim($decodedPayload);
        }
        abort_unless(
            preg_match('/^LUMEAR-WAYBILL\s*:\s*/i', $waybillPayload) === 1,
            422,
            'Scan the QR code printed on the Lumear seller waybill.'
        );
        $trackingCode = strtoupper((string) preg_replace(
            '/^LUMEAR-WAYBILL\s*:\s*/i',
            '',
            $waybillPayload
        ));
        abort_unless(
            is_string($trackingCode)
                && preg_match('/^LM-TRK-[A-Z0-9]{10}$/', $trackingCode) === 1,
            422,
            'Scan a valid Lumear seller waybill QR code.'
        );

        $rider = $this->rider($request);
        abort_unless(
            app(RiderJobPolicy::class)->canDeliver($rider),
            403,
            'Your job is not customer delivery.'
        );

        DB::transaction(function () use ($rider, $request, $shipmentId, $trackingCode): void {
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->where('rider_id', $rider->id)
                ->where('logistics_provider_id', $rider->logistics_provider_id)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404, 'Assigned parcel not found.');
            abort_unless(
                hash_equals(strtoupper((string) $shipment->tracking_code), $trackingCode),
                422,
                'This waybill belongs to a different parcel.'
            );

            $pickupAlreadyScanned = DB::table('delivery_events')
                ->where('shipment_id', $shipment->id)
                ->where('status', 'picked_up')
                ->exists();
            abort_unless(
                in_array($shipment->status, ['assigned', 'in_transit'], true)
                    && ! $pickupAlreadyScanned,
                422,
                'This parcel was already scanned or is no longer waiting for hub handover.'
            );

            $sellerOrder = SellerOrder::with('order.buyer', 'seller.user')
                ->lockForUpdate()
                ->findOrFail($shipment->seller_order_id);
            abort_if(
                in_array($sellerOrder->status, ['completed', 'cancelled'], true),
                422,
                'This order is already closed.'
            );

            $scannedAt = now();
            DB::table('shipments')->where('id', $shipment->id)->update([
                'status' => 'picked_up',
                'updated_at' => $scannedAt,
            ]);
            DB::table('delivery_events')->insert([
                'shipment_id' => $shipment->id,
                'status' => 'picked_up',
                'attempt' => max(1, (int) $shipment->attempts),
                'user_id' => $request->attributes->get('rider_api_user_id'),
                'note' => 'Rider scanned the seller waybill at the hub and accepted custody.',
                'occurred_at' => $scannedAt,
                'created_at' => $scannedAt,
                'updated_at' => $scannedAt,
            ]);

            $sellerOrder->update([
                'status' => 'shipped',
                'delivered_at' => $sellerOrder->delivered_at,
            ]);
            OrderTimeline::create([
                'order_id' => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'actor_id' => $rider->user_id,
                'status' => 'shipped',
                'event' => 'rider_picked_up',
                'note' => 'Rider scanned the seller waybill and accepted custody of the parcel.',
                'metadata' => [
                    'shipment_id' => $shipment->id,
                    'tracking_code' => $shipment->tracking_code,
                ],
            ]);

            $notification = new MarketplaceAlert(
                'Your parcel is with the rider',
                "The rider scanned waybill {$shipment->tracking_code} at the hub and confirmed the parcel is in their possession.",
                route('orders')
            );
            $sellerOrder->order->buyer?->notify($notification);
            $sellerOrder->seller?->user?->notify(new MarketplaceAlert(
                'Delivery update',
                "Waybill {$shipment->tracking_code} was scanned by the assigned rider at the hub.",
                route('seller.orders')
            ));
        }, 3);

        return response()->json([
            'success' => true,
            'status' => 'picked_up',
            'message' => 'Pickup confirmed. This parcel is now in your care.',
        ]);
    }

    public function updateShipment(
        Request $request,
        int $shipmentId,
        RiderRemittanceService $remittanceService,
        RiderPayrollService $payrollService,
        LogisticsWalletService $logisticsWalletService,
    )
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_transit', 'out_for_delivery', 'delivered', 'failed'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $rider = $this->rider($request);
        abort_unless(app(RiderJobPolicy::class)->canDeliver($rider), 403, 'Your job is not customer delivery.');

        $result = DB::transaction(function () use ($data, $rider, $request, $shipmentId, $remittanceService, $payrollService, $logisticsWalletService): array {
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->where('rider_id', $rider->id)
                ->where('logistics_provider_id', $rider->logistics_provider_id)
                ->lockForUpdate()
                ->first();
            abort_unless($shipment, 404, 'Shipment not found.');

            if (in_array(
                $data['status'],
                ['in_transit', 'out_for_delivery', 'delivered', 'failed'],
                true
            )) {
                $pickupWasConfirmed = DB::table('delivery_events')
                    ->where('shipment_id', $shipment->id)
                    ->where('status', 'picked_up')
                    ->exists();
                abort_unless(
                    $pickupWasConfirmed,
                    422,
                    'Scan the seller waybill at the hub before starting delivery.'
                );
            }

            $allowed = [
                'assigned' => [],
                'picked_up' => ['in_transit', 'out_for_delivery'],
                'in_transit' => ['out_for_delivery', 'failed'],
                'out_for_delivery' => ['delivered', 'failed'],
            ];
            if ($shipment->status === $data['status']) {
                if ($data['status'] === 'delivered') {
                    $payrollService->creditDeliveredShipment((int) $shipment->id);
                    $logisticsWalletService->creditDeliveredShipment((int) $shipment->id);
                    if ((int) $shipment->cod_amount_minor > 0) {
                        $remittanceService->createCodObligation((int) $shipment->id);
                    }
                }
                return [
                    'attempt' => (int) $shipment->attempts,
                    'status' => $shipment->status,
                    'return_ready' => $shipment->status === 'returned',
                ];
            }
            abort_unless(
                in_array($data['status'], $allowed[$shipment->status] ?? [], true),
                422,
                'That shipment status cannot be applied yet.'
            );

            $isFailedAttempt = $data['status'] === 'failed';
            $attempt = $isFailedAttempt
                ? (int) $shipment->attempts + 1
                : max(1, (int) $shipment->attempts);
            $isReturnReady = $isFailedAttempt && $attempt >= 4;
            $shipmentStatus = $isFailedAttempt
                ? ($isReturnReady ? 'returned' : $shipment->status)
                : $data['status'];

            DB::table('shipments')->where('id', $shipment->id)->update([
                'status' => $shipmentStatus,
                'attempts' => $attempt,
                'cod_collected' => $data['status'] === 'delivered' ? true : $shipment->cod_collected,
                'updated_at' => now(),
            ]);
            if ($data['status'] === 'delivered' || $isReturnReady) {
                DB::table('rider_tasks')
                    ->where('shipment_id', $shipment->id)
                    ->where('rider_id', $rider->id)
                    ->whereNotIn(
                        'status',
                        RiderTaskAssignmentService::TERMINAL_STATUSES
                    )
                    ->update([
                        'status' => $isReturnReady ? 'cancelled' : 'delivered',
                        'updated_at' => now(),
                    ]);
            }
            if ($data['status'] === 'delivered') {
                $payrollService->creditDeliveredShipment((int) $shipment->id);
                $logisticsWalletService->creditDeliveredShipment((int) $shipment->id);
            }
            DB::table('delivery_events')->updateOrInsert([
                'shipment_id' => $shipment->id,
                'status' => $isFailedAttempt
                    ? ($isReturnReady ? 'return_to_seller' : 'delivery_failed_attempt')
                    : $data['status'],
                'attempt' => $attempt,
            ], [
                'user_id' => $request->attributes->get('rider_api_user_id'),
                'note' => $isReturnReady
                    ? 'Fourth delivery attempt failed. Parcel is ready to return to seller. '.($data['note'] ?? '')
                    : ($data['note'] ?? null),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sellerOrder = SellerOrder::with('order.buyer', 'seller.user')->lockForUpdate()->findOrFail($shipment->seller_order_id);
            abort_if(in_array($sellerOrder->status, ['completed', 'cancelled'], true), 422, 'This order is already closed.');
            $sellerOrder->update([
                'status' => $data['status'] === 'delivered' ? 'delivered' : 'shipped',
                'delivered_at' => $data['status'] === 'delivered' ? now() : $sellerOrder->delivered_at,
            ]);
            if ($data['status'] === 'delivered' && (int) $shipment->cod_amount_minor > 0) {
                $remittanceService->createCodObligation((int) $shipment->id);
            }
            OrderTimeline::create([
                'order_id' => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'actor_id' => $rider->user_id,
                'status' => $sellerOrder->status,
                'event' => 'rider_'.$data['status'],
                'note' => $data['note'] ?? 'Rider reported '.str_replace('_', ' ', $data['status']).'.',
                'metadata' => ['shipment_id' => $shipment->id, 'tracking_code' => $shipment->tracking_code],
            ]);
            $message = $isReturnReady
                ? "Delivery attempt 4 of 4 was unsuccessful. Parcel {$shipment->tracking_code} is being returned to the seller."
                : ($isFailedAttempt
                    ? "Delivery attempt {$attempt} of 4 was unsuccessful for parcel {$shipment->tracking_code}. Another attempt may be scheduled."
                    : $sellerOrder->order->reference.': '.str_replace('_', ' ', $data['status']).'. Tracking: '.$shipment->tracking_code);
            if ($data['status'] === 'delivered') {
                $message = "The rider marked order {$sellerOrder->order->reference} as delivered and submitted the delivery confirmation. Please open My Orders and confirm that you received the parcel.";
            }
            $notificationTitle = $data['status'] === 'picked_up'
                ? 'Your parcel is with the rider'
                : 'Delivery update';
            if ($data['status'] === 'picked_up') {
                $message = "The rider scanned waybill {$shipment->tracking_code} at the hub and confirmed the parcel is in their possession.";
            }
            $sellerOrder->order->buyer->notify(new MarketplaceAlert($notificationTitle, $message, route('orders')));
            $sellerOrder->seller->user->notify(new MarketplaceAlert('Delivery update', $message, route('seller.orders')));
            return [
                'attempt' => $attempt,
                'status' => $shipmentStatus,
                'return_ready' => $isReturnReady,
            ];
        }, 3);

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function reportShipmentDelay(Request $request, int $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'proposed_for' => ['required', 'date', 'after:now'],
        ]);

        $rider = $this->rider($request);
        abort_unless(
            app(RiderJobPolicy::class)->canDeliver($rider),
            403,
            'Your rider account is not assigned to customer deliveries.'
        );

        return DB::transaction(function () use ($rider, $shipmentId, $data): JsonResponse {
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->where('rider_id', $rider->id)
                ->where('logistics_provider_id', $rider->logistics_provider_id)
                ->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery'])
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404, 'Active delivery not found.');

            $latestEvent = DB::table('delivery_events')
                ->where('shipment_id', $shipment->id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first();

            abort_if(
                $latestEvent?->status === 'delivery_delay_reported',
                422,
                'Your delay report is already waiting for Logistics to review.'
            );

            $proposedFor = \Illuminate\Support\Carbon::parse(
                $data['proposed_for']
            );
            $proposedLabel = $proposedFor->format('M j, Y g:i A');
            $reason = trim($data['reason']);
            $note = 'Rider reported a delivery delay. Reason: '
                . $reason
                . '. Proposed new delivery: '
                . $proposedLabel
                . '.';
            $attempt = max(
                max(1, (int) ($shipment->attempts ?? 0)),
                (int) DB::table('delivery_events')
                    ->where('shipment_id', $shipment->id)
                    ->where('status', 'delivery_delay_reported')
                    ->max('attempt') + 1,
            );

            DB::table('delivery_events')->insert([
                'shipment_id' => $shipment->id,
                'status' => 'delivery_delay_reported',
                'attempt' => $attempt,
                'user_id' => $rider->user_id,
                'note' => $note,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $riderName = DB::table('users')
                ->where('id', $rider->user_id)
                ->value('name') ?? 'Assigned rider';

            LogisticsNotification::create(
                (int) $rider->logistics_provider_id,
                'delivery_delay_reported',
                'Rider Reported a Delivery Delay',
                $riderName
                    . ' reported a delay for waybill '
                    . $shipment->tracking_code
                    . '. Proposed delivery: '
                    . $proposedLabel
                    . '. Review it in Delivery Monitoring.',
                null,
            );

            return response()->json([
                'success' => true,
                'status' => 'Delay Reported',
                'message' => 'Your delay report was sent to Logistics for review.',
            ]);
        }, 3);
    }

    public function logout(Request $request)
    {
        DB::table('rider_api_tokens')->where('id', $request->attributes->get('rider_api_token_id'))->delete();
        return response()->json(['success' => true]);
    }
}

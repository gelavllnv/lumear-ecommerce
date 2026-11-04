<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RiderMobileApiController extends Controller
{
    private function rider(Request $request): object
    {
        return $request->attributes->get('rider_api_rider');
    }

    private function profile(object $rider): array
    {
        $user = DB::table('users')->where('id', $rider->user_id)->first();

        return [
            'id' => $rider->id,
            'rider_id' => 'RDR-' . str_pad((string) $rider->id, 3, '0', STR_PAD_LEFT),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'birthday' => $rider->date_of_birth,
            'sex' => $rider->sex ?? '',
            'region' => $rider->region ?? '',
            'province' => $rider->province ?? '',
            'municipality' => $rider->municipality ?? '',
            'barangay' => $rider->barangay ?? '',
            'street' => $rider->street ?? '',
            'house_unit' => $rider->house_unit ?? '',
            'vehicle_type' => $rider->vehicle_type,
            'job_type' => $rider->job_type ?? '',
            'license_number' => $rider->license_no ?? '',
            'plate_number' => $rider->plate_no ?? '',
            'license_expiry' => $rider->license_expires_at,
            'status' => $rider->management_status ?? 'Offline',
            'is_available' => (bool) $rider->is_active,
            'provider' => DB::table('logistics_providers')->where('id', $rider->logistics_provider_id)->value('name'),
        ];
    }

    public function register(Request $request)
    {
        $adultCutoff = now()->subYears(18)->toDateString();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'max:30'],
            'sex' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'birthday' => ['required', 'date', "before_or_equal:$adultCutoff"],
            'region' => ['required', 'string', 'max:150'],
            'province' => ['required', 'string', 'max:150'],
            'municipality' => ['required', 'string', 'max:150'],
            'municipality_psgc_code' => ['required', 'string', 'max:20'],
            'barangay' => ['required', 'string', 'max:150'],
            'street' => ['required', 'string', 'max:150'],
            'house_unit' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['required', Rule::in(['Motorcycle', 'Tricycle', 'Van', 'Truck'])],
            'job_type' => ['required', Rule::in(['Delivery', 'Seller Pickup', 'Long-distance Handover'])],
            'license_number' => ['required', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:100'],
            'driver_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'or_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'cr_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'birthday.before_or_equal' => 'Riders must be at least 18 years old.',
        ]);

        $provider = DB::table('logistics_providers')
            ->where('status', 'approved')
            ->where('municipality_psgc_code', $data['municipality_psgc_code'])
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages([
                'municipality' => 'There is no approved Lumear logistics provider for this municipality yet.',
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
        if (! $rider->is_active || $rider->management_status === 'Suspended') {
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
        $data = $request->validate([
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'street' => ['sometimes', 'required', 'string', 'max:150'],
            'house_unit' => ['nullable', 'string', 'max:100'],
            'license_number' => ['sometimes', 'required', 'string', 'max:100'],
            'plate_number' => ['sometimes', 'required', 'string', 'max:100'],
        ]);
        $rider = $this->rider($request);

        DB::transaction(function () use ($data, $rider): void {
            if (array_key_exists('phone', $data)) {
                DB::table('users')->where('id', $rider->user_id)->update(['phone' => $data['phone'], 'updated_at' => now()]);
            }
            DB::table('riders')->where('id', $rider->id)->update(array_filter([
                'street' => $data['street'] ?? null,
                'house_unit' => $data['house_unit'] ?? null,
                'license_no' => $data['license_number'] ?? null,
                'plate_no' => $data['plate_number'] ?? null,
                'updated_at' => now(),
            ], static fn ($value) => $value !== null));
        });

        return $this->me($request);
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
        $shipments = DB::table('shipments as s')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->join('users as buyer', 'buyer.id', '=', 'o.buyer_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'so.seller_id')
            ->where('s.rider_id', $rider->id)
            ->select('s.*', 'o.reference as order_reference', 'o.shipping_address', 'buyer.name as buyer_name', 'buyer.phone as buyer_phone', 'seller.name as seller_name')
            ->latest('s.updated_at')->get()->map(fn ($shipment) => [
                'id' => $shipment->id,
                'tracking_code' => $shipment->tracking_code,
                'status' => $shipment->status,
                'order_reference' => $shipment->order_reference,
                'buyer_name' => $shipment->buyer_name,
                'buyer_phone' => $shipment->buyer_phone,
                'seller_name' => $shipment->seller_name,
                'shipping_address' => json_decode($shipment->shipping_address, true) ?: $shipment->shipping_address,
                'cod_amount_minor' => $shipment->cod_amount_minor,
                'updated_at' => $shipment->updated_at,
            ]);

        return response()->json(['shipments' => $shipments]);
    }

    public function updateShipment(Request $request, int $shipmentId)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $rider = $this->rider($request);

        DB::transaction(function () use ($data, $rider, $request, $shipmentId): void {
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->where('rider_id', $rider->id)
                ->lockForUpdate()
                ->first();
            abort_unless($shipment, 404, 'Shipment not found.');

            $allowed = [
                'assigned' => ['picked_up'],
                'picked_up' => ['in_transit'],
                'in_transit' => ['out_for_delivery', 'failed'],
                'out_for_delivery' => ['delivered', 'failed'],
            ];
            abort_unless(
                in_array($data['status'], $allowed[$shipment->status] ?? [], true),
                422,
                'That shipment status cannot be applied yet.'
            );

            DB::table('shipments')->where('id', $shipment->id)->update([
                'status' => $data['status'],
                'cod_collected' => $data['status'] === 'delivered' ? true : $shipment->cod_collected,
                'updated_at' => now(),
            ]);
            DB::table('delivery_events')->updateOrInsert([
                'shipment_id' => $shipment->id,
                'status' => $data['status'],
                'attempt' => max(1, (int) $shipment->attempts),
            ], [
                'user_id' => $request->attributes->get('rider_api_user_id'),
                'note' => $data['note'] ?? null,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function logout(Request $request)
    {
        DB::table('rider_api_tokens')->where('id', $request->attributes->get('rider_api_token_id'))->delete();
        return response()->json(['success' => true]);
    }
}

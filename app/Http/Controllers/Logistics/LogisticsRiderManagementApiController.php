<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Support\LogisticsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsRiderManagementApiController extends Controller
{
    private function providerId(): int
    {
        $id = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->value('id');

        abort_unless($id, 403, 'Approved logistics account required.');

        return (int) $id;
    }

    private function providerArea(int $providerId): string
    {
        $provider = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();

        return implode(', ', array_filter([
            $provider->municipality_name ?? '',
            $provider->province_name ?? '',
        ]));
    }

    private function applicationData(int $providerId)
    {
        $area = $this->providerArea($providerId);

        return DB::table('logistics_rider_applications')
            ->where('logistics_provider_id', $providerId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($a) => [
                'id' => 'APP-' . str_pad($a->id, 3, '0', STR_PAD_LEFT),
                'firstName' => explode(' ', trim($a->name), 2)[0],
                'lastName' => explode(' ', trim($a->name), 2)[1] ?? '',
                'email' => $a->email,
                'phone' => '',
                'birthday' => '',
                'sex' => '',
                'municipality' => $area,
                'address' => $area,
                'vehicle' => $a->vehicle_type ?? 'Motorcycle',
                'plate' => $a->plate_no ?? '',
                'license' => '',
                'submitted' => substr($a->created_at, 0, 10),
                'status' => ucfirst($a->status),
                'remarks' => $a->remarks ?? '',
            ]);
    }

    private function riderData(int $providerId)
    {
        $area = $this->providerArea($providerId);

        return DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->select(
                'r.*',
                'u.name',
                'u.email',
                'u.phone'
            )
            ->orderByDesc('r.id')
            ->get()
            ->map(fn ($r) => [
                'id' => 'RDR-' . str_pad($r->id, 3, '0', STR_PAD_LEFT),
                'applicationId' => null,
                'name' => $r->name,
                'email' => $r->email,
                'phone' => $r->phone ?? '',
                'municipality' => $area,
                'address' => $area,
                'vehicle' => $r->vehicle_type ?? 'Motorcycle',
                'plate' => $r->plate_no ?? '',
                'license' => '',
                'status' => $r->management_status === 'Suspended'
                    ? 'Suspended'
                    : ($r->is_active ? 'Available' : 'Offline'),
                'remarks' => $r->management_remarks ?? '',
            ]);
    }

    public function index()
    {
        $providerId = $this->providerId();

        return response()->json([
            'success' => true,
            'applications' => $this->applicationData($providerId),
            'riders' => $this->riderData($providerId),
        ]);
    }

    public function decide(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:Approved,Rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        if (
            $data['status'] === 'Rejected' &&
            !filled($data['remarks'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'remarks' => 'A rejection reason is required.',
            ]);
        }

        $providerId = $this->providerId();

        DB::transaction(function () use ($id, $providerId, $data) {
            $application = DB::table('logistics_rider_applications')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($application, 404, 'Application not found.');

            abort_unless(
                $application->status === 'pending',
                422,
                'Application has already been reviewed.'
            );

            if ($data['status'] === 'Approved') {
                $user = DB::table('users')
                    ->where('email', $application->email)
                    ->first();

                if (!$user) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Applicant must register a user account before approval.',
                    ]);
                }

                $existing = DB::table('riders')
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'This user already has a rider account.',
                    ]);
                }

                DB::table('riders')->insert([
                    'user_id' => $user->id,
                    'logistics_provider_id' => $providerId,
                    'vehicle_type' => $application->vehicle_type,
                    'plate_no' => $application->plate_no,
                    'is_active' => false,
                    'management_status' => 'Offline',
                    'management_remarks' =>
                        'Newly approved rider.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('logistics_rider_applications')
                ->where('id', $id)
                ->update([
                    'status' => strtolower($data['status']),
                    'remarks' => $data['remarks'] ?? '',
                    'updated_at' => now(),
                ]);

            // NEW: Notify Logistics about the decision.
            LogisticsNotification::create(
                $providerId,
                'rider_application',
                'Rider Application ' . $data['status'],
                'Application #' . $application->id
                    . ' for ' . $application->name
                    . ' was ' . strtolower($data['status']) . '.',
                null
            );
        });

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'Approved'
                ? 'Rider approved and added to Rider List.'
                : 'Rider application rejected.',
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:Available,Offline,Suspended',
            'remarks' => 'nullable|string|max:500',
        ]);

        if (
            $data['status'] === 'Suspended' &&
            !filled($data['remarks'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'remarks' => 'A suspension reason is required.',
            ]);
        }

        $providerId = $this->providerId();

        DB::transaction(function () use ($id, $providerId, $data) {
            $rider = DB::table('riders')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->lockForUpdate()
                ->first();

            abort_unless($rider, 404, 'Rider not found.');

            if ($data['status'] === 'Suspended') {
                $activeDeliveries = DB::table('shipments')
                    ->where('rider_id', $id)
                    ->where('logistics_provider_id', $providerId)
                    ->whereIn('status', [
                        'assigned',
                        'picked_up',
                        'in_transit',
                        'out_for_delivery',
                    ])
                    ->exists();

                if ($activeDeliveries) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Reassign active deliveries before suspending this rider.',
                    ]);
                }
            }

            DB::table('riders')
                ->where('id', $id)
                ->update([
                    'is_active' => $data['status'] === 'Available',
                    'management_status' => $data['status'],
                    'management_remarks' => $data['remarks'] ?? '',
                    'updated_at' => now(),
                ]);

            // NEW: Notify Logistics about rider status.
            LogisticsNotification::create(
                $providerId,
                'rider_status',
                'Rider Status Updated',
                'Rider #' . $rider->id
                    . ' status changed to ' . $data['status'] . '.',
                null
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Rider status updated successfully.',
        ]);
    }
}

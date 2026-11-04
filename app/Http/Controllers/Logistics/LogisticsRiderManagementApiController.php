<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
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
                'phone' => $a->phone ?? '',
                'birthday' => $a->date_of_birth ?? '',
                'sex' => $a->sex ?? '',
                'municipality' => $a->municipality ?? $area,
                'address' => implode(', ', array_filter([
                    $a->house_unit ?? '', $a->street ?? '',
                    $a->barangay ?? '', $a->municipality ?? $area,
                ])),
                'vehicle' => $a->vehicle_type ?? 'Motorcycle',
                'plate' => $a->plate_no ?? '',
                'license' => $a->license_no ?? '',
                'jobType' => $a->job_type ?? '',
                'documents' => json_decode($a->document_paths ?? '[]', true),
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
                'municipality' => $r->municipality ?? $area,
                'address' => implode(', ', array_filter([
                    $r->house_unit ?? '', $r->street ?? '',
                    $r->barangay ?? '', $r->municipality ?? $area,
                ])),
                'vehicle' => $r->vehicle_type ?? 'Motorcycle',
                'plate' => $r->plate_no ?? '',
                'license' => $r->license_no ?? '',
                'jobType' => $r->job_type ?? '',
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
                    'job_type' => $application->job_type ?? null,
                    'plate_no' => $application->plate_no,
                    'license_no' => $application->license_no ?? null,
                    'sex' => $application->sex ?? null,
                    'date_of_birth' => $application->date_of_birth ?? null,
                    'region' => $application->region ?? null,
                    'province' => $application->province ?? null,
                    'municipality' => $application->municipality ?? null,
                    'barangay' => $application->barangay ?? null,
                    'street' => $application->street ?? null,
                    'house_unit' => $application->house_unit ?? null,
                    'document_paths' => $application->document_paths ?? null,
                    'is_active' => true,
                    'management_status' => 'Available',
                    'management_remarks' =>
                        'Newly approved rider. Rider can set availability in the Lumear Rider app.',
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
        });

        return response()->json([
            'success' => true,
            'message' => 'Rider status updated successfully.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\RiderTaskAssignmentService;
use App\Support\LogisticsNotification;
use App\Support\RegisteredRiderArea;
use App\Support\RiderJobType;
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

        abort_unless(
            $id,
            403,
            'Approved logistics account required.'
        );

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
            ->map(fn ($application) => [
                'id' => 'APP-'.str_pad(
                    (string) $application->id,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'firstName' => explode(
                    ' ',
                    trim($application->name),
                    2
                )[0],
                'lastName' => explode(
                    ' ',
                    trim($application->name),
                    2
                )[1] ?? '',
                'email' => $application->email,
                'phone' => $application->phone ?? '',
                'birthday' => $application->date_of_birth ?? '',
                'sex' => $application->sex ?? '',
                'municipality' => RegisteredRiderArea::label($application),
                'address' => $this->address($application, $area),
                'vehicle' => $application->vehicle_type ?? 'Motorcycle',
                'jobType' => $application->job_type,
                'plate' => $application->plate_no ?? '',
                'license' => $application->license_number
                    ?? $application->license_no
                    ?? '',
                'submitted' => substr(
                    (string) $application->created_at,
                    0,
                    10
                ),
                'status' => ucfirst($application->status),
                'remarks' => $application->remarks ?? '',
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
                'u.name as account_name',
                'u.email as account_email',
                'u.phone as account_phone'
            )
            ->orderByDesc('r.id')
            ->get()
            ->map(fn ($rider) => [
                'id' => 'RDR-'.str_pad(
                    (string) $rider->id,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'applicationId' => null,
                'name' => $rider->account_name,
                'email' => $rider->account_email,
                'phone' => $rider->account_phone ?? '',
                'municipality' => RegisteredRiderArea::label($rider),
                'address' => $this->address($rider, $area),
                'vehicle' => $rider->vehicle_type ?? 'Motorcycle',
                'jobType' => $rider->job_type,
                'shiftStartTime' => $rider->shift_start_time,
                'shiftEndTime' => $rider->shift_end_time,
                'plate' => $rider->plate_no ?? '',
                'license' => $rider->license_no ?? '',
                'status' => $rider->management_status === 'Suspended'
                    ? 'Suspended'
                    : (
                        RiderJobType::isAvailable($rider)
                            ? 'Available'
                            : 'Offline'
                    ),
                'remarks' => $rider->management_remarks ?? '',
            ]);
    }

    private function address(object $record, string $fallback): string
    {
        $parts = array_filter([
            $record->house_unit ?? '',
            $record->street ?? '',
            $record->barangay ?? '',
            $record->municipality ?? '',
            $record->province ?? '',
            $record->region ?? '',
            $record->postal_code ?? '',
        ], fn ($value) => $value !== null && $value !== '');

        return $parts
            ? implode(', ', $parts)
            : $fallback;
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
            $data['status'] === 'Rejected'
            && ! filled($data['remarks'] ?? null)
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

            abort_unless(
                $application,
                404,
                'Application not found.'
            );

            abort_unless(
                $application->status === 'pending',
                422,
                'Application has already been reviewed.'
            );

            if ($data['status'] === 'Approved') {
                // Preserve the job type chosen during rider registration.
                // Logistics cannot choose or override this value.
                $jobType = RiderJobType::validateSelected(
                    $application->job_type
                );

                $userQuery = DB::table('users');

                if (! empty($application->user_id)) {
                    $userQuery->where(
                        'id',
                        $application->user_id
                    );
                } else {
                    $userQuery->where(
                        'email',
                        $application->email
                    );
                }

                // Serialize approvals belonging to the same user.
                $user = $userQuery
                    ->lockForUpdate()
                    ->first();

                if (! $user) {
                    throw ValidationException::withMessages([
                        'status' => 'Applicant must register a user account before approval.',
                    ]);
                }

                $existing = DB::table('riders')
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'status' => 'This user already has a rider account.',
                    ]);
                }

                DB::table('riders')->insert([
                    'user_id' => $user->id,
                    'logistics_provider_id' => $providerId,
                    'vehicle_type' => $application->vehicle_type,
                    'job_type' => $jobType,
                    'shift_start_time' => $jobType === RiderJobType::HUB_TRANSFER
                        ? '20:00:00'
                        : '07:00:00',
                    'shift_end_time' => $jobType === RiderJobType::HUB_TRANSFER
                        ? '05:00:00'
                        : '17:00:00',
                    'plate_no' => $application->plate_no,

                    'province' => $application->province,
                    'municipality' => $application->municipality,
                    'barangay' => $application->barangay,
                    'municipality_psgc_code' => $application->municipality_psgc_code,

                    'is_active' => false,
                    'management_status' => 'Offline',
                    'management_remarks' => 'Newly approved rider.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('logistics_rider_applications')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->update([
                    'status' => strtolower($data['status']),
                    'remarks' => $data['remarks'] ?? '',
                    'updated_at' => now(),
                ]);

            LogisticsNotification::create(
                $providerId,
                'rider_application',
                'Rider Application '.$data['status'],
                'Application #'.$application->id
                    .' for '.$application->name
                    .' was '.strtolower($data['status']).'.',
                null
            );
        }, 3);

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
            $data['status'] === 'Suspended'
            && ! filled($data['remarks'] ?? null)
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

            abort_unless(
                $rider,
                404,
                'Rider not found.'
            );

            if ($data['status'] === 'Suspended') {
                $activeDeliveries = DB::table('shipments')
                    ->where('rider_id', $id)
                    ->whereIn('status', [
                        'assigned',
                        'picked_up',
                        'in_transit',
                        'out_for_delivery',
                    ])
                    ->exists();

                $activeTasks = DB::table('rider_tasks')
                    ->where('rider_id', $id)
                    ->where(function ($query) {
                        $query
                            ->whereNotIn(
                                'status',
                                RiderTaskAssignmentService::TERMINAL_STATUSES
                            )
                            ->orWhereNull('status');
                    })
                    ->exists();

                if ($activeDeliveries || $activeTasks) {
                    throw ValidationException::withMessages([
                        'status' => 'Resolve active deliveries and rider tasks before suspending this rider.',
                    ]);
                }
            }

            // Availability changes do not change the registered job type.
            DB::table('riders')
                ->where('id', $id)
                ->where('logistics_provider_id', $providerId)
                ->update([
                    'is_active' => $data['status'] === 'Available',
                    'management_status' => $data['status'],
                    'management_remarks' => $data['remarks'] ?? '',
                    'updated_at' => now(),
                ]);

            LogisticsNotification::create(
                $providerId,
                'rider_status',
                'Rider Status Updated',
                'Rider #'.$rider->id
                    .' status changed to '.$data['status'].'.',
                null
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Rider status updated successfully.',
        ]);
    }
}

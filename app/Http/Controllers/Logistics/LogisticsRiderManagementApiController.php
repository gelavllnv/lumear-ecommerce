<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MarketplaceAlert;
use App\Services\RiderTaskAssignmentService;
use App\Support\LogisticsNotification;
use App\Support\RiderJobType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $hubName = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->value('name');

        return DB::table('logistics_rider_applications')
            ->where('logistics_provider_id', $providerId)
            ->whereIn('status', ['pending', 'rejected'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($application) => [
                'id' => 'APP-' . str_pad(
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
                'municipality' => $application->municipality ?? $area,
                'address' => $this->address($application, $area),
                'region' => $application->region ?? '',
                'province' => $application->province ?? '',
                'barangay' => $application->barangay ?? '',
                'street' => $application->street ?? '',
                'houseUnit' => $application->house_unit ?? '',
                'preferredHub' => $hubName ?? '',
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
                ...$this->documentLinks(
                    $application->document_paths ?? null,
                    'applications',
                    (int) $application->id,
                ),
            ]);
    }

    private function riderData(int $providerId)
    {
        $area = $this->providerArea($providerId);
        $hubName = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->value('name');
        $approvedApplications = DB::table('logistics_rider_applications')
            ->where('logistics_provider_id', $providerId)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($applications) => $applications->first());

        $userBirthdays = DB::table('users')
            ->whereIn('id', function ($query) use ($providerId) {
                $query
                    ->select('user_id')
                    ->from('riders')
                    ->where('logistics_provider_id', $providerId);
            })
            ->whereNotNull('date_of_birth')
            ->pluck('date_of_birth', 'id');
        $shipmentStats = DB::table('shipments')
            ->select('rider_id', 'status', DB::raw('COUNT(*) as total'))
            ->whereIn('rider_id', function ($query) use ($providerId) {
                $query
                    ->select('id')
                    ->from('riders')
                    ->where('logistics_provider_id', $providerId);
            })
            ->groupBy('rider_id', 'status')
            ->get()
            ->groupBy('rider_id');

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
            ->map(function ($rider) use ($area, $hubName, $approvedApplications, $userBirthdays, $shipmentStats) {
                $application = $approvedApplications->get($rider->user_id);
                $riderShipments = $shipmentStats->get($rider->id, collect());
                $birthday = ($rider->date_of_birth ?? null)
                    ?: ($application->date_of_birth ?? null)
                    ?: $userBirthdays->get($rider->user_id);
                $documentPaths = $rider->document_paths
                    ?: ($application->document_paths ?? null);
                $addressRecord = (object) [
                    'house_unit' => ($rider->house_unit ?? null) ?: ($application->house_unit ?? ''),
                    'street' => ($rider->street ?? null) ?: ($application->street ?? ''),
                    'barangay' => ($rider->barangay ?? null) ?: ($application->barangay ?? ''),
                    'municipality' => ($rider->municipality ?? null) ?: ($application->municipality ?? ''),
                    'province' => ($rider->province ?? null) ?: ($application->province ?? ''),
                    'region' => ($rider->region ?? null) ?: ($application->region ?? ''),
                    'postal_code' => ($rider->postal_code ?? null) ?: ($application->postal_code ?? ''),
                ];
                $delivered = (int) $riderShipments
                    ->where('status', 'delivered')
                    ->sum('total');
                $ongoing = (int) $riderShipments
                    ->whereIn('status', ['assigned', 'picked_up', 'in_transit', 'out_for_delivery'])
                    ->sum('total');
                $failed = (int) $riderShipments
                    ->whereIn('status', ['failed', 'delivery_failed_attempt'])
                    ->sum('total');

                return [
                'id' => 'RDR-' . str_pad(
                    (string) $rider->id,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'applicationId' => null,
                'name' => $rider->account_name,
                'email' => ($rider->account_email ?? null) ?: ($application->email ?? ''),
                'phone' => ($rider->account_phone ?? null) ?: ($application->phone ?? ''),
                'hub' => $hubName ?? 'Hub not assigned',
                'preferredHub' => $hubName ?? '',
                'municipality' => $addressRecord->municipality ?: $area,
                'address' => $this->address($addressRecord, $area),
                'birthday' => $birthday ?? '',
                'age' => filled($birthday)
                    ? \Illuminate\Support\Carbon::parse($birthday)->age
                    : null,
                'sex' => ($rider->sex ?? null) ?: ($application->sex ?? ''),
                'region' => $addressRecord->region,
                'province' => $addressRecord->province,
                'barangay' => $addressRecord->barangay,
                'street' => $addressRecord->street,
                'houseUnit' => $addressRecord->house_unit,
                'vehicle' => $rider->vehicle_type ?? 'Motorcycle',
                'jobType' => $rider->job_type,
                'shiftStartTime' => $rider->shift_start_time,
                'shiftEndTime' => $rider->shift_end_time,
                'plate' => $rider->plate_no ?? '',
                'license' => ($rider->license_no ?? null) ?: ($application->license_no ?? ''),
                'joined' => substr((string) $rider->created_at, 0, 10),
                'delivered' => $delivered,
                'ongoing' => $ongoing,
                'failed' => $failed,
                'status' => in_array(
                    $rider->management_status,
                    ['Suspended', 'Terminated'],
                    true,
                )
                    ? $rider->management_status
                    : (
                        RiderJobType::isAvailable($rider)
                            ? 'Available'
                            : 'Offline'
                    ),
                'remarks' => $rider->management_remarks ?? '',
                ...$this->documentLinks(
                    $documentPaths,
                    'riders',
                    (int) $rider->id,
                ),
            ];
            });
    }

    private function documentLinks(?string $documentPaths, string $ownerType, int $ownerId): array
    {
        $paths = json_decode((string) $documentPaths, true);
        $paths = is_array($paths) ? $paths : [];

        return [
            'licenseDocument' => $this->documentLink(
                $paths['driver_license'] ?? null,
                "/logistics/{$ownerType}/{$ownerId}/documents/driver_license",
                'drivers_license',
            ),
            'orDocument' => $this->documentLink(
                $paths['or_document'] ?? null,
                "/logistics/{$ownerType}/{$ownerId}/documents/or_document",
                'or',
            ),
            'crDocument' => $this->documentLink(
                $paths['cr_document'] ?? null,
                "/logistics/{$ownerType}/{$ownerId}/documents/cr_document",
                'cr',
            ),
        ];
    }

    private function documentLink(mixed $storedPath, string $endpoint, string $label): ?array
    {
        if (
            ! is_string($storedPath)
            || $storedPath === ''
            || ! Storage::disk('local')->exists($storedPath)
        ) {
            return null;
        }

        return [
            'path' => $endpoint,
            'name' => $label . '.' . pathinfo($storedPath, PATHINFO_EXTENSION),
        ];
    }

    public function riderDocument(Request $request, int $id, string $type): StreamedResponse
    {
        $providerId = $this->providerId();
        $rider = DB::table('riders')
            ->where('id', $id)
            ->where('logistics_provider_id', $providerId)
            ->first();

        abort_unless($rider, 404, 'Rider not found.');

        $documents = json_decode((string) ($rider->document_paths ?? ''), true);
        $path = $this->documentPath($documents, $type);

        if ($path === null) {
            $application = DB::table('logistics_rider_applications')
                ->where('user_id', $rider->user_id)
                ->where('logistics_provider_id', $providerId)
                ->where('status', 'approved')
                ->latest('id')
                ->first();
            $documents = json_decode((string) ($application->document_paths ?? ''), true);
            $path = $this->documentPath($documents, $type);
        }

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404, 'Document not found.');

        return Storage::disk('local')->download($path);
    }

    public function previewRiderDocument(Request $request, int $id, string $type): BinaryFileResponse
    {
        $providerId = $this->providerId();
        $rider = DB::table('riders')
            ->where('id', $id)
            ->where('logistics_provider_id', $providerId)
            ->first();

        abort_unless($rider, 404, 'Rider not found.');

        $documents = json_decode((string) ($rider->document_paths ?? ''), true);
        $path = $this->documentPath($documents, $type);

        if ($path === null) {
            $application = DB::table('logistics_rider_applications')
                ->where('user_id', $rider->user_id)
                ->where('logistics_provider_id', $providerId)
                ->where('status', 'approved')
                ->latest('id')
                ->first();
            $documents = json_decode((string) ($application->document_paths ?? ''), true);
            $path = $this->documentPath($documents, $type);
        }

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404, 'Document not found.');

        return response()->file(Storage::disk('local')->path($path));
    }

    public function applicationDocument(Request $request, int $id, string $type): StreamedResponse
    {
        $providerId = $this->providerId();
        $application = DB::table('logistics_rider_applications')
            ->where('id', $id)
            ->where('logistics_provider_id', $providerId)
            ->first();

        abort_unless($application, 404, 'Application not found.');

        $documents = json_decode((string) ($application->document_paths ?? ''), true);
        $path = $this->documentPath($documents, $type);

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404, 'Document not found.');

        return Storage::disk('local')->download($path);
    }

    public function previewApplicationDocument(Request $request, int $id, string $type): BinaryFileResponse
    {
        $providerId = $this->providerId();
        $application = DB::table('logistics_rider_applications')
            ->where('id', $id)
            ->where('logistics_provider_id', $providerId)
            ->first();

        abort_unless($application, 404, 'Application not found.');

        $documents = json_decode((string) ($application->document_paths ?? ''), true);
        $path = $this->documentPath($documents, $type);

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404, 'Document not found.');

        return response()->file(Storage::disk('local')->path($path));
    }

    private function documentPath(mixed $documents, string $type): ?string
    {
        $field = [
            'driver_license' => 'driver_license',
            'or_document' => 'or_document',
            'cr_document' => 'cr_document',
        ][$type] ?? null;

        if ($field === null || ! is_array($documents)) {
            return null;
        }

        $path = $documents[$field] ?? null;

        return is_string($path) ? $path : null;
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
            'shift_start_time' => 'required_if:status,Approved|nullable|date_format:H:i',
            'shift_end_time' => 'required_if:status,Approved|nullable|date_format:H:i',
        ]);

        if (
            $data['status'] === 'Approved'
            && $data['shift_end_time'] <= $data['shift_start_time']
        ) {
            throw ValidationException::withMessages([
                'shift_end_time' => 'Shift end time must be later than the start time.',
            ]);
        }

        if (
            $data['status'] === 'Rejected'
            && !filled($data['remarks'] ?? null)
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

                if (!empty($application->user_id)) {
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

                if (!$user) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Applicant must register a user account before approval.',
                    ]);
                }

                $existing = DB::table('riders')
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
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
                    'name' => $application->name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'vehicle_type' => $application->vehicle_type,
                    'job_type' => $jobType,
                    'plate_no' => $application->plate_no,
                    'license_no' => $application->license_no,
                    'sex' => $application->sex,
                    'date_of_birth' => $application->date_of_birth,
                    'region' => $application->region,
                    'province' => $application->province,
                    'municipality' => $application->municipality,
                    'municipality_psgc_code' => $application->municipality_psgc_code,
                    'postal_code' => $application->postal_code,
                    'barangay' => $application->barangay,
                    'street' => $application->street,
                    'house_unit' => $application->house_unit,
                    'document_paths' => $application->document_paths,
                    'is_active' => false,
                    'management_status' => 'Offline',
                    'management_remarks' => 'Newly approved rider.',
                    'shift_start_time' => $data['shift_start_time'],
                    'shift_end_time' => $data['shift_end_time'],
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
                'Rider Application ' . $data['status'],
                'Application #' . $application->id
                    . ' for ' . $application->name
                    . ' was ' . strtolower($data['status']) . '.',
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
            'status' => 'required|in:Available,Offline,Suspended,Terminated',
            'remarks' => 'nullable|string|max:500',
        ]);

        if (
            in_array($data['status'], ['Suspended', 'Terminated'], true)
            && !filled($data['remarks'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'remarks' => 'A reason is required to suspend or terminate a rider.',
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

            if (
                $rider->management_status === 'Terminated'
                && $data['status'] !== 'Terminated'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'A terminated rider account cannot be reactivated.',
                ]);
            }

            if (in_array($data['status'], ['Suspended', 'Terminated'], true)) {
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
                    ->whereExists(function ($query) {
                        $query
                            ->selectRaw('1')
                            ->from('shipments as task_shipment')
                            ->whereColumn(
                                'task_shipment.id',
                                'rider_tasks.shipment_id'
                            )
                            ->whereNotIn('task_shipment.status', [
                                'delivered',
                                'returned',
                                'cancelled',
                            ]);
                    })
                    ->exists();

                if ($activeDeliveries || $activeTasks) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Resolve active deliveries and rider tasks before suspending or terminating this rider.',
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

            if (
                in_array($data['status'], ['Suspended', 'Terminated'], true)
                && $rider->management_status !== $data['status']
            ) {
                $reason = trim((string) ($data['remarks'] ?? ''));
                $isTerminated = $data['status'] === 'Terminated';
                User::query()->find($rider->user_id)?->notify(new MarketplaceAlert(
                    $isTerminated
                        ? 'Rider account terminated'
                        : 'Rider account suspended',
                    ($isTerminated
                        ? 'Your Logistics rider account has been terminated. Contact your logistics provider if you believe this is a mistake.'
                        : 'Your Logistics rider account has been suspended.')
                        . ($reason !== '' ? ' Reason: ' . $reason : ''),
                    null,
                ));
            }

            LogisticsNotification::create(
                $providerId,
                'rider_status',
                'Rider Status Updated',
                'Rider #' . $rider->id
                    . ' status changed to ' . $data['status'] . '.'
                    . (filled($data['remarks'] ?? null)
                        ? ' Reason: ' . $data['remarks']
                        : ''),
                null
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Rider status updated successfully.',
        ]);
    }
}

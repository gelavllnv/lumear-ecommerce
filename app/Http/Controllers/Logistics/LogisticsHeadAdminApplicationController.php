<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LogisticsHeadAdminApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        if (! in_array($status, [
            'pending',
            'approved',
            'rejected',
            'all',
        ], true)) {
            abort(404);
        }

        $query = DB::table('logistics_applications')
            ->select(
                'id',
                'company_name',
                'first_name',
                'last_name',
                'city',
                'province',
                'status',
                'created_at'
            );

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view(
            'logistics.head-admin.applications.index',
            [
                'applications' => $query
                    ->orderByDesc('id')
                    ->paginate(15)
                    ->withQueryString(),

                'status' => $status,
            ]
        );
    }

    public function show(int $id)
    {
        $application = DB::table('logistics_applications as a')
            ->join(
                'logistics_providers as p',
                'p.id',
                '=',
                'a.logistics_provider_id'
            )
            ->select(
                'a.*',
                'p.status as provider_status',
                'p.rejection_reason',
                'p.municipality_name',
                'p.municipality_psgc_code as provider_municipality_code'
            )
            ->where('a.id', $id)
            ->first();

        abort_unless($application, 404);

        return view(
            'logistics.head-admin.applications.show',
            compact('application')
        );
    }

    public function document(int $id, string $type)
    {
        $application = DB::table('logistics_applications')
            ->where('id', $id)
            ->first();

        abort_unless($application, 404);

        $path = match ($type) {
            'valid-id' => $application->valid_id_path,
            'business-permit' => $application->business_permit_path,
            default => null,
        };

        $prefix = match ($type) {
            'valid-id' => 'logistics/valid-ids/',
            'business-permit' => 'logistics/business-permits/',
            default => '',
        };

        abort_unless(
            is_string($path) &&
            str_starts_with($path, $prefix) &&
            ! str_contains($path, '..') &&
            Storage::disk('local')->exists($path),
            404
        );

        return Storage::disk('local')->response(
            $path,
            basename($path),
            [
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            'inline'
        );
    }

    public function approve(int $id)
    {
        try {
            DB::transaction(function () use ($id) {

                $application = DB::table('logistics_applications')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($application, 404);

                if ($application->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'application' => 'Only pending applications can be approved.',
                    ]);
                }

                $provider = DB::table('logistics_providers')
                    ->where('id', $application->logistics_provider_id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($provider, 404);

                if ($provider->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'application' => 'The provider is no longer pending.',
                    ]);
                }

                $municipalityCode =
                    $application->municipality_psgc_code;

                if (! $municipalityCode) {
                    throw ValidationException::withMessages([
                        'application' => 'This application has no municipality code.',
                    ]);
                }

                $alreadyAssigned = DB::table('logistics_providers')
                    ->where('status', 'approved')
                    ->where(
                        'municipality_psgc_code',
                        $municipalityCode
                    )
                    ->where('id', '!=', $provider->id)
                    ->exists();

                if ($alreadyAssigned) {
                    throw ValidationException::withMessages([
                        'application' => 'Another Logistics provider is already approved for this municipality.',
                    ]);
                }

                DB::table('logistics_providers')
                    ->where('id', $provider->id)
                    ->update([
                        'status' => 'approved',
                        'rejection_reason' => null,
                        'updated_at' => now(),
                    ]);

                DB::table('logistics_applications')
                    ->where('id', $application->id)
                    ->update([
                        'status' => 'approved',
                        'updated_at' => now(),
                    ]);

            }, 3);

        } catch (QueryException $exception) {

            // The existing unique municipality constraint
            // is the final protection against concurrent approvals.
            if (
                in_array(
                    (int) ($exception->errorInfo[1] ?? 0),
                    [1062, 1213],
                    true
                )
            ) {
                return back()->withErrors([
                    'application' => 'Approval could not be completed because the municipality may already be assigned or the record was updated concurrently. Please refresh and try again.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route(
                'logistics.head-admin.applications.show',
                $id
            )
            ->with(
                'success',
                'Logistics application approved successfully.'
            );
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use ($id, $data) {

            $application = DB::table('logistics_applications')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            abort_unless($application, 404);

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages([
                    'application' => 'Only pending applications can be rejected.',
                ]);
            }

            $provider = DB::table('logistics_providers')
                ->where('id', $application->logistics_provider_id)
                ->lockForUpdate()
                ->first();

            abort_unless($provider, 404);

            if ($provider->status !== 'pending') {
                throw ValidationException::withMessages([
                    'application' => 'The provider is no longer pending.',
                ]);
            }

            DB::table('logistics_providers')
                ->where('id', $provider->id)
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => trim($data['rejection_reason']),
                    'updated_at' => now(),
                ]);

            DB::table('logistics_applications')
                ->where('id', $application->id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);

        }, 3);

        return redirect()
            ->route(
                'logistics.head-admin.applications.show',
                $id
            )
            ->with(
                'success',
                'Logistics application rejected successfully.'
            );
    }
}

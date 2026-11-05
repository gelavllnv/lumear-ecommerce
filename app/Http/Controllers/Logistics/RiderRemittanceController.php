<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\RiderRemittanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RiderRemittanceController extends Controller
{
    private function providerId(): int
    {
        $provider = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        abort_unless($provider, 403, 'Approved logistics account required.');

        return (int) $provider->id;
    }

    public function index(
        Request $request,
        RiderRemittanceService $service
    ) {
        $providerId = $this->providerId();

        // Synchronize restrictions for overdue riders.
        // A scheduled job will also be needed later.
        $riderIds = DB::table('rider_cod_obligations')
            ->where('logistics_provider_id', $providerId)
            ->where('due_at', '<', now())
            ->whereRaw('expected_amount_minor > settled_amount_minor')
            ->whereNotIn('status', ['settled', 'cancelled'])
            ->distinct()
            ->pluck('rider_id');

        foreach ($riderIds as $riderId) {
            $service->syncOverdueRestriction(
                (int) $riderId,
                $providerId
            );
        }

        $summary = DB::table('rider_cod_obligations')
            ->where('logistics_provider_id', $providerId)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                COALESCE(SUM(expected_amount_minor), 0) as expected,
                COALESCE(SUM(settled_amount_minor), 0) as settled,
                COALESCE(SUM(
                    CASE
                        WHEN due_at < ?
                        AND expected_amount_minor > settled_amount_minor
                        THEN expected_amount_minor - settled_amount_minor
                        ELSE 0
                    END
                ), 0) as overdue
            ', [now()])
            ->first();

        $expected = (int) $summary->expected;
        $settled = (int) $summary->settled;

        $stats = [
            'expected' => $expected,
            'settled' => $settled,
            'outstanding' => max(0, $expected - $settled),
            'overdue' => (int) $summary->overdue,
            'pending_count' => DB::table('rider_remittances')
                ->where('logistics_provider_id', $providerId)
                ->where('status', 'pending_verification')
                ->count(),
            'open_discrepancies' => DB::table(
                'rider_remittance_discrepancies'
            )
                ->where('logistics_provider_id', $providerId)
                ->whereIn('status', ['open', 'under_review'])
                ->count(),
        ];

        $remittances = DB::table('rider_remittances as rem')
            ->join('riders as r', 'r.id', '=', 'rem.rider_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('rem.logistics_provider_id', $providerId)
            ->select(
                'rem.*',
                'u.name as rider_name'
            )
            ->orderByRaw("
                CASE WHEN rem.status = 'pending_verification'
                THEN 0 ELSE 1 END
            ")
            ->orderByDesc('rem.submitted_at')
            ->paginate(10, ['*'], 'remittance_page');

        $riderBalances = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('rider_cod_obligations as o', function ($join) {
                $join->on('o.rider_id', '=', 'r.id')
                    ->where('o.status', '!=', 'cancelled');
            })
            ->where('r.logistics_provider_id', $providerId)
            ->groupBy('r.id', 'u.name')
            ->select(
                'r.id',
                'u.name as rider_name'
            )
            ->selectRaw('
                COALESCE(SUM(o.expected_amount_minor), 0)
                as expected_minor
            ')
            ->selectRaw('
                COALESCE(SUM(o.settled_amount_minor), 0)
                as settled_minor
            ')
            ->orderBy('u.name')
            ->get()
            ->map(function ($rider) use ($providerId) {
                $rider->outstanding_minor = max(
                    0,
                    (int) $rider->expected_minor
                    - (int) $rider->settled_minor
                );

                $rider->restricted = DB::table(
                    'rider_remittance_restrictions'
                )
                    ->where('rider_id', $rider->id)
                    ->where('logistics_provider_id', $providerId)
                    ->where('status', 'active')
                    ->exists();

                return $rider;
            });

        $discrepancies = DB::table(
            'rider_remittance_discrepancies as d'
        )
            ->join('riders as r', 'r.id', '=', 'd.rider_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('d.logistics_provider_id', $providerId)
            ->select('d.*', 'u.name as rider_name')
            ->orderByDesc('d.created_at')
            ->limit(10)
            ->get();

        return view('logistics.remittance.index', compact(
            'stats',
            'remittances',
            'riderBalances',
            'discrepancies'
        ));
    }

    public function verify(
        Request $request,
        int $id,
        RiderRemittanceService $service
    ) {
        $providerId = $this->providerId();

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'funds_confirmed' => ['required', 'accepted'],
        ]);

        try {
            $service->verifyDigitalRemittance(
                $id,
                $providerId,
                (int) Auth::id(),
                $data['remarks'] ?? null
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with(
            'success',
            'Digital remittance verified and COD balances updated.'
        );
    }

    public function reject(
        Request $request,
        int $id,
        RiderRemittanceService $service
    ) {
        $providerId = $this->providerId();

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $service->rejectDigitalRemittance(
                $id,
                $providerId,
                (int) Auth::id(),
                $data['reason']
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with(
            'success',
            'Remittance rejected. The COD balance remains outstanding.'
        );
    }

    public function receipt(int $id)
    {
        $providerId = $this->providerId();

        $remittance = DB::table('rider_remittances')
            ->where('id', $id)
            ->where('logistics_provider_id', $providerId)
            ->first();

        abort_unless($remittance, 404);

        abort_unless($remittance->receipt_path, 404);

        $path = $remittance->receipt_path;

        abort_if(
            str_contains($path, '..')
            || str_starts_with($path, '/'),
            404
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        // Force a download rather than displaying untrusted files.
        return $disk->download(
            $path,
            'remittance-receipt-' . $id . '.' .
            pathinfo($path, PATHINFO_EXTENSION)
        );
    }
}

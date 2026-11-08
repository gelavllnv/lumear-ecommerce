<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\CodForwardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CodForwardingController extends Controller
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

    public function index(CodForwardingService $service)
    {
        $providerId = $this->providerId();
        $stats = $service->summary($providerId);

        $transfers = DB::table('logistics_cod_forwardings')
            ->where('logistics_provider_id', $providerId)
            ->orderByDesc('id')
            ->paginate(10);

        $items = DB::table('logistics_cod_forwarding_items as i')
            ->join(
                'logistics_cod_wallet_entries as e',
                'e.id',
                '=',
                'i.cod_wallet_entry_id'
            )
            ->whereIn(
                'i.forwarding_id',
                $transfers->pluck('id')
            )
            ->get([
                'i.forwarding_id',
                'i.cod_wallet_entry_id',
                'e.rider_remittance_id',
                'i.amount_minor',
            ])
            ->groupBy('forwarding_id');

        $requestKey = (string) Str::uuid();

        return view(
            'logistics.remittance.forward',
            compact(
                'stats',
                'transfers',
                'items',
                'requestKey'
            )
        );
    }

    public function store(
        Request $request,
        CodForwardingService $service
    ) {
        $data = $request->validate([
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,10}(?:\.\d{1,2})?$/',
            ],
            'request_key' => [
                'required',
                'uuid',
            ],
        ]);

        // Convert decimal text to centavos without float arithmetic.
        $parts = explode('.', $data['amount'], 2);

        $minor = ((int) $parts[0] * 100)
            + (int) str_pad(
                $parts[1] ?? '',
                2,
                '0'
            );

        $transfer = $service->create(
            $this->providerId(),
            (int) Auth::id(),
            $minor,
            $data['request_key']
        );

        $message = match ($transfer->status) {
            'completed' => 'Request '.$transfer->reference
                .' was already confirmed by admin. No duplicate transfer was created.',

            'rejected' => 'Request '.$transfer->reference
                .' was already rejected. Reload the form to submit a new request.',

            default => 'Request '.$transfer->reference
                .' recorded. Funds are reserved pending admin wallet credit.',
        };

        return redirect()
            ->route('logistics.cod-forwarding.index')
            ->with('success', $message);
    }
}

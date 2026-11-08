<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\CodForwardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CodForwardingBridgeController extends Controller
{
    public function index(
        Request $request,
        CodForwardingService $service
    ) {
        $data = $request->validate([
            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $transfers = DB::table('logistics_cod_forwardings')
            ->where('status', 'pending_admin')
            ->orderBy('id')
            ->limit((int) ($data['limit'] ?? 20))
            ->get();

        return response()->json([
            'data' => $transfers
                ->map(
                    fn ($transfer) => $service->payload($transfer)
                )
                ->all(),
        ]);
    }

    public function show(
        string $reference,
        CodForwardingService $service
    ) {
        $transfer = DB::table('logistics_cod_forwardings')
            ->where('reference', $reference)
            ->first();

        abort_unless($transfer, 404);

        return response()->json([
            'data' => $service->payload($transfer),
        ]);
    }

    public function complete(
        Request $request,
        string $reference,
        CodForwardingService $service
    ) {
        $data = $request->validate([
            'amount_minor' => [
                'required',
                'integer',
                'min:1',
            ],
            'currency' => [
                'required',
                'in:PHP',
            ],
            'admin_reference' => [
                'required',
                'string',
                'max:120',
            ],
        ]);

        $transfer = $service->complete(
            $reference,
            (int) $data['amount_minor'],
            $data['currency'],
            $data['admin_reference']
        );

        return response()->json([
            'data' => $service->payload($transfer),
        ]);
    }

    public function reject(
        Request $request,
        string $reference,
        CodForwardingService $service
    ) {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
            'credit_not_applied' => [
                'required',
                'accepted',
            ],
        ]);

        $transfer = $service->reject(
            $reference,
            $data['reason']
        );

        return response()->json([
            'data' => $service->payload($transfer),
        ]);
    }
}

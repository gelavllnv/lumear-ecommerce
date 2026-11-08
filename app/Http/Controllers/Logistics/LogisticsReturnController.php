<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\LogisticsReturnService;
use App\Support\RiderJobType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogisticsReturnController extends Controller
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

    public function index(Request $request)
    {
        $providerId = $this->providerId();

        $status = $request->query('status', 'all');

        $cases = DB::table('logistics_return_cases as rc')
            ->leftJoin('riders as r', 'r.id', '=', 'rc.rider_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('rc.logistics_provider_id', $providerId)
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('rc.status', $status)
            )
            ->select('rc.*', 'u.name as rider_name')
            ->orderByDesc('rc.created_at')
            ->paginate(20)
            ->withQueryString();

        $riders = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.logistics_provider_id', $providerId)
            ->where('r.job_type', RiderJobType::SELLER_PICKUP)
            ->select('r.*', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->filter(
                fn ($rider) => RiderJobType::isAvailable($rider)
            );

        $summary = DB::table('logistics_return_cases')
            ->where('logistics_provider_id', $providerId)
            ->selectRaw(
                "COUNT(*) total,
                SUM(status = 'approved') approved,
                SUM(status = 'scheduled') scheduled,
                SUM(status = 'picked_up') picked_up,
                SUM(status = 'received_at_hub') hub_received,
                SUM(status = 'handed_to_seller') completed,
                SUM(status = 'pickup_failed') failed"
            )
            ->first();

        return view(
            'logistics.returns.index',
            compact(
                'cases',
                'riders',
                'summary',
                'status'
            )
        );
    }

    public function schedule(
        Request $request,
        int $id,
        LogisticsReturnService $service
    ) {
        $data = $request->validate([
            'rider_id' => ['required', 'integer'],
            'scheduled_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'schedule_start' => [
                'required',
                'date_format:H:i',
            ],
            'schedule_end' => [
                'required',
                'date_format:H:i',
            ],
            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $service->schedule(
            $id,
            $this->providerId(),
            (int) Auth::id(),
            (int) $data['rider_id'],
            $data['scheduled_date'],
            $data['schedule_start'],
            $data['schedule_end'],
            $data['note'] ?? null
        );

        return back()->with(
            'success',
            'Return pickup scheduled. The Buyer/Admin side can now show the pickup window.'
        );
    }

    public function receive(
        Request $request,
        int $id,
        LogisticsReturnService $service
    ) {
        $data = $request->validate([
            'return_reference' => [
                'required',
                'string',
                'max:64',
            ],
            'condition' => [
                'nullable',
                'string',
                'max:120',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $service->receiveAtHub(
            $id,
            $this->providerId(),
            (int) Auth::id(),
            $data['return_reference'],
            $data['condition'] ?? null,
            $data['remarks'] ?? null
        );

        return back()->with(
            'success',
            'Return parcel received at the hub.'
        );
    }

    public function handToSeller(
        Request $request,
        int $id,
        LogisticsReturnService $service
    ) {
        $data = $request->validate([
            'recipient' => [
                'required',
                'string',
                'max:150',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $service->handToSeller(
            $id,
            $this->providerId(),
            (int) Auth::id(),
            $data['recipient'],
            $data['remarks'] ?? null
        );

        return back()->with(
            'success',
            'Return handoff to seller recorded. Admin can now continue its refund decision.'
        );
    }

    public function report(Request $request)
    {
        $providerId = $this->providerId();

        $data = $request->validate([
            'from' => [
                'required',
                'date_format:Y-m-d',
            ],
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ]);

        $provider = DB::table('logistics_providers')
            ->where('id', $providerId)
            ->first();

        $returns = DB::table('logistics_return_cases as rc')
            ->leftJoin('riders as r', 'r.id', '=', 'rc.rider_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('rc.logistics_provider_id', $providerId)
            ->whereDate('rc.approved_at', '>=', $data['from'])
            ->whereDate('rc.approved_at', '<=', $data['to'])
            ->select(
                'rc.return_reference',
                'rc.status',
                'rc.return_reason',
                'rc.approved_at',
                'rc.scheduled_date',
                'rc.picked_up_at',
                'rc.hub_received_at',
                'rc.seller_handed_at',
                'u.name as rider_name'
            )
            ->orderBy('rc.approved_at')
            ->get();

        $summary = [
            'total' => $returns->count(),

            'approved' => $returns
                ->where('status', 'approved')
                ->count(),

            'scheduled' => $returns
                ->where('status', 'scheduled')
                ->count(),

            'picked_up' => $returns
                ->where('status', 'picked_up')
                ->count(),

            'hub_received' => $returns
                ->where('status', 'received_at_hub')
                ->count(),

            'completed' => $returns
                ->where('status', 'handed_to_seller')
                ->count(),

            'failed' => $returns
                ->where('status', 'pickup_failed')
                ->count(),
        ];

        $generatedAt = now('Asia/Manila')
            ->format('F d, Y h:i A').' PHT';

        $reference = 'RETURN-'
            .now('Asia/Manila')->format('Ymd-His');

        $pdf = Pdf::loadView(
            'logistics.returns.report-pdf',
            [
                'provider' => $provider,
                'returns' => $returns,
                'summary' => $summary,
                'from' => $data['from'],
                'to' => $data['to'],
                'generatedAt' => $generatedAt,
                'reference' => $reference,
            ]
        )
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        return $pdf->download(
            'lumear-return-report-'
            .$data['from']
            .'-to-'
            .$data['to']
            .'.pdf'
        );
    }
}

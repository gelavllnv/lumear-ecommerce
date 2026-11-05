<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LogisticsReportPdfController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET AUTHENTICATED LOGISTICS PROVIDER
    |--------------------------------------------------------------------------
    */

    private function provider()
    {
        $provider = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        abort_unless($provider, 403);

        return $provider;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE REPORT FILTERS
    |--------------------------------------------------------------------------
    */

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => [
                'required',
                'date_format:Y-m-d',
            ],

            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],

            'type' => [
                'required',
                Rule::in([
                    'all',
                    'pickup',
                    'incoming',
                    'sorting',
                    'assignment',
                    'delivery',
                    'rider',
                ]),
            ],

            'status' => [
                'required',
                Rule::in([
                    'all',
                    'Completed',
                    'In Progress',
                    'Exception',
                ]),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE OFFICIAL PDF REPORT
    |--------------------------------------------------------------------------
    |
    | Paper format: A4
    | Orientation: PORTRAIT (VERTICAL)
    |
    | The report continues using real provider-scoped
    | records from the Logistics Reports API.
    |
    */

    public function generate(
        Request $request,
        LogisticsReportsApiController $reportsApi
    ) {
        $provider = $this->provider();

        $filters = $this->validatedFilters($request);

        /*
        |--------------------------------------------------------------------------
        | RETRIEVE LIVE LOGISTICS REPORT DATA
        |--------------------------------------------------------------------------
        */

        $reportRequest = Request::create(
            '/logistics/api/reports',
            'GET',
            [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'type' => $filters['type'],
            ]
        );

        $reportRequest->setUserResolver(
            fn () => $request->user()
        );

        $response = $reportsApi->index($reportRequest);

        $payload = $response->getData(true);

        $records = collect($payload['records'] ?? []);

        /*
        |--------------------------------------------------------------------------
        | APPLY STATUS FILTER
        |--------------------------------------------------------------------------
        */

        if ($filters['status'] !== 'all') {
            $records = $records->where(
                'status',
                $filters['status']
            );
        }

        $records = $records->values();

        /*
        |--------------------------------------------------------------------------
        | REPORT STATISTICS
        |--------------------------------------------------------------------------
        */

        $summary = [
            'total' => $records->count(),

            'completed' => $records
                ->where('status', 'Completed')
                ->count(),

            'progress' => $records
                ->where('status', 'In Progress')
                ->count(),

            'exceptions' => $records
                ->where('status', 'Exception')
                ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | GENERATE REFERENCE AND PHILIPPINE LOCAL TIME
        |--------------------------------------------------------------------------
        */

        $philippineTime = now('Asia/Manila');

        $reference = 'LMR-' .
            $philippineTime->format('Ymd-His') . '-' .
            strtoupper(Str::random(6));

        $generatedAt = $philippineTime->format(
            'F d, Y h:i A'
        ) . ' PHT';

        /*
        |--------------------------------------------------------------------------
        | GENERATE A4 PORTRAIT PDF
        |--------------------------------------------------------------------------
        |
        | Changed from landscape to portrait.
        |
        | Portrait dimensions:
        | 210 mm x 297 mm
        |
        */

        $pdf = Pdf::loadView(
            'logistics.reports.pdf',
            [
                'reference' => $reference,
                'provider' => $provider,
                'generatedBy' => Auth::user()->name,
                'generatedAt' => $generatedAt,
                'filters' => $filters,
                'records' => $records,
                'summary' => $summary,
            ]
        )
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ]);

        /*
        |--------------------------------------------------------------------------
        | GET GENERATED PDF CONTENT
        |--------------------------------------------------------------------------
        */

        $bytes = $pdf->output();

        /*
        |--------------------------------------------------------------------------
        | STORE ORIGINAL PDF
        |--------------------------------------------------------------------------
        */

        $path = 'logistics/reports/' .
            $provider->id . '/' .
            $reference . '.pdf';

        Storage::disk('local')->put(
            $path,
            $bytes
        );

        /*
        |--------------------------------------------------------------------------
        | SAVE REPORT HISTORY TO MYSQL
        |--------------------------------------------------------------------------
        */

        DB::table('logistics_generated_reports')
            ->insert([
                'logistics_provider_id' => $provider->id,

                'generated_by' => Auth::id(),

                'report_type' => $filters['type'],

                'status_filter' => $filters['status'],

                'date_from' => $filters['from'],

                'date_to' => $filters['to'],

                'record_count' => $records->count(),

                'file_path' => $path,

                'sha256' => hash(
                    'sha256',
                    $bytes
                ),

                'created_at' => now(),

                'updated_at' => now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | DOWNLOAD GENERATED PORTRAIT PDF
        |--------------------------------------------------------------------------
        */

        return response()->streamDownload(
            fn () => print($bytes),
            $reference . '.pdf',
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET GENERATED REPORT HISTORY
    |--------------------------------------------------------------------------
    */

    public function history()
    {
        $provider = $this->provider();

        $reports = DB::table(
            'logistics_generated_reports'
        )
            ->where(
                'logistics_provider_id',
                $provider->id
            )
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id',
                'report_type',
                'status_filter',
                'date_from',
                'date_to',
                'record_count',
                'sha256',
                'created_at',
            ]);

        return response()->json([
            'reports' => $reports,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD PREVIOUSLY GENERATED PDF
    |--------------------------------------------------------------------------
    |
    | Previously generated reports retain their
    | original format and content.
    |
    */

    public function download(int $id)
    {
        $provider = $this->provider();

        /*
        |--------------------------------------------------------------------------
        | FIND REPORT BELONGING TO THIS PROVIDER
        |--------------------------------------------------------------------------
        */

        $report = DB::table(
            'logistics_generated_reports'
        )
            ->where('id', $id)
            ->where(
                'logistics_provider_id',
                $provider->id
            )
            ->first();

        abort_unless($report, 404);

        /*
        |--------------------------------------------------------------------------
        | CHECK ORIGINAL PDF FILE
        |--------------------------------------------------------------------------
        */

        $disk = Storage::disk('local');

        abort_unless(
            $disk->exists($report->file_path),
            404,
            'Original report file not found.'
        );

        $bytes = $disk->get(
            $report->file_path
        );

        /*
        |--------------------------------------------------------------------------
        | VERIFY PDF INTEGRITY
        |--------------------------------------------------------------------------
        */

        if (!hash_equals(
            $report->sha256,
            hash('sha256', $bytes)
        )) {
            abort(
                409,
                'Report integrity verification failed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DOWNLOAD ORIGINAL VERIFIED PDF
        |--------------------------------------------------------------------------
        */

        return response()->streamDownload(
            fn () => print($bytes),
            basename($report->file_path),
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}

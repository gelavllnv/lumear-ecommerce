<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class LogisticsOperationsController extends Controller
{
    private function provider(): object
    {
        $provider = DB::table('logistics_providers')
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        abort_unless($provider, 403, 'Approved logistics account required.');

        return $provider;
    }

    private function shipments(int $providerId)
    {
        return DB::table('shipments as s')
            ->join('seller_orders as so', 'so.id', '=', 's.seller_order_id')
            ->join('orders as o', 'o.id', '=', 'so.order_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'so.seller_id')
            ->leftJoin('riders as r', 'r.id', '=', 's.rider_id')
            ->leftJoin('users as rider_user', 'rider_user.id', '=', 'r.user_id')
            ->leftJoin('logistics_sorting as sorting', 'sorting.shipment_id', '=', 's.id')
            ->where('s.logistics_provider_id', $providerId)
            ->select(
                's.*',
                'o.reference',
                'o.shipping_address',
                'so.status as seller_status',
                'seller.name as seller_name',
                'rider_user.name as rider_name',
                'sorting.stage as sorting_stage'
            );
    }

    public function page(Request $request, string $module)
    {
        $allowed = [
            'incoming', 'sorting', 'assignment', 'monitoring',
            'rider-applications', 'riders', 'messages',
            'reports', 'account'
        ];

        abort_unless(in_array($module, $allowed, true), 404);

        $provider = $this->provider();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $records = collect();
        $riders = collect();
        $applications = collect();
        $messages = collect();

        if (in_array($module, [
            'incoming', 'sorting', 'assignment',
            'monitoring', 'reports'
        ], true)) {
            $query = $this->shipments($provider->id);

            if ($module === 'incoming') {
                $query->whereIn('s.status', ['picked_up', 'in_transit'])
                    ->whereNull('sorting.id');
            }

            if ($module === 'sorting') {
                $query->where('sorting.stage', 'received')
                    ->whereNotNull('sorting.verified_at');
            }

            if ($module === 'assignment') {
                $query->where('sorting.stage', 'sorted')
                    ->whereIn('s.status', [
                        'unassigned', 'assigned', 'picked_up', 'in_transit'
                    ]);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('s.tracking_code', 'like', "%{$search}%")
                        ->orWhere('o.reference', 'like', "%{$search}%");
                });
            }

            if ($status !== 'all') {
                $query->where('s.status', $status);
            }

            $records = $query
                ->orderByDesc('s.updated_at')
                ->limit(200)
                ->get();
        }

        if (in_array($module, ['assignment', 'riders'], true)) {
            $riders = DB::table('riders as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->where('r.logistics_provider_id', $provider->id)
                ->select('r.*', 'u.name', 'u.email')
                ->orderBy('u.name')
                ->get();
        }

        if ($module === 'rider-applications') {
            $applications = DB::table('logistics_rider_applications')
                ->where('logistics_provider_id', $provider->id)
                ->orderByDesc('id')
                ->get();
        }

        if ($module === 'messages') {
            $messages = DB::table('logistics_messages as m')
                ->leftJoin('users as sender', 'sender.id', '=', 'm.sender_id')
                ->leftJoin('users as recipient', 'recipient.id', '=', 'm.recipient_id')
                ->where('m.logistics_provider_id', $provider->id)
                ->where(function ($q) {
                    $q->where('m.sender_id', Auth::id())
                        ->orWhere('m.recipient_id', Auth::id());
                })
                ->select('m.*', 'sender.name as sender_name', 'recipient.name as recipient_name')
                ->orderByDesc('m.id')
                ->limit(200)
                ->get();
        }

        $counts = DB::table('shipments')
            ->where('logistics_provider_id', $provider->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('logistics.operations-workspace', compact(
            'provider', 'module', 'records', 'riders',
            'applications', 'messages', 'counts',
            'search', 'status'
        ));
    }

    private function event(int $shipmentId, string $status, string $note): void
    {
        $attempt = (int) DB::table('shipments')
            ->where('id', $shipmentId)
            ->value('attempts');

        DB::table('delivery_events')->insert([
            'shipment_id' => $shipmentId,
            'status' => $status,
            'attempt' => max(1, $attempt),
            'user_id' => Auth::id(),
            'note' => $note,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function receive(int $id)
    {
        $provider = $this->provider();

        DB::transaction(function () use ($id, $provider) {
            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where('logistics_provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404);

            abort_unless(
                in_array($shipment->status, ['picked_up', 'in_transit'], true),
                422,
                'Parcel has not been picked up.'
            );

            $existing = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->first();

            abort_if($existing, 422, 'Parcel already received.');

            DB::table('logistics_sorting')->insert([
                'shipment_id' => $id,
                'logistics_provider_id' => $provider->id,
                'stage' => 'received',
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->event($id, 'sorting_received', 'Received at sorting center');
        });

        return back()->with('success', 'Parcel received.');
    }

    public function sort(int $id)
    {
        $provider = $this->provider();

        DB::transaction(function () use ($id, $provider) {
            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where('logistics_provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404);

            abort_unless(
                in_array($shipment->status, ['picked_up', 'in_transit'], true),
                422
            );

            $sorting = DB::table('logistics_sorting')
                ->where('shipment_id', $id)
                ->where('logistics_provider_id', $provider->id)
                ->first();

            abort_unless($sorting, 422, 'Receive parcel first.');
            abort_unless(
                $sorting->stage === 'received' && $sorting->verified_at,
                422,
                'Parcel must be verified before sorting.'
            );

            DB::table('logistics_sorting')
                ->where('id', $sorting->id)
                ->update([
                    'stage' => 'sorted',
                    'sorted_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->event($id, 'sorting_completed', 'Parcel sorting completed');
        });

        return back()->with('success', 'Parcel sorted.');
    }

    public function assign(Request $request, int $id)
    {
        $data = $request->validate([
            'rider_id' => ['required', 'integer'],
        ]);

        $provider = $this->provider();

        DB::transaction(function () use ($data, $provider, $id) {
            $shipment = DB::table('shipments')
                ->where('id', $id)
                ->where('logistics_provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            abort_unless($shipment, 404);

            abort_unless(
                in_array($shipment->status, [
                    'unassigned', 'assigned', 'picked_up', 'in_transit'
                ], true),
                422,
                'Shipment cannot be assigned.'
            );

            abort_unless(
                DB::table('logistics_sorting')
                    ->where('shipment_id', $shipment->id)
                    ->where('logistics_provider_id', $provider->id)
                    ->where('stage', 'sorted')
                    ->exists(),
                422,
                'Parcel must be received and sorted before assigning a rider.'
            );

            $rider = DB::table('riders')
                ->where('id', $data['rider_id'])
                ->where('logistics_provider_id', $provider->id)
                ->where('is_active', 1)
                ->first();

            abort_unless($rider, 422, 'Rider is not active.');

            DB::table('shipments')
                ->where('id', $id)
                ->update([
                    'rider_id' => $rider->id,
                    'status' => $shipment->status === 'unassigned'
                        ? 'assigned'
                        : $shipment->status,
                    'updated_at' => now(),
                ]);

            $this->event(
                $id,
                'rider_assigned',
                'Assigned to rider #' . $rider->id
            );
        });

        return back()->with('success', 'Rider assigned.');
    }

    public function riderStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $provider = $this->provider();

        $rider = DB::table('riders')
            ->where('id', $id)
            ->where('logistics_provider_id', $provider->id)
            ->first();

        abort_unless($rider, 404);

        DB::table('riders')
            ->where('id', $id)
            ->update([
                'is_active' => $data['is_active'],
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Rider status updated.');
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'vehicle_type' => 'required|string|max:80',
            'plate_no' => 'required|string|max:80',
        ]);

        $provider = $this->provider();

        DB::table('logistics_rider_applications')->insert([
            ...$data,
            'logistics_provider_id' => $provider->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Application submitted.');
    }

    public function decide(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $provider = $this->provider();

        DB::transaction(function () use ($provider, $id, $data) {
            $application = DB::table('logistics_rider_applications')
                ->where('id', $id)
                ->where('logistics_provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            abort_unless($application, 404);
            abort_unless($application->status === 'pending', 422);

            if ($data['decision'] === 'approved') {
                $user = DB::table('users')
                    ->where('email', $application->email)
                    ->first();

                abort_unless(
                    $user,
                    422,
                    'Rider must register a LUMEAR user account first.'
                );

                $existing = DB::table('riders')
                    ->where('user_id', $user->id)
                    ->first();

                abort_if($existing, 422, 'User already registered as a rider.');

                DB::table('riders')->insert([
                    'user_id' => $user->id,
                    'logistics_provider_id' => $provider->id,
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
                    'is_active' => 1,
                    'management_status' => 'Available',
                    'management_remarks' => 'Newly approved rider.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('logistics_rider_applications')
                ->where('id', $id)
                ->update([
                    'status' => $data['decision'],
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'Application reviewed.');
    }

    public function message(Request $request)
    {
        $data = $request->validate([
            'recipient_email' => 'required|email',
            'body' => 'required|string|max:3000',
        ]);

        $provider = $this->provider();

        $recipient = DB::table('users')
            ->where('email', $data['recipient_email'])
            ->first();

        abort_unless($recipient, 422, 'Recipient not found.');

        DB::table('logistics_messages')->insert([
            'logistics_provider_id' => $provider->id,
            'sender_id' => Auth::id(),
            'recipient_id' => $recipient->id,
            'body' => $data['body'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Message saved.');
    }

    public function account(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_phone' => 'required|string|max:30',
        ]);

        $provider = $this->provider();

        DB::table('logistics_providers')
            ->where('id', $provider->id)
            ->update([
                'name' => $data['name'],
                'contact_phone' => $data['contact_phone'],
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Business profile updated.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        abort_unless(
            Hash::check($data['current_password'], $user->password),
            422,
            'Current password incorrect.'
        );

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'password' => Hash::make($data['password']),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Password updated.');
    }

    public function report(Request $request)
    {
        $provider = $this->provider();

        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $rows = DB::table('shipments')
            ->where('logistics_provider_id', $provider->id)
            ->whereBetween('created_at', [
                $data['from'] . ' 00:00:00',
                $data['to'] . ' 23:59:59',
            ])
            ->select('tracking_code', 'status', 'created_at', 'updated_at')
            ->orderBy('created_at')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Tracking Code', 'Status', 'Created', 'Updated'
            ]);

            foreach ($rows as $row) {
                fputcsv($file, [
                    $row->tracking_code,
                    $row->status,
                    $row->created_at,
                    $row->updated_at,
                ]);
            }

            fclose($file);
        }, 'lumear-logistics-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}

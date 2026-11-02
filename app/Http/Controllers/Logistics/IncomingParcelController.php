<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncomingParcelController extends Controller
{
    private const SESSION_KEY = 'lumear_incoming_parcels';

    private function parcels(Request $request): array
    {
        return $request->session()->get(self::SESSION_KEY, []);
    }

    public function index(Request $request)
    {
        $all = $this->parcels($request);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $parcels = array_values(array_filter($all, function ($parcel) use ($search, $status) {
            $matchesStatus = $status === 'all'
                || $parcel['status'] === $status;

            $matchesSearch = $search === ''
                || str_contains(strtolower(
                    $parcel['tracking'] . ' ' .
                    $parcel['recipient'] . ' ' .
                    $parcel['sender'] . ' ' .
                    $parcel['destination']
                ), strtolower($search));

            return $matchesStatus && $matchesSearch;
        }));

        $stats = [
            'total' => count($all),
            'awaiting' => count(array_filter(
                $all, fn ($p) => $p['status'] === 'Awaiting Arrival'
            )),
            'received' => count(array_filter(
                $all, fn ($p) => $p['status'] === 'Received'
            )),
            'today' => count(array_filter(
                $all, fn ($p) =>
                    !empty($p['received_at'])
                    && str_starts_with($p['received_at'], now()->toDateString())
            )),
        ];

        return view('logistics.incoming.index', compact(
            'parcels', 'stats', 'search', 'status'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tracking' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'sender' => ['required', 'string', 'max:120'],
            'recipient' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'regex:/^09[0-9]{9}$/'],
            'destination' => ['required', 'string', 'max:200'],
            'courier' => ['required', 'string', 'max:100'],
            'parcel_type' => ['required', Rule::in([
                'Standard', 'Fragile', 'Documents', 'Bulky'
            ])],
            'weight' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $all = $this->parcels($request);
        $tracking = strtoupper(trim($validated['tracking']));

        foreach ($all as $parcel) {
            if ($parcel['tracking'] === $tracking) {
                return back()->withErrors([
                    'tracking' => 'This tracking number is already registered.',
                ])->withInput();
            }
        }

        $all[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tracking' => $tracking,
            'sender' => trim($validated['sender']),
            'recipient' => trim($validated['recipient']),
            'contact' => $validated['contact'],
            'destination' => trim($validated['destination']),
            'courier' => trim($validated['courier']),
            'parcel_type' => $validated['parcel_type'],
            'weight' => (float) $validated['weight'],
            'notes' => $validated['notes'] ?? '',
            'status' => 'Awaiting Arrival',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'received_at' => null,
            'received_by' => null,
        ];

        $request->session()->put(self::SESSION_KEY, $all);

        return redirect()
            ->route('logistics.incoming.index')
            ->with('success', "Parcel {$tracking} registered successfully.");
    }

    public function receive(Request $request, string $id)
    {
        $all = $this->parcels($request);
        $found = false;
        $tracking = '';

        foreach ($all as &$parcel) {
            if ($parcel['id'] !== $id) {
                continue;
            }

            $found = true;
            $tracking = $parcel['tracking'];

            if ($parcel['status'] === 'Received') {
                return back()->withErrors([
                    'receive' => 'This parcel has already been received. Duplicate scan blocked.',
                ]);
            }

            $parcel['status'] = 'Received';
            $parcel['received_at'] = now()->format('Y-m-d H:i:s');
            $parcel['received_by'] = 'Logistics Operator (Demo)';
            break;
        }

        unset($parcel);

        abort_unless($found, 404);

        $request->session()->put(self::SESSION_KEY, $all);

        return redirect()
            ->route('logistics.incoming.index')
            ->with('success', "{$tracking} successfully scanned and received.");
    }

    public function show(Request $request, string $id)
    {
        foreach ($this->parcels($request) as $parcel) {
            if ($parcel['id'] === $id) {
                return response()->json($parcel);
            }
        }

        abort(404, 'Parcel not found.');
    }

    public function export(Request $request)
    {
        $parcels = $this->parcels($request);

        return response()->streamDownload(function () use ($parcels) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Tracking Number', 'Sender', 'Recipient',
                'Contact', 'Destination', 'Courier',
                  'Parcel Type', 'Weight (kg)', 'Status',
                'Registered At', 'Received At', 'Received By'
            ]);

            foreach ($parcels as $p) {
                fputcsv($out, [
                    $p['tracking'], $p['sender'], $p['recipient'],
                    $p['contact'], $p['destination'], $p['courier'],
                    $p['parcel_type'], $p['weight'], $p['status'],
                    $p['created_at'], $p['received_at'],
                    $p['received_by']
                ]);
            }

            fclose($out);
        }, 'lumear-incoming-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

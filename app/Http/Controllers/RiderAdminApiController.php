<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RiderAdminApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($data['email'])])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->hasRole('rider_admin') || $user->is_suspended) {
            throw ValidationException::withMessages(['email' => 'Invalid administrator credentials.']);
        }
        $token = Str::random(80);
        DB::table('mobile_api_tokens')->insert([
            'user_id' => $user->id,
            'role' => 'rider_admin',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['token' => $token, 'role' => 'rider_admin', 'name' => $user->name]);
    }

    public function riders(): JsonResponse
    {
        $riders = DB::table('riders as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('logistics_providers as lp', 'lp.id', '=', 'r.logistics_provider_id')
            ->select('r.*', 'u.name', 'u.email', 'u.phone', 'u.date_of_birth', 'lp.name as hub_name')
            ->orderByDesc('r.id')
            ->get()
            ->map(fn ($rider): array => [
                'id' => 'RDR-' . str_pad((string) $rider->id, 3, '0', STR_PAD_LEFT),
                'name' => $rider->name,
                'email' => $rider->email,
                'phone' => $rider->phone ?? '',
                'status' => $rider->management_status ?? 'Offline',
                'vehicle' => $rider->vehicle_type ?? '',
                'jobType' => $rider->job_type ?? '',
                'hub' => $rider->hub_name ?? '',
                'municipality' => $rider->municipality ?? '',
                'address' => implode(', ', array_filter([$rider->house_unit ?? '', $rider->street ?? '', $rider->barangay ?? '', $rider->municipality ?? '', $rider->province ?? ''])),
                'plate' => $rider->plate_no ?? '',
                'licenseNumber' => $rider->license_no ?? '',
                'birthday' => $rider->date_of_birth ?? '',
                'sex' => $rider->sex ?? '',
                'joined' => $rider->created_at,
            ]);

        return response()->json(['riders' => $riders]);
    }

    public function reports(): JsonResponse
    {
        $reports = DB::table('rider_bug_reports as br')
            ->join('riders as r', 'r.id', '=', 'br.rider_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->select('br.*', 'r.id as rider_database_id', 'r.vehicle_type', 'r.job_type', 'r.municipality', 'r.logistics_provider_id', 'u.name as rider_name', 'u.email as rider_email', 'u.phone as rider_phone')
            ->orderByDesc('br.created_at')
            ->get()
            ->map(fn ($report): array => [
                'id' => 'BUG-' . str_pad((string) $report->id, 6, '0', STR_PAD_LEFT),
                'riderId' => 'RDR-' . str_pad((string) $report->rider_database_id, 3, '0', STR_PAD_LEFT),
                'riderName' => $report->rider_name,
                'riderEmail' => $report->rider_email,
                'riderPhone' => $report->rider_phone ?? '',
                'vehicle' => $report->vehicle_type ?? '',
                'jobType' => $report->job_type ?? '',
                'municipality' => $report->municipality ?? '',
                'affectedScreen' => $report->affected_screen,
                'appVersion' => $report->app_version ?? '',
                'deviceInfo' => $report->device_info ?? '',
                'description' => $report->description,
                'expectedResult' => $report->expected_result ?? '',
                'evidencePaths' => collect(json_decode($report->evidence_paths ?? '[]', true) ?: [])->map(fn (string $path): string => '/rider/admin/bug-reports/' . $report->id . '/evidence/' . basename($path))->all(),
                'reportedAt' => $report->created_at,
                'status' => $report->status,
                'adminResponse' => $report->admin_response ?? '',
            ]);

        return response()->json(['reports' => $reports]);
    }

    public function submitReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'affected_screen' => ['required', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'device_info' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'expected_result' => ['nullable', 'string', 'max:3000'],
            'evidence.*' => ['required', 'image', 'max:10240'],
        ]);
        $rider = $request->attributes->get('rider_api_rider');
        $paths = [];
        foreach ($request->file('evidence', []) as $file) {
            $paths[] = $file->store('rider-bug-reports/' . $rider->id, 'local');
        }
        $id = DB::table('rider_bug_reports')->insertGetId([
            'rider_id' => $rider->id,
            'affected_screen' => $data['affected_screen'],
            'app_version' => $data['app_version'] ?? null,
            'device_info' => $data['device_info'] ?? null,
            'description' => $data['description'],
            'expected_result' => $data['expected_result'] ?? null,
            'evidence_paths' => json_encode($paths),
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => 'BUG-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), 'message' => 'Bug report submitted.'], 201);
    }

    public function updateReport(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Pending', 'Under Review', 'Resolved'])],
            'admin_response' => ['nullable', 'string', 'max:3000'],
        ]);
        abort_unless(DB::table('rider_bug_reports')->where('id', $id)->exists(), 404);
        DB::table('rider_bug_reports')->where('id', $id)->update([
            'status' => $data['status'],
            'admin_response' => trim($data['admin_response'] ?? ''),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Bug report updated.']);
    }

    public function evidence(Request $request, int $id, string $file)
    {
        $report = DB::table('rider_bug_reports')->where('id', $id)->first();
        abort_unless($report, 404);
        $paths = json_decode($report->evidence_paths ?? '[]', true) ?: [];
        $path = collect($paths)->first(fn (string $item): bool => basename($item) === $file);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}

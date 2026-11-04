<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LogisticsAccountController extends Controller
{
    private function account(): array
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $provider = DB::table('logistics_providers')
            ->where('user_id', $user->id)
            ->first();

        abort_unless($provider, 403, 'Logistics account not found.');

        abort_if(
            $provider->status !== 'approved',
            403,
            'Your Logistics account is not approved.'
        );

        $application = DB::table('logistics_applications')
            ->where('logistics_provider_id', $provider->id)
            ->first();

        abort_unless($application, 404, 'Registration record not found.');

        return [$user, $provider, $application];
    }

    private function logActivity(
        int $providerId,
        int $userId,
        string $action,
        string $description
    ): void {
        DB::table('logistics_account_activities')->insert([
            'logistics_provider_id' => $providerId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    public function show()
    {
        [$user, $provider, $application] = $this->account();

        return response()->json([
            'success' => true,
            'profile' => [
                'accountId' => 'LOG-' . str_pad(
                    $provider->id,
                    5,
                    '0',
                    STR_PAD_LEFT
                ),
                'firstName' => $application->first_name,
                'lastName' => $application->last_name,
                'middleInitial' => $application->middle_initial,
                'sex' => $application->sex,
                'birthday' => $application->birthday,
                'email' => $user->email,
                'emailVerified' => $user->email_verified_at !== null,
                'phone' => $user->phone,
                'businessName' => $provider->name,
                'status' => $provider->status,
                'region' => $application->region,
                'province' => $provider->province_name
                    ?? $application->province,
                'municipality' => $provider->municipality_name
                    ?? $application->city,
                'barangay' => $application->barangay,
                'postalCode' => $application->postal_code,
                'houseNumber' => $application->house_number,
                'street' => $application->street,
                'secNumber' => $application->sec_number,
                'tin' => $application->tin,
                'documents' => [
                    'validId' => [
                        'available' => (bool) $application->valid_id_path,
                        'url' => route(
                            'logistics.account.document',
                            ['type' => 'valid-id']
                        ),
                    ],
                    'businessPermit' => [
                        'available' => (bool) $application->business_permit_path,
                        'url' => route(
                            'logistics.account.document',
                            ['type' => 'business-permit']
                        ),
                    ],
                ],
            ],
        ]);
    }

    public function updatePersonal(Request $request)
    {
        [$user, $provider, $application] = $this->account();

        $data = $request->validate([
            'first_name' => [
                'required', 'string', 'min:2', 'max:50',
                'regex:/^[\pL]+(?:[ .\'-][\pL]+)*\.?$/u',
            ],
            'last_name' => [
                'required', 'string', 'min:2', 'max:50',
                'regex:/^[\pL]+(?:[ .\'-][\pL]+)*\.?$/u',
            ],
            'middle_initial' => [
                'nullable', 'regex:/^\pL$/u',
            ],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'birthday' => [
                'required', 'date', 'before_or_equal:-18 years',
            ],
            'phone' => [
                'required',
                'regex:/^(09[0-9]{9}|\+639[0-9]{9})$/',
            ],
        ]);

        $data['first_name'] = trim($data['first_name']);
        $data['last_name'] = trim($data['last_name']);
        $data['middle_initial'] = isset($data['middle_initial'])
            ? strtoupper(trim($data['middle_initial']))
            : null;

        $fullName = $data['first_name'] . ' ' . $data['last_name'];

        DB::transaction(function () use (
            $user, $provider, $application, $data, $fullName
        ) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'name' => $fullName,
                    'phone' => $data['phone'],
                    'updated_at' => now(),
                ]);

            DB::table('logistics_applications')
                ->where('id', $application->id)
                ->update([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_initial' => $data['middle_initial'],
                    'sex' => $data['sex'],
                    'birthday' => $data['birthday'],
                    'mobile' => $data['phone'],
                    'updated_at' => now(),
                ]);

            DB::table('logistics_providers')
                ->where('id', $provider->id)
                ->update([
                    'contact_phone' => $data['phone'],
                    'updated_at' => now(),
                ]);

            $this->logActivity(
                $provider->id,
                $user->id,
                'personal_updated',
                'Personal information was updated.'
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Personal information saved successfully.',
        ]);
    }

    public function updateBusiness(Request $request)
    {
        [$user, $provider, $application] = $this->account();

        $data = $request->validate([
            'business_name' => [
                'required', 'string', 'max:150', 'regex:/\S/u',
            ],
            'barangay' => [
                'required', 'string', 'max:150', 'regex:/\S/u',
            ],
            'postal_code' => [
                'required', 'regex:/^[0-9]{4}$/',
            ],
            'house_number' => [
                'required', 'string', 'max:100', 'regex:/\S/u',
            ],
            'street' => [
                'required', 'string', 'max:150', 'regex:/\S/u',
            ],
        ]);

        foreach ([
            'business_name',
            'barangay',
            'house_number',
            'street',
        ] as $field) {
            $data[$field] = trim($data[$field]);
        }

        DB::transaction(function () use (
            $user, $provider, $application, $data
        ) {
            DB::table('logistics_providers')
                ->where('id', $provider->id)
                ->update([
                    'name' => $data['business_name'],
                    'updated_at' => now(),
                ]);

            DB::table('logistics_applications')
                ->where('id', $application->id)
                ->update([
                    'company_name' => $data['business_name'],
                    'barangay' => $data['barangay'],
                    'postal_code' => $data['postal_code'],
                    'house_number' => $data['house_number'],
                    'street' => $data['street'],
                    'updated_at' => now(),
                ]);

            $this->logActivity(
                $provider->id,
                $user->id,
                'business_updated',
                'Sorting center business information was updated.'
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Business information saved successfully.',
        ]);
    }

    public function updatePassword(Request $request)
    {
        [$user, $provider] = $this->account();

        $data = $request->validate([
            'current_password' => [
                'required', 'string', 'current_password',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                'confirmed',
                'different:current_password',
            ],
        ]);

        DB::transaction(function () use ($user, $provider, $data) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make($data['password']),
                    'remember_token' => null,
                    'updated_at' => now(),
                ]);

            $this->logActivity(
                $provider->id,
                $user->id,
                'password_changed',
                'Account password was changed.'
            );
        });

        // Preserve the current login session while invalidating
        // other sessions if the app uses Laravel's database session driver.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    public function activity()
    {
        [$user, $provider] = $this->account();

        $activities = DB::table('logistics_account_activities')
            ->where('logistics_provider_id', $provider->id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id',
                'action',
                'description',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'activities' => $activities,
        ]);
    }

    public function document(string $type)
    {
        [, , $application] = $this->account();

        $path = match ($type) {
            'valid-id' => $application->valid_id_path,
            'business-permit' => $application->business_permit_path,
            default => abort(404),
        };

        abort_unless(
            $path && Storage::disk('local')->exists($path),
            404,
            'Document not found.'
        );

        $allowedPrefixes = [
            'valid-id' => 'logistics/valid-ids/',
            'business-permit' => 'logistics/business-permits/',
        ];

        abort_unless(
            str_starts_with($path, $allowedPrefixes[$type]),
            403
        );

        $mime = Storage::disk('local')->mimeType($path);

        abort_unless(
            in_array($mime, [
                'application/pdf',
                'image/jpeg',
                'image/png',
            ], true),
            415
        );

        return response()->file(
            Storage::disk('local')->path($path),
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
                'Content-Disposition' => 'inline',
            ]
        );
    }
}

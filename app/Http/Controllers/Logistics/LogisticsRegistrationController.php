<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsApplication;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LogisticsRegistrationController extends Controller
{
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'company_name' => ['required', 'string', 'max:150', 'regex:/\S/u'],
                'sec_number' => ['required', 'regex:/^[A-Za-z]{2}[0-9]{9}$/'],
                'tin' => ['required', 'regex:/^[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}$/'],
            ],

            2 => [
                'first_name' => [
                    'required', 'string', 'min:2', 'max:50',
                    'regex:/^[\pL]+(?:[ .\'-][\pL]+)*\.?$/u',
                ],
                'last_name' => [
                    'required', 'string', 'min:2', 'max:50',
                    'regex:/^[\pL]+(?:[ .\'-][\pL]+)*\.?$/u',
                ],
                'middle_initial' => ['nullable', 'regex:/^\pL$/u'],
                'sex' => ['required', Rule::in(['Male', 'Female'])],
                'birthday' => ['required', 'date', 'before_or_equal:-18 years'],
                'email' => ['required', 'email', 'max:150'],
                'mobile' => [
                    'required',
                    'regex:/^(09[0-9]{9}|\+639[0-9]{9})$/',
                ],
            ],

            3 => [
                'region' => ['required', 'string', 'max:150'],
                'province' => ['nullable', 'string', 'max:150'],
                'city' => ['required', 'string', 'max:150'],
                'municipality_psgc_code' => [
                    'required', 'string', 'regex:/^[0-9]{9,10}$/',
                ],
                'barangay' => ['required', 'string', 'max:150'],
                'postal_code' => ['required', 'regex:/^[0-9]{4}$/'],
                'house_number' => ['required', 'string', 'max:100', 'regex:/\S/u'],
                'street' => ['required', 'string', 'max:150', 'regex:/\S/u'],
            ],

            4 => [
                'valid_id' => [
                    'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120',
                ],
                'business_permit' => [
                    'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120',
                ],
            ],

            5 => [
                'password' => [
                    'required', 'string', 'min:8',
                    'regex:/[A-Z]/',
                    'regex:/[a-z]/',
                    'regex:/[0-9]/',
                    'regex:/[^A-Za-z0-9]/',
                    'confirmed',
                ],
            ],

            default => throw ValidationException::withMessages([
                'step' => 'Invalid registration step.',
            ]),
        };
    }

    private function messages(): array
    {
        return [
            'first_name.regex' => 'Enter a valid first name.',
            'last_name.regex' => 'Enter a valid last name.',
            'mobile.regex' => 'Enter 09XXXXXXXXX or +639XXXXXXXXX.',
            'sec_number.regex' => 'SEC number must contain two letters and nine digits.',
            'tin.regex' => 'TIN must follow 000-000-000-000.',
            'postal_code.regex' => 'Postal code must contain four digits.',
            'password.confirmed' => 'Passwords do not match.',
            'municipality_psgc_code.required' => 'Please select a valid city or municipality.',
        ];
    }

    private function isMunicipalityTaken(string $code): bool
    {
        return DB::table('logistics_providers')
            ->where('status', 'approved')
            ->where('municipality_psgc_code', $code)
            ->exists();
    }

    public function municipalityAvailability(Request $request)
    {
        $data = $request->validate([
            'municipality_psgc_code' => [
                'required', 'regex:/^[0-9]{9,10}$/',
            ],
        ]);

        $taken = $this->isMunicipalityTaken(
            $data['municipality_psgc_code']
        );

        return response()->json([
            'available' => !$taken,
            'message' => $taken
                ? 'This city or municipality already has an approved Logistics provider.'
                : 'This city or municipality is available.',
        ]);
    }

    public function validateStep(Request $request)
    {
        $step = (int) $request->input('step');

        $data = $request->all();

        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = Str::lower(trim($data['email']));
        }

        Validator::make(
            $data,
            $this->rulesForStep($step),
            $this->messages()
        )->validate();

        if ($step === 3 &&
            $this->isMunicipalityTaken($data['municipality_psgc_code'])) {
            throw ValidationException::withMessages([
                'city' => 'This city or municipality already has an approved Logistics provider.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Validation passed.',
        ]);
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        $email = Str::lower(trim($data['email']));

        if (DB::table('users')->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }

        $rateKey = 'logistics-otp:' .
            hash('sha256', $email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return response()->json([
                'message' => 'Too many OTP requests. Please try again later.',
            ], 429);
        }

        $lastSent = (int) $request->session()->get(
            'logistics_otp_last_sent', 0
        );

        if (time() - $lastSent < 60) {
            return response()->json([
                'message' => 'Please wait before requesting another code.',
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::raw(
                "Your LUMEAR Logistics verification code is: {$code}\n\n" .
                "This code expires in 5 minutes.\n\n" .
                "If you did not request this code, ignore this email.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('LUMEAR Logistics Email Verification');
                }
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to send the verification email.',
            ], 503);
        }

        RateLimiter::hit($rateKey, 600);

        $request->session()->put([
            'logistics_otp_email' => $email,
            'logistics_otp_hash' => hash('sha256', $code),
            'logistics_otp_expires' => time() + 300,
            'logistics_otp_last_sent' => time(),
            'logistics_otp_attempts' => 0,
            'logistics_otp_verified' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'expires_in' => 300,
            'resend_after' => 60,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = Str::lower(trim($data['email']));

        $storedEmail = $request->session()->get('logistics_otp_email');
        $storedHash = $request->session()->get('logistics_otp_hash');
        $expiresAt = (int) $request->session()->get('logistics_otp_expires', 0);
        $attempts = (int) $request->session()->get('logistics_otp_attempts', 0);

        if (!$storedEmail || !$storedHash ||
            !hash_equals($storedEmail, $email)) {
            return response()->json([
                'message' => 'No verification code was requested for this email.',
            ], 422);
        }

        if (time() > $expiresAt) {
            $request->session()->forget('logistics_otp_hash');

            return response()->json([
                'message' => 'Verification code expired. Request another.',
            ], 422);
        }

        if ($attempts >= 5) {
            $request->session()->forget('logistics_otp_hash');

            return response()->json([
                'message' => 'Too many incorrect attempts.',
            ], 429);
        }

        $request->session()->increment('logistics_otp_attempts');

        if (!hash_equals($storedHash, hash('sha256', $data['otp']))) {
            return response()->json([
                'message' => 'Incorrect verification code.',
            ], 422);
        }

        $request->session()->put('logistics_otp_verified', true);

        $request->session()->forget([
            'logistics_otp_hash',
            'logistics_otp_expires',
            'logistics_otp_attempts',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email successfully verified.',
        ]);
    }

    public function submit(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'company_name' => trim((string) $request->input('company_name')),
            'sec_number' => strtoupper(trim((string) $request->input('sec_number'))),
            'tin' => trim((string) $request->input('tin')),
            'mobile' => trim((string) $request->input('mobile')),
        ]);

        $email = $request->input('email');

        if ($request->session()->get('logistics_otp_verified') !== true ||
            $request->session()->get('logistics_otp_email') !== $email) {
            throw ValidationException::withMessages([
                'email' => 'Verify your email using OTP before submitting.',
            ]);
        }

        $rules = array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
            $this->rulesForStep(4),
            $this->rulesForStep(5),
            ['terms' => ['accepted']]
        );

        $rules['email'][] = Rule::unique('users', 'email');
        $rules['email'][] = Rule::unique('logistics_applications', 'email');
        $rules['sec_number'][] = Rule::unique('logistics_applications', 'sec_number');
        $rules['tin'][] = Rule::unique('logistics_applications', 'tin');

        $data = Validator::make(
            $request->all(),
            $rules,
            $this->messages()
        )->validate();

        $municipalityCode = $data['municipality_psgc_code'];

        if ($this->isMunicipalityTaken($municipalityCode)) {
            throw ValidationException::withMessages([
                'city' => 'This city or municipality is already assigned.',
            ]);
        }

        $roleId = DB::table('roles')
            ->where('name', 'logistics')
            ->value('id');

        if (!$roleId) {
            return response()->json([
                'message' => 'Logistics role is missing. Contact the administrator.',
            ], 500);
        }

        $validIdPath = null;
        $permitPath = null;

        try {
            $validIdPath = $request->file('valid_id')
                ->store('logistics/valid-ids', 'local');

            $permitPath = $request->file('business_permit')
                ->store('logistics/business-permits', 'local');

            if (!$validIdPath || !$permitPath) {
                throw new \RuntimeException('Document storage failed.');
            }

            $applicationId = DB::transaction(function () use (
                $data,
                $email,
                $roleId,
                $municipalityCode,
                $validIdPath,
                $permitPath
            ) {
                $userId = DB::table('users')->insertGetId([
                    'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make($data['password']),
                    'phone' => $data['mobile'],
                    'is_suspended' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('role_user')->insert([
                    'role_id' => $roleId,
                    'user_id' => $userId,
                ]);

                $slug = Str::slug($data['company_name']);

                if ($slug === '') {
                    $slug = 'logistics';
                }

                // A unique suffix avoids conflicts with existing company slugs.
                $slug .= '-' . Str::lower(Str::random(10));

                $providerId = DB::table('logistics_providers')
                    ->insertGetId([
                        'user_id' => $userId,
                        'name' => $data['company_name'],
                        'slug' => $slug,
                        'status' => 'pending',
                        'contact_phone' => $data['mobile'],
                        'municipality_psgc_code' => $municipalityCode,
                        'municipality_name' => $data['city'],
                        'province_name' => $data['province'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $application = LogisticsApplication::create([
                    'logistics_provider_id' => $providerId,
                    'company_name' => $data['company_name'],
                    'sec_number' => $data['sec_number'],
                    'tin' => $data['tin'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_initial' => $data['middle_initial'] ?? null,
                    'sex' => $data['sex'],
                    'birthday' => $data['birthday'],
                    'email' => $email,
                    'mobile' => $data['mobile'],
                    'region' => $data['region'],
                    'province' => $data['province'] ?? null,
                    'city' => $data['city'],
                    'municipality_psgc_code' => $municipalityCode,
                    'barangay' => $data['barangay'],
                    'house_number' => $data['house_number'],
                    'street' => $data['street'],
                    'postal_code' => $data['postal_code'],
                    'valid_id_path' => $validIdPath,
                    'business_permit_path' => $permitPath,
                    'email_verified_at' => now(),
                    'status' => 'pending',
                ]);

                return $application->id;
            });

        } catch (\Throwable $exception) {
            if ($validIdPath) {
                Storage::disk('local')->delete($validIdPath);
            }

            if ($permitPath) {
                Storage::disk('local')->delete($permitPath);
            }

            if ($exception instanceof QueryException &&
                (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw ValidationException::withMessages([
                    'registration' => 'Some registration details are already in use.',
                ]);
            }

            report($exception);

            return response()->json([
                'message' => 'Unable to save your application. Please try again.',
            ], 500);
        }

        $request->session()->forget([
            'logistics_otp_email',
            'logistics_otp_hash',
            'logistics_otp_expires',
            'logistics_otp_last_sent',
            'logistics_otp_attempts',
            'logistics_otp_verified',
        ]);

        $request->session()->put(
            'logistics_application_submitted',
            $applicationId
        );

        return response()->json([
            'success' => true,
            'message' => 'Application submitted. Awaiting Admin approval.',
            'redirect' => route('logistics.pending'),
        ], 201);
    }
}

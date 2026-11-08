<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LogisticsHeadAdminSalaryMatrixController extends Controller
{
    public function index()
    {
        $tiers = DB::table('logistics_salary_location_tiers')
            ->orderBy('code')
            ->get();

        $providers = DB::table('logistics_providers as lp')
            ->leftJoin(
                'logistics_salary_location_tiers as tier',
                'tier.id',
                '=',
                'lp.salary_location_tier_id'
            )
            ->where('lp.status', 'approved')
            ->select(
                'lp.id',
                'lp.name',
                'lp.municipality_name',
                'lp.province_name',
                'lp.salary_location_tier_id',
                'tier.name as tier_name'
            )
            ->orderBy('lp.province_name')
            ->orderBy('lp.municipality_name')
            ->get();

        $matrices = DB::table(
            'logistics_admin_salary_matrix as matrix'
        )
            ->leftJoin(
                'logistics_salary_location_tiers as tier',
                'tier.id',
                '=',
                'matrix.location_tier_id'
            )
            ->leftJoin(
                'users as user',
                'user.id',
                '=',
                'matrix.set_by'
            )
            ->where('matrix.is_active', true)
            ->select(
                'matrix.*',
                'tier.name as tier_name',
                'user.name as set_by_name'
            )
            ->orderBy('matrix.role')
            ->orderBy('matrix.location_tier_id')
            ->get();

        return view(
            'logistics.head-admin.salary-matrix',
            compact(
                'tiers',
                'providers',
                'matrices'
            )
        );
    }

    public function assignTier(
        Request $request,
        int $id
    ) {
        $data = $request->validate([
            'salary_location_tier_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'logistics_salary_location_tiers',
                    'id'
                ),
            ],
        ]);

        $provider = DB::table('logistics_providers')
            ->where('id', $id)
            ->where('status', 'approved')
            ->first();

        abort_unless(
            $provider,
            404,
            'Approved Logistics hub not found.'
        );

        DB::table('logistics_providers')
            ->where('id', $id)
            ->update([
                'salary_location_tier_id' => $data['salary_location_tier_id'] ?? null,

                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            'Hub salary tier updated.'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role' => [
                'required',
                Rule::in([
                    'logistics_admin',
                    'head_super_admin',
                ]),
            ],

            'location_tier_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'logistics_salary_location_tiers',
                    'id'
                ),
            ],

            'standard_monthly_salary' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000',
            ],

            'effective_from' => [
                'required',
                'date_format:Y-m-d',
            ],
        ]);

        $standardMinor = (int) round(
            ((float) $data['standard_monthly_salary']) * 100
        );

        $tier = null;

        if ($data['role'] === 'logistics_admin') {
            if (! $data['location_tier_id']) {
                throw ValidationException::withMessages([
                    'location_tier_id' => 'Choose a city/bayan tier for a Logistics Admin salary.',
                ]);
            }

            $tier = DB::table(
                'logistics_salary_location_tiers'
            )
                ->where('id', $data['location_tier_id'])
                ->first();

            if (! $tier) {
                abort(404, 'Salary tier not found.');
            }

            if (
                $standardMinor
                < (int) $tier->minimum_monthly_salary_minor
            ) {
                throw ValidationException::withMessages([
                    'standard_monthly_salary' => 'The salary is below this tier’s allowed minimum.',
                ]);
            }

            if (
                $tier->maximum_monthly_salary_minor !== null
                && $standardMinor
                    > (int) $tier->maximum_monthly_salary_minor
            ) {
                throw ValidationException::withMessages([
                    'standard_monthly_salary' => 'The salary is above this tier’s allowed maximum.',
                ]);
            }
        }

        DB::transaction(function () use (
            $data,
            $standardMinor,
            $tier
        ) {
            $oldMatrix = DB::table(
                'logistics_admin_salary_matrix'
            )
                ->where('role', $data['role'])
                ->where('is_active', true);

            if ($data['role'] === 'logistics_admin') {
                $oldMatrix->where(
                    'location_tier_id',
                    $data['location_tier_id']
                );
            } else {
                $oldMatrix->whereNull('location_tier_id');
            }

            $oldMatrix
                ->lockForUpdate()
                ->update([
                    'is_active' => false,
                    'effective_to' => now('Asia/Manila')
                        ->subDay()
                        ->format('Y-m-d'),

                    'updated_at' => now(),
                ]);

            DB::table('logistics_admin_salary_matrix')
                ->insert([
                    'role' => $data['role'],

                    'location_tier_id' => $data['role'] === 'logistics_admin'
                            ? $data['location_tier_id']
                            : null,

                    'minimum_monthly_salary_minor' => $tier
                            ? $tier->minimum_monthly_salary_minor
                            : $standardMinor,

                    'maximum_monthly_salary_minor' => $tier
                            ? $tier->maximum_monthly_salary_minor
                            : $standardMinor,

                    'standard_monthly_salary_minor' => $standardMinor,

                    'effective_from' => $data['effective_from'],

                    'effective_to' => null,

                    'is_active' => true,

                    'set_by' => Auth::id(),

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }, 3);

        return back()->with(
            'success',
            'Salary matrix rule saved.'
        );
    }
}

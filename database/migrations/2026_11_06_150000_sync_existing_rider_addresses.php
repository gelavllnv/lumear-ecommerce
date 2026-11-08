<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LAGUNA_POSTAL_CODES = [
        'alaminos' => '4001', 'bay' => '4033', 'binan' => '4024', 'biñan' => '4024',
        'cabuyao' => '4025', 'calamba' => '4027', 'calauan' => '4012', 'cavinti' => '4013',
        'famy' => '4021', 'kalayaan' => '4015', 'liliw' => '4004', 'los banos' => '4030',
        'los baños' => '4030', 'lumban' => '4014', 'luisiana' => '4032', 'magdalena' => '4007',
        'mahayhay' => '4005', 'majayjay' => '4005', 'nagcarlan' => '4002', 'paete' => '4016',
        'pagsanjan' => '4008', 'pakil' => '4017', 'pangil' => '4018', 'pila' => '4010',
        'rizal' => '4003', 'san pablo' => '4000', 'san pedro' => '4023',
        'santa cruz' => '4009', 'sta cruz' => '4009', 'santa maria' => '4022',
        'santa rosa' => '4026', 'sta rosa' => '4026', 'siniloan' => '4019', 'victoria' => '4011',
    ];

    public function up(): void
    {
        DB::table('logistics_rider_applications')
            ->whereRaw('LOWER(province) = ?', ['laguna'])
            ->orderBy('id')
            ->get()
            ->each(function (object $application): void {
                $municipality = mb_strtolower(trim((string) $application->municipality));
                $municipality = preg_replace('/^(city of|municipality of|city)\s+/', '', $municipality);
                $municipality = preg_replace('/\s+city$/', '', $municipality);
                $postalCode = $application->postal_code ?: (self::LAGUNA_POSTAL_CODES[$municipality] ?? null);

                if ($postalCode === null) {
                    return;
                }

                if (! $application->postal_code) {
                    DB::table('logistics_rider_applications')
                        ->where('id', $application->id)
                        ->update(['postal_code' => $postalCode]);
                }

                $email = DB::table('users')->where('id', $application->user_id)->value('email');
                $email ??= $application->email;
                $userId = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $email)])
                    ->value('id');

                if ($userId === null) {
                    return;
                }

                $rider = DB::table('riders')->where('user_id', $userId)->first();

                if (! $rider) {
                    return;
                }

                $updates = ['postal_code' => $rider->postal_code ?: $postalCode];

                foreach ([
                    'name', 'region', 'province', 'municipality', 'municipality_psgc_code',
                    'barangay', 'street', 'house_unit', 'sex', 'date_of_birth',
                ] as $field) {
                    if (($rider->{$field} ?? null) === null && ($application->{$field} ?? null) !== null) {
                        $updates[$field] = $application->{$field};
                    }
                }

                DB::table('riders')->where('id', $rider->id)->update($updates);
            });
    }

    public function down(): void
    {
        /** Preserve postal codes that may have been edited after this backfill. */
    }
};

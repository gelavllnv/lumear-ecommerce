<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsApplication extends Model
{
    protected $table = 'logistics_applications';

    protected $fillable = [
        'logistics_provider_id',
        'company_name',
        'sec_number',
        'tin',
        'first_name',
        'last_name',
        'middle_initial',
        'sex',
        'birthday',
        'email',
        'mobile',
        'region',
        'province',
        'city',
        'municipality_psgc_code',
        'barangay',
        'house_number',
        'street',
        'postal_code',
        'valid_id_path',
        'business_permit_path',
        'email_verified_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'email_verified_at' => 'datetime',
        ];
    }

    public function logisticsProvider(): BelongsTo
    {
        return $this->belongsTo(
            LogisticsProvider::class,
            'logistics_provider_id'
        );
    }
}

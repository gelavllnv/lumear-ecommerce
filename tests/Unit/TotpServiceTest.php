<?php

namespace Tests\Unit;

use App\Services\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_generated_secrets_are_valid_base32_values(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertFalse(app(TotpService::class)->verify($secret, 'ABCDEF'));
    }
}

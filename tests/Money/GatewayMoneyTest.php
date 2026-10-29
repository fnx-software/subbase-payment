<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Money;

use InvalidArgumentException;
use Nafiswatsiq\SubbasePayment\Support\GatewayMoney;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

final class GatewayMoneyTest extends TestCase
{
    public function test_supports_multiple_currency_precisions(): void
    {
        $this->assertSame(1234, GatewayMoney::toMinorUnits('12.34', 'USD'));
        $this->assertSame(123, GatewayMoney::toMinorUnits('123', 'JPY'));
        $this->assertSame(1234, GatewayMoney::toMinorUnits('1.234', 'KWD'));
        $this->assertSame('1.234', GatewayMoney::minorUnits(1234, 'KWD'));
        $this->assertTrue(GatewayMoney::same('12.3400', '12.34', 'USD'));
        $this->assertFalse(GatewayMoney::same('12.35', '12.34', 'USD'));
    }

    public function test_does_not_silently_round_invalid_minor_units(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GatewayMoney::toMinorUnits('1.001', 'USD');
    }

    public function test_rejects_scientific_notation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GatewayMoney::same('1e4', '10000', 'USD');
    }
}

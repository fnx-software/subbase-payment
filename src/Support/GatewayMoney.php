<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Support;

use InvalidArgumentException;
use OverflowException;

final class GatewayMoney
{
    public static function scale(string $currency): int
    {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('Invalid ISO 4217 currency.');
        }

        $override = config("subbase-payment.currency_scales.{$currency}");

        if ($override !== null) {
            if (! is_int($override) || $override < 0 || $override > 4) {
                throw new InvalidArgumentException('Invalid custom currency precision.');
            }

            return $override;
        }

        if (in_array($currency, [
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'MGA',
            'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ], true)) {
            return 0;
        }

        if (in_array($currency, ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'], true)) {
            return 3;
        }

        if (in_array($currency, ['CLF', 'UYW'], true)) {
            return 4;
        }

        return 2;
    }

    public static function minorUnits(int|string $amount, string $currency): string
    {
        $value = (string) $amount;

        if (! preg_match('/^(?:0|[1-9][0-9]{0,17})$/D', $value)) {
            throw new InvalidArgumentException('Gateway minor units must be a nonnegative integer.');
        }

        $scale = self::scale($currency);

        return bcdiv($value, '1'.str_repeat('0', $scale), $scale);
    }

    public static function toMinorUnits(string $amount, string $currency): int
    {
        self::validateDecimal($amount);

        $scale = self::scale($currency);
        $factor = '1'.str_repeat('0', $scale);
        $result = bcmul($amount, $factor, 0);

        if (bccomp($amount, bcdiv($result, $factor, $scale), max($scale, 4)) !== 0) {
            throw new InvalidArgumentException('Amount contains fractional minor units.');
        }

        if (bccomp($result, (string) PHP_INT_MAX, 0) > 0) {
            throw new OverflowException('Amount exceeds the minor-unit integer range.');
        }

        return (int) $result;
    }

    public static function same(string $first, string $second, string $currency): bool
    {
        self::validateDecimal($first);
        self::validateDecimal($second);
        $scale = self::scale($currency);

        // Ensure no extra nonzero fraction is hidden by the comparison scale.
        $precision = max($scale, 8);

        return bccomp($first, $second, $precision) === 0;
    }

    public static function validateDecimal(string $amount): void
    {
        if (! preg_match('/^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,8})?$/D', $amount)) {
            throw new InvalidArgumentException('Payment money must be a plain nonnegative decimal string.');
        }
    }
}

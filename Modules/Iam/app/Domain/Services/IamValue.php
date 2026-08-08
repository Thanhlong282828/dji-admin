<?php

namespace Modules\Iam\Domain\Services;

use UnexpectedValueException;

final class IamValue
{
    public static function int(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        } if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        } throw new UnexpectedValueException('Expected database integer.');
    }

    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        } throw new UnexpectedValueException('Expected database string.');
    }
}

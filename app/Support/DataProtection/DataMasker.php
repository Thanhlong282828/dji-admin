<?php

namespace App\Support\DataProtection;

final class DataMasker
{
    public function email(string $value): string
    {
        [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

        return ($local !== '' ? mb_substr($local, 0, 1) : '*').'***'.($domain !== '' ? '@'.$domain : '');
    }

    public function lastFour(string $value): string
    {
        $suffix = mb_substr($value, -4);

        return '********'.$suffix;
    }

    public function phone(string $value): string
    {
        return '******'.mb_substr($value, -4);
    }
}

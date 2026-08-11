<?php

namespace App\Support\Accessibility;

use InvalidArgumentException;

final class ColorContrastCalculator
{
    public function ratio(string $foreground, string $background): float
    {
        $light = max($this->luminance($foreground), $this->luminance($background));
        $dark = min($this->luminance($foreground), $this->luminance($background));

        return round(($light + 0.05) / ($dark + 0.05), 2);
    }

    private function luminance(string $hex): float
    {
        if (preg_match('/^#([0-9a-f]{6})$/i', $hex, $matches) !== 1) {
            throw new InvalidArgumentException('A six-digit hexadecimal color is required.');
        }
        $channels = [];
        foreach ([0, 2, 4] as $offset) {
            $value = hexdec(substr($matches[1], $offset, 2)) / 255;
            $channels[] = $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}

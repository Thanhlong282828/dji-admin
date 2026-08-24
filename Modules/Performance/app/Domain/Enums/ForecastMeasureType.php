<?php

namespace Modules\Performance\Domain\Enums;

enum ForecastMeasureType: string
{
    case Actual = 'actual';
    case Budget = 'budget';
    case Forecast = 'forecast';
    case Scenario = 'scenario';
}

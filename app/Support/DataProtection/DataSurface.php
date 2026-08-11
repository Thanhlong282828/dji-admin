<?php

namespace App\Support\DataProtection;

enum DataSurface: string
{
    case Ui = 'ui';
    case Api = 'api';
    case Log = 'log';
    case Audit = 'audit';
    case Export = 'export';
    case Integration = 'integration';
    case Notification = 'notification';
}

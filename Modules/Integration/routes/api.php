<?php

use Illuminate\Support\Facades\Route;
use Modules\Integration\Presentation\Http\Controllers\IntegrationController;

Route::post('integrations/webhooks/{company}/{connection}/{provider}', [IntegrationController::class, 'webhook'])->middleware('throttle:60,1')->name('integration.webhook');

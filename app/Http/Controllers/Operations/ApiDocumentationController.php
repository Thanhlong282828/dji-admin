<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class ApiDocumentationController extends Controller
{
    public function __invoke(): View
    {
        return view('operations.api-documentation');
    }
}

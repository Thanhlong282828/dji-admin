<?php

namespace Modules\Core\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['locale' => ['required', 'string', Rule::in(['default', 'vi', 'en', 'zh_CN'])]]);
        $request->session()->put('locale', $request->string('locale')->toString());

        return back();
    }
}

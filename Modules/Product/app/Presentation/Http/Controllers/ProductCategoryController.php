<?php

namespace Modules\Product\Presentation\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Product\Application\Commands\SaveProductCategoryCommand;

final class ProductCategoryController
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    public function index(): View
    {
        return view('admin.product-categories.index', ['categories' => DB::table('product_categories as c')->leftJoin('product_categories as p', 'p.id', '=', 'c.parent_id')->select('c.*', 'p.name as parent_name')->orderBy('c.sort_order')->orderBy('c.name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255'], 'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'], 'status' => ['required', 'in:active,inactive'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $name = $request->string('name')->toString();
        $this->commands->dispatch(new SaveProductCategoryCommand($name, $request->integer('parent_id') ?: null, $request->string('status')->toString(), $request->integer('sort_order')));

        return back()->with('success', 'Đã thêm danh mục.');
    }
}

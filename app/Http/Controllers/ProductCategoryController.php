<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductCategoryController
{
    public function index(): View
    {
        return view('admin.product-categories.index', ['categories' => DB::table('product_categories as c')->leftJoin('product_categories as p', 'p.id', '=', 'c.parent_id')->select('c.*', 'p.name as parent_name')->orderBy('c.sort_order')->orderBy('c.name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255'], 'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'], 'status' => ['required', 'in:active,inactive'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $name = $request->string('name')->toString();
        DB::table('product_categories')->insert(['name' => $name, 'parent_id' => $request->integer('parent_id') ?: null, 'status' => $request->string('status')->toString(), 'sort_order' => $request->integer('sort_order'), 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Đã thêm danh mục.');
    }
}

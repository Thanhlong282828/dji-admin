<?php

namespace Modules\Performance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.talent.performance.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.talent.performance.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('admin.talent.performance.index');
    }

    /**
     * Show the specified resource.
     */
    public function show(int $id): RedirectResponse
    {
        return redirect()->route('admin.talent.performance.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): RedirectResponse
    {
        return redirect()->route('admin.talent.performance.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        return redirect()->route('admin.talent.performance.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        return redirect()->route('admin.talent.performance.index');
    }
}

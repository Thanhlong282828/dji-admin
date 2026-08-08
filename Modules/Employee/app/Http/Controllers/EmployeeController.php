<?php

namespace Modules\Employee\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('employee::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('employee::index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void {}

    /**
     * Show the specified resource.
     */
    public function show(int $id): View
    {
        return view('employee::index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        return view('employee::index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): void {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): void {}
}

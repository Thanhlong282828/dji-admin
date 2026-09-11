<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Commands\MutateBuyerAddressCommand;
use Modules\Core\Application\Buses\CommandBusInterface;

final readonly class BuyerAddressController
{
    public function __construct(private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);

        return view('public.addresses.index', ['addresses' => DB::table('buyer_addresses')->where('user_id', $user->id)->orderByDesc('is_default')->orderByDesc('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->commands->dispatch(new MutateBuyerAddressCommand('save', $this->user($request), data: $this->validated($request)));

        return back()->with('success', 'Đã thêm địa chỉ giao hàng.');
    }

    public function update(Request $request, int $address): RedirectResponse
    {
        $this->commands->dispatch(new MutateBuyerAddressCommand('save', $this->user($request), $address, $this->validated($request)));

        return back()->with('success', 'Đã cập nhật địa chỉ.');
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        $this->commands->dispatch(new MutateBuyerAddressCommand('default', $this->user($request), $address));

        return back()->with('success', 'Đã đặt địa chỉ mặc định.');
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $this->commands->dispatch(new MutateBuyerAddressCommand('delete', $this->user($request), $address));

        return back()->with('success', 'Đã xóa địa chỉ.');
    }

    /** @return array{label:string,recipient_name:string,phone:string,province:string,district:string,ward:string,address_line:string,postal_code:?string,is_default:bool} */
    private function validated(Request $request): array
    {
        $request->validate(['label' => ['required', 'string', 'max:40'], 'recipient_name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'regex:/^(\+84|0)[0-9]{9,10}$/'], 'province' => ['required', 'string', 'max:120'], 'district' => ['required', 'string', 'max:120'], 'ward' => ['required', 'string', 'max:120'], 'address_line' => ['required', 'string', 'max:500'], 'postal_code' => ['nullable', 'string', 'max:20'], 'is_default' => ['nullable', 'boolean']]);

        return ['label' => $request->string('label')->toString(), 'recipient_name' => $request->string('recipient_name')->toString(), 'phone' => $request->string('phone')->toString(), 'province' => $request->string('province')->toString(), 'district' => $request->string('district')->toString(), 'ward' => $request->string('ward')->toString(), 'address_line' => $request->string('address_line')->toString(), 'postal_code' => $request->filled('postal_code') ? $request->string('postal_code')->toString() : null, 'is_default' => $request->boolean('is_default')];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->access_enabled, 403);

        return $user;
    }
}

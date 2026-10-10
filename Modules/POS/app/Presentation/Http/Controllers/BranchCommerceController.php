<?php

namespace Modules\POS\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Inventory\Application\Queries\GetInventoryProcessHelpQuery;
use Modules\POS\Application\Commands\MutateBranchCommerceCommand;
use Modules\POS\Application\Queries\GetBranchCommercePageQuery;

final class BranchCommerceController extends Controller
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function dashboard(Request $request): View
    {
        return view('admin.branch-commerce.dashboard', $this->read($request, 'dashboard'));
    }

    public function warehouse(Request $request): View
    {
        $data = $this->read($request, 'warehouse', ['branch_id' => $request->integer('branch_id')]);
        $companyId = (int) ($data['companyId'] ?? $request->integer('company_id', 1));
        $data['processHelp'] = $this->queries->ask(new GetInventoryProcessHelpQuery($companyId, $request->integer('branch_id') ?: null, $this->actor($request)));

        return view('admin.branch-commerce.warehouse', $data);
    }

    public function customers(Request $request): View
    {
        return view('admin.branch-commerce.customers', $this->read($request, 'customers', [
            'branch_id' => $request->integer('branch_id'),
            'segment' => $request->string('segment')->toString(),
            'search' => $request->string('search')->toString(),
        ]));
    }

    public function customer(Request $request, int $customer): View
    {
        return view('admin.branch-commerce.customer-show', $this->read($request, 'customer', ['customer' => $customer]));
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $data = $this->validated($request, ['full_name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:30', Rule::unique('commerce_customers')->where(fn (Builder $query): Builder => $query->where('company_id', $companyId))], 'email' => ['nullable', 'email', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $this->commands->dispatch(new MutateBranchCommerceCommand('customer.create', $companyId, $this->actor($request), $data));

        return back()->with('success', 'Đã thêm khách hàng.');
    }

    public function updateCustomer(Request $request, int $customer): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $data = $this->validated($request, ['full_name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:30', Rule::unique('commerce_customers')->where(fn (Builder $query): Builder => $query->where('company_id', $companyId))->ignore($customer)], 'email' => ['nullable', 'email', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        $this->commands->dispatch(new MutateBranchCommerceCommand('customer.update', $companyId, $this->actor($request), $data, $customer));

        return back()->with('success', 'Đã sửa khách hàng.');
    }

    public function destroyCustomer(Request $request, int $customer): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $this->commands->dispatch(new MutateBranchCommerceCommand('customer.delete', $companyId, $this->actor($request), resourceId: $customer));

        return back()->with('success', 'Đã xóa khách hàng chưa phát sinh giao dịch.');
    }

    public function services(Request $request): View
    {
        return view('admin.branch-commerce.services', $this->read($request, 'services'));
    }

    public function storeService(Request $request): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $data = $this->serviceData($request, $companyId);
        $this->commands->dispatch(new MutateBranchCommerceCommand('service.create', $companyId, $this->actor($request), $data));

        return back()->with('success', 'Đã thêm dịch vụ công ty.');
    }

    public function updateService(Request $request, int $service): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $this->commands->dispatch(new MutateBranchCommerceCommand('service.update', $companyId, $this->actor($request), $this->serviceData($request, $companyId, $service), $service));

        return back()->with('success', 'Đã sửa dịch vụ công ty.');
    }

    public function destroyService(Request $request, int $service): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $this->commands->dispatch(new MutateBranchCommerceCommand('service.delete', $companyId, $this->actor($request), resourceId: $service));

        return back()->with('success', 'Đã xóa dịch vụ chưa phát sinh sử dụng.');
    }

    public function checkins(Request $request): View
    {
        return view('admin.branch-commerce.checkins', $this->read($request, 'checkins'));
    }

    public function storeCheckin(Request $request): RedirectResponse
    {
        [$user, $companyId, $branches] = $this->scope($request);
        $request->validate(['branch_id' => ['required', 'integer'], 'customer_id' => ['required', 'integer']]);
        $branchId = $request->integer('branch_id');
        abort_unless($branches->contains('id', $branchId) && $this->access->branch($user, $companyId, $branchId), 404);
        $visit = $this->commands->dispatch(new MutateBranchCommerceCommand('visit.check-in', $companyId, $user, ['branch_id' => $branchId, 'customer_id' => $request->integer('customer_id')]));
        abort_unless(is_int($visit), 500);

        return to_route('admin.branch-commerce.checkout', ['visit' => $visit, 'company_id' => $companyId])->with('success', 'Khách đã check-in.');
    }

    public function checkout(Request $request, int $visit): View
    {
        return view('admin.branch-commerce.checkout', $this->read($request, 'checkout', ['visit' => $visit]));
    }

    public function addService(Request $request, int $visit): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $request->validate(['service_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $this->commands->dispatch(new MutateBranchCommerceCommand('visit.service.add', $companyId, $this->actor($request), ['service_id' => $request->integer('service_id'), 'quantity' => $request->integer('quantity')], $visit));

        return back()->with('success', 'Đã thêm/cập nhật dịch vụ sử dụng.');
    }

    public function addProduct(Request $request, int $visit): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $request->validate(['product_option' => ['required', 'string', 'regex:/^\d+:\d+$/'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000']]);
        [$variant, $location] = array_map('intval', explode(':', $request->string('product_option')->toString(), 2));
        $this->commands->dispatch(new MutateBranchCommerceCommand('visit.product.add', $companyId, $this->actor($request), ['variant_id' => $variant, 'location_id' => $location, 'quantity' => $request->integer('quantity')], $visit));

        return back()->with('success', 'Đã thêm/cập nhật sản phẩm khách chọn.');
    }

    public function removeLine(Request $request, int $visit, string $type, int $line): RedirectResponse
    {
        [, $companyId] = $this->scope($request);
        $this->commands->dispatch(new MutateBranchCommerceCommand('visit.line.remove', $companyId, $this->actor($request), ['type' => $type, 'line_id' => $line], $visit));

        return back()->with('success', 'Đã xóa line khỏi phiên chưa checkout.');
    }

    public function finalize(Request $request, int $visit): RedirectResponse
    {
        [$user, $companyId] = $this->scope($request);
        $request->validate(['payment_method' => ['required', Rule::in(['cash', 'card_evidence', 'manual_transfer'])]]);
        $receipt = $this->commands->dispatch(new MutateBranchCommerceCommand('visit.checkout', $companyId, $user, ['payment_method' => $request->string('payment_method')->toString()], $visit));
        abort_unless(is_int($receipt), 500);

        return back()->with('success', "Checkout hoàn tất. Receipt #{$receipt} đã nối Branch Revenue.");
    }

    public function sales(Request $request): View
    {
        return view('admin.branch-commerce.sales', $this->read($request, 'sales', ['branch_id' => $request->integer('branch_id')]));
    }

    public function receipts(Request $request): View
    {
        return view('admin.branch-commerce.receipts', $this->read($request, 'receipts', ['branch_id' => $request->integer('branch_id')]));
    }

    public function receipt(Request $request, int $receipt): View
    {
        return view('admin.branch-commerce.receipt-show', $this->read($request, 'receipt', ['receipt' => $receipt]));
    }

    /** @return array{User,int,Collection<int,\stdClass>} */
    private function scope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);
        $branches = DB::table('branches as b')->where('b.company_id', $companyId)->whereNull('b.deleted_at')->when(! $user->can('organization.tenant-all'), fn (Builder $query): Builder => $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $user->id)))->orderBy('b.name')->get(['b.id', 'b.code', 'b.name']);

        return [$user, $companyId, $branches];
    }

    /** @return array<string,mixed> */
    private function serviceData(Request $request, int $companyId, ?int $serviceId = null): array
    {
        return $this->validated($request, ['code' => ['required', 'string', 'max:60', Rule::unique('company_services')->where(fn (Builder $query): Builder => $query->where('company_id', $companyId))->ignore($serviceId)], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:3000'], 'price_minor' => ['required', 'integer', 'min:0'], 'status' => ['required', Rule::in(['active', 'inactive'])]]) + ['currency' => 'VND'];
    }

    /** @param array<string,mixed> $rules
     * @return array<string,mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $data = $request->validate($rules);
        if (! is_array($data)) {
            throw new \RuntimeException('Validated payload không hợp lệ.');
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    private function read(Request $request, string $page, array $filters = []): array
    {
        $actor = $this->actor($request);
        $companyId = $request->integer('company_id', 1);
        $result = $this->queries->ask(new GetBranchCommercePageQuery($page, $actor, $companyId, $filters));
        abort_unless(is_array($result), 500);

        $normalized = [];
        foreach ($result as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}

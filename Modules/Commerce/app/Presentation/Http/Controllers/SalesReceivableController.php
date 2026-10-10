<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Commerce\Application\Commands\AcceptQuotationCommand;
use Modules\Commerce\Application\Commands\AllocateCustomerCollectionCommand;
use Modules\Commerce\Application\Commands\ConfirmSalesOrderCommand;
use Modules\Commerce\Application\Commands\ConvertQuotationToSalesOrderCommand;
use Modules\Commerce\Application\Commands\CreateCustomerInvoiceCommand;
use Modules\Commerce\Application\Commands\CreateQuotationCommand;
use Modules\Commerce\Application\Commands\FulfillSalesOrderCommand;
use Modules\Commerce\Application\Commands\IssueCustomerInvoiceCommand;
use Modules\Commerce\Application\Queries\GetCustomerRefundWorkspaceQuery;
use Modules\Commerce\Application\Queries\GetSalesProcessHelpQuery;
use Modules\Commerce\Application\Queries\GetSalesReceivableQuery;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;

final readonly class SalesReceivableController
{
    public function __construct(private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        $company = $this->company($request);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        return view('admin.commerce.sales-receivable', [
            'companyId' => $company,
            'data' => $this->queries->ask(new GetSalesReceivableQuery($company)),
            'refundData' => $this->queries->ask(new GetCustomerRefundWorkspaceQuery($company)),
            'processHelp' => $this->queries->ask(new GetSalesProcessHelpQuery($company, $branchId, $user)),
        ]);
    }

    public function mutate(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $validated = $request->validate(['company_id' => ['required', 'integer'], 'action' => ['required', 'string', 'max:60'], 'id' => ['nullable', 'integer'], 'quotation_id' => ['nullable', 'integer'], 'sales_order_id' => ['nullable', 'integer'], 'sales_order_line_id' => ['nullable', 'integer'], 'customer_invoice_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'product_variant_id' => ['nullable', 'integer'], 'inventory_location_id' => ['nullable', 'integer'], 'financial_account_id' => ['nullable', 'integer', 'min:1'], 'reference' => ['nullable', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'], 'currency' => ['nullable', 'string', 'size:3'], 'quotation_date' => ['nullable', 'date'], 'valid_until' => ['nullable', 'date'], 'order_date' => ['nullable', 'date'], 'fulfilled_on' => ['nullable', 'date'], 'invoice_number' => ['nullable', 'string', 'max:120'], 'invoice_date' => ['nullable', 'date'], 'due_date' => ['nullable', 'date'], 'tax_category_code' => ['nullable', 'string', 'max:50'], 'collection_date' => ['nullable', 'date'], 'method' => ['nullable', 'string', 'max:60'], 'quantity' => ['nullable', 'integer', 'min:1'], 'unit_price_minor' => ['nullable', 'integer', 'min:1'], 'discount_minor' => ['nullable', 'integer', 'min:0'], 'amount_minor' => ['nullable', 'integer', 'min:1']]);
        abort_unless(is_array($validated), 422);
        $action = $request->string('action')->toString();
        $permission = str_starts_with($action, 'collection.') ? 'collection.create' : (str_starts_with($action, 'invoice.') ? 'customer-invoice.manage' : 'sales.manage');
        abort_unless($user->can($permission), 403);
        $data = $this->stringKeys($validated);
        $command = match ($action) {
            'quotation.create' => new CreateQuotationCommand($company, $user, $data),
            'quotation.accept' => new AcceptQuotationCommand($company, $user, $data),
            'order.convert' => new ConvertQuotationToSalesOrderCommand($company, $user, $data),
            'order.confirm' => new ConfirmSalesOrderCommand($company, $user, $data),
            'order.fulfill' => new FulfillSalesOrderCommand($company, $user, $data),
            'invoice.create' => new CreateCustomerInvoiceCommand($company, $user, $data),
            'invoice.issue' => new IssueCustomerInvoiceCommand($company, $user, $data),
            'collection.allocate' => new AllocateCustomerCollectionCommand($company, $user, $data),
            default => abort(422, 'Sales action không hợp lệ.'),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'Sales/AR đã cập nhật.');
    }

    private function company(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($user->hasRole('Super Admin') || DB::table('company_user_access')->where(['company_id' => $company, 'user_id' => $user->id])->exists(), 404);

        return $company;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private function stringKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}

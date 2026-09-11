<?php

namespace Modules\Maintenance\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Maintenance\Application\Commands\AddMaintenancePartCommand;
use Modules\Maintenance\Application\Commands\ApproveMaintenanceRequestCommand;
use Modules\Maintenance\Application\Commands\AssignMaintenanceWorkOrderCommand;
use Modules\Maintenance\Application\Commands\ChangeMaintenanceWorkOrderStatusCommand;
use Modules\Maintenance\Application\Commands\ConvertMaintenanceRequestCommand;
use Modules\Maintenance\Application\Commands\CreateMaintenanceExpenseCommand;
use Modules\Maintenance\Application\Commands\CreateMaintenancePurchaseRequestCommand;
use Modules\Maintenance\Application\Commands\CreateMaintenanceRequestCommand;
use Modules\Maintenance\Application\Commands\CreateMaintenanceWorkOrderCommand;
use Modules\Maintenance\Application\Commands\CreatePreventiveMaintenancePlanCommand;
use Modules\Maintenance\Application\Commands\GeneratePreventiveWorkOrderCommand;
use Modules\Maintenance\Application\Commands\IssueMaintenancePartCommand;
use Modules\Maintenance\Application\Commands\SubmitMaintenanceRequestCommand;
use Modules\Maintenance\Application\Queries\GetMaintenanceQuery;
use Modules\Maintenance\Presentation\Http\Requests\MaintenanceRequest;

final class MaintenanceController extends Controller
{
    public function __construct(private readonly QueryBusInterface $q, private readonly CommandBusInterface $c, private readonly BranchAccessService $access) {}

    public function overview(Request $r): View
    {
        [, $company] = $this->actor($r);
        $data = $this->q->ask(new GetMaintenanceQuery($company));

        return view('maintenance::overview', compact('data', 'company'));
    }

    public function requests(Request $r): View
    {
        [, $company] = $this->actor($r);
        $data = $this->q->ask(new GetMaintenanceQuery($company));

        return view('maintenance::requests', compact('data', 'company'));
    }

    public function orders(Request $r): View
    {
        [, $company] = $this->actor($r);
        $data = $this->q->ask(new GetMaintenanceQuery($company));

        return view('maintenance::orders', compact('data', 'company'));
    }

    public function show(Request $r, int $order): View
    {
        [, $company] = $this->actor($r);
        $data = $this->q->ask(new GetMaintenanceQuery($company, $order));

        return view('maintenance::show', compact('data', 'company'));
    }

    public function plans(Request $r): View
    {
        [, $company] = $this->actor($r);
        $data = $this->q->ask(new GetMaintenanceQuery($company));

        return view('maintenance::plans', compact('data', 'company'));
    }

    public function storeRequest(MaintenanceRequest $r): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $id = $this->c->dispatch(new CreateMaintenanceRequestCommand($r->validated(), (int) $u->id));
        abort_unless(is_int($id), 500);

        return to_route('admin.maintenance.requests', ['company_id' => $company])->with('success', 'Đã tạo yêu cầu #'.$id);
    }

    public function submit(MaintenanceRequest $r, int $request): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $this->c->dispatch(new SubmitMaintenanceRequestCommand($company, $request, (int) $u->id));

        return back()->with('success', 'Đã gửi yêu cầu.');
    }

    public function decision(MaintenanceRequest $r, int $request): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $this->c->dispatch(new ApproveMaintenanceRequestCommand($company, $request, (int) $u->id, $r->string('decision')->toString() === 'approve'));

        return back()->with('success', 'Đã xử lý yêu cầu.');
    }

    public function convert(MaintenanceRequest $r, int $request): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $id = $this->c->dispatch(new ConvertMaintenanceRequestCommand($company, $request, (int) $u->id));

        return to_route('admin.maintenance.work-orders.show', ['order' => $id, 'company_id' => $company]);
    }

    public function storeOrder(MaintenanceRequest $r): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $id = $this->c->dispatch(new CreateMaintenanceWorkOrderCommand($r->validated(), (int) $u->id));

        return to_route('admin.maintenance.work-orders.show', ['order' => $id, 'company_id' => $company]);
    }

    public function assign(MaintenanceRequest $r, int $order): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $this->c->dispatch(new AssignMaintenanceWorkOrderCommand($company, $order, $r->integer('employee_id'), (int) $u->id));

        return back()->with('success', 'Đã phân công.');
    }

    public function status(MaintenanceRequest $r, int $order): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $this->c->dispatch(new ChangeMaintenanceWorkOrderStatusCommand($company, $order, $r->string('status')->toString(), (int) $u->id, $r->validated()));

        return back()->with('success', 'Đã cập nhật Work Order.');
    }

    public function storePlan(MaintenanceRequest $r): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $this->c->dispatch(new CreatePreventiveMaintenancePlanCommand($r->validated(), (int) $u->id));

        return back()->with('success', 'Đã tạo kế hoạch.');
    }

    public function generate(MaintenanceRequest $r, int $plan): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $id = $this->c->dispatch(new GeneratePreventiveWorkOrderCommand($company, $plan, (int) $u->id));

        return to_route('admin.maintenance.work-orders.show', ['order' => $id, 'company_id' => $company]);
    }

    public function part(MaintenanceRequest $r, int $order): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $this->c->dispatch(new AddMaintenancePartCommand($company, $order, $r->validated(), (int) $u->id));

        return back()->with('success', 'Đã thêm spare part.');
    }

    public function issue(MaintenanceRequest $r, int $order, int $part): RedirectResponse
    {
        [$u,$company] = $this->actor($r);
        $this->c->dispatch(new IssueMaintenancePartCommand($company, $order, $part, (int) $u->id));

        return back()->with('success', 'Đã xuất spare part.');
    }

    public function expense(MaintenanceRequest $r, int $order): RedirectResponse
    {
        [$user, $company] = $this->actor($r);
        $this->c->dispatch(new CreateMaintenanceExpenseCommand($company, $order, $user, $r->validated()));

        return back()->with('success', 'Đã tạo Expense draft tại Finance với nguồn Work Order.');
    }

    public function purchaseRequest(MaintenanceRequest $r, int $order): RedirectResponse
    {
        [$user, $company] = $this->actor($r);
        $this->c->dispatch(new CreateMaintenancePurchaseRequestCommand($company, $order, $user, $r->validated()));

        return back()->with('success', 'Đã tạo Purchase Request draft tại Procurement với nguồn Work Order.');
    }

    /** @return array{User,int} */
    private function actor(Request $r): array
    {
        $u = $r->user();
        $company = $r->integer('company_id');
        abort_unless($u instanceof User && $company > 0 && $this->access->company($u, $company), 404);

        return [$u, $company];
    }
}

<?php

namespace App\Support\Architecture;

use App\Support\Navigation\AdminNavigation;
use Illuminate\Routing\Router;
use RuntimeException;

final readonly class ErpArchitectureMapService
{
    public function __construct(private AdminNavigation $navigation, private Router $router) {}

    /** @return array{menu:string,module:string,items:int,modules:int,edges:int} */
    public function generate(): array
    {
        $modules = $this->modules();
        $this->assertAcyclic($modules);
        $groups = $this->navigation->definitions();
        $routes = $this->router->getRoutes();
        $menu = [str_repeat('=', 60), 'SƠ ĐỒ LUỒNG MENU ERP HIỆN TẠI', str_repeat('=', 60), '', 'Nguồn: AdminNavigation + Laravel named routes. Không liệt kê action-only/backend-only.', ''];
        $items = 0;
        foreach ($groups as $group) {
            $menu[] = mb_strtoupper($group['label']);
            $menu[] = '│';
            foreach ($group['items'] as $index => $item) {
                if ($routes->getByName($item['route']) === null) {
                    throw new RuntimeException('Sidebar route does not exist: '.$item['route']);
                }
                $items++;
                $last = $index === array_key_last($group['items']);
                $indent = $last ? '    ' : '│   ';
                $menu[] = ($last ? '└── ' : '├── ').$item['label'];
                $menu[] = $indent.'Owner      : '.$this->owner($item['route'], $modules);
                $menu[] = $indent.'Route      : '.$item['route'];
                $menu[] = $indent.'Permission : '.$item['permission'];
                $menu[] = $indent.'Connects   : '.$this->connection($item['key']);
                $menu[] = $indent.'Status     : IMPLEMENTED';
            }
            $menu[] = '';
        }
        $menu[] = 'CANONICAL BUSINESS CHAINS';
        foreach ($this->chains() as $chain) {
            $menu[] = '→ '.$chain;
        }

        $module = [str_repeat('=', 60), 'SƠ ĐỒ LIÊN KẾT MODULE ERP HIỆN TẠI', str_repeat('=', 60), '', 'Nhãn [DEPENDS] là code dependency trong module.json; [BUSINESS FLOW] là liên kết nghiệp vụ, không mặc nhiên là dependency.', ''];
        $edges = 0;
        foreach ($modules as $name => $definition) {
            $module[] = 'MODULE: '.$name;
            $module[] = 'Responsibility : '.($definition['description'] !== '' ? $definition['description'] : 'Owner bounded context theo module runtime hiện tại.');
            $module[] = 'Status         : IMPLEMENTED';
            $module[] = 'Manifest       : Modules/'.$name.'/module.json';
            $module[] = 'Main routes    : '.$this->moduleRoutes($name, $definition['alias']);
            $module[] = 'Write boundary : '.$name.'-owned application/domain services and tables.';
            $module[] = 'Read boundary  : Query/read services; Dashboard/Reporting aggregate read-only.';
            foreach ($definition['requires'] as $dependency) {
                if (! isset($modules[$dependency])) {
                    throw new RuntimeException("Missing module {$dependency} required by {$name}");
                }
                $edges++;
                $module[] = '[DEPENDS] '.$name.' → '.$dependency;
            }
            $module[] = '';
        }
        $module[] = 'REQUIRED BUSINESS FLOWS';
        foreach ($this->chains() as $chain) {
            $module[] = '[BUSINESS FLOW] '.$chain;
        }

        $menuPath = base_path('SO_DO_LUONG_MENU_ERP.txt');
        $modulePath = base_path('SO_DO_LIEN_KET_MODULE_ERP.txt');
        file_put_contents($menuPath, implode(PHP_EOL, $menu).PHP_EOL);
        file_put_contents($modulePath, implode(PHP_EOL, $module).PHP_EOL);

        return ['menu' => $menuPath, 'module' => $modulePath, 'items' => $items, 'modules' => count($modules), 'edges' => $edges];
    }

    /** @return array<string,array{alias:string,description:string,requires:list<string>}> */
    private function modules(): array
    {
        $result = [];
        $statuses = json_decode((string) file_get_contents(base_path('modules_statuses.json')), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($statuses)) {
            throw new RuntimeException('Invalid modules_statuses.json');
        }
        foreach (glob(base_path('Modules/*/module.json')) ?: [] as $path) {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || ! is_string($decoded['name'] ?? null)) {
                throw new RuntimeException('Invalid module manifest: '.$path);
            }
            if (($statuses[$decoded['name']] ?? false) !== true) {
                continue;
            }
            $rawRequires = $decoded['requires'] ?? [];
            if (! is_array($rawRequires)) {
                throw new RuntimeException('Invalid requires list: '.$path);
            }
            $requires = [];
            foreach ($rawRequires as $dependency) {
                if (is_string($dependency)) {
                    $requires[] = $dependency;
                }
            }
            $result[$decoded['name']] = ['alias' => is_string($decoded['alias'] ?? null) ? $decoded['alias'] : '', 'description' => is_string($decoded['description'] ?? null) ? $decoded['description'] : '', 'requires' => $requires];
        }
        ksort($result);

        return $result;
    }

    /** @param array<string,array{alias:string,description:string,requires:list<string>}> $modules */
    private function assertAcyclic(array $modules): void
    {
        $visiting = [];
        $visited = [];
        $walk = function (string $name) use (&$walk, &$visiting, &$visited, $modules): void {
            if (isset($visiting[$name])) {
                throw new RuntimeException('Module dependency cycle includes '.$name);
            }
            if (isset($visited[$name])) {
                return;
            }
            $visiting[$name] = true;
            foreach ($modules[$name]['requires'] as $dependency) {
                if (isset($modules[$dependency])) {
                    $walk($dependency);
                }
            }
            unset($visiting[$name]);
            $visited[$name] = true;
        };
        foreach (array_keys($modules) as $name) {
            $walk($name);
        }
    }

    /** @param array<string,array{alias:string,description:string,requires:list<string>}> $modules */
    private function owner(string $route, array $modules): string
    {
        $map = ['admin.documents.' => 'Document', 'admin.analytics.enterprise' => 'Dashboard', 'admin.supply-chain.' => 'Dashboard', 'admin.sales.' => 'Commerce', 'admin.commerce.' => 'Commerce', 'admin.seller.' => 'Commerce', 'admin.products.' => 'Product', 'admin.product-categories.' => 'Product', 'admin.procurement.' => 'Procurement', 'admin.finance.' => 'Finance', 'admin.payroll.' => 'Payroll', 'admin.employees.' => 'Employee', 'admin.hr.' => 'Employee', 'admin.recruitment' => 'Recruitment', 'admin.attendance.' => 'Attendance', 'admin.leave.' => 'Leave', 'admin.overtime.' => 'Overtime', 'admin.iam.' => 'Iam', 'admin.pos.' => 'POS', 'dashboard' => 'Dashboard'];
        foreach ($map as $prefix => $owner) {
            if (str_starts_with($route, $prefix) && isset($modules[$owner])) {
                return $owner;
            }
        }

        return 'Cross-project/Admin UI';
    }

    private function connection(string $key): string
    {
        return match ($key) {
            'dashboard' => 'People + Sales + Supply Chain + Finance + Control',
            'sales-receivable' => 'Customer → Quotation → Sales Order → Inventory → Invoice → AR → Collection → Treasury',
            'procurement' => 'Vendor → Purchase Request → Purchase Order → Receipt → Inventory → AP → Treasury',
            'financial-statements' => 'Posted Journal → GL → Trial Balance → P&L / Balance Sheet / Cash Flow',
            'finance-overview' => 'GL + AR/AP + Treasury + Budget + Commitment',
            'employees' => 'Employee → Assignment → Attendance → Payroll',
            'recruitment' => 'Requisition → Candidate → Offer → Employee',
            'seller-inventory', 'inventory-movements' => 'Product Variant → Inventory balance → append-only movement',
            'pos-dashboard' => 'Customer Visit → Receipt → Inventory → Revenue → Finance',
            'enterprise-documents' => 'Owner record → DMS metadata → immutable version/SHA-256 → Approval/Audit evidence → owner command',
            default => 'Owner workspace and canonical detail/action routes',
        };
    }

    /** @return list<string> */
    private function chains(): array
    {
        return [
            'Recruitment → Employee → Assignment → Contract → Attendance → Payroll',
            'Employee → Attendance / Leave / Overtime / Salary Assignment → Payroll Input → Run → Item → Payslip → Tax / Insurance → Accounting',
            'Company → Vendor → Procurement → Goods Receipt → Inventory → Vendor Bill → AP → Treasury → Accounting → Financial Statements',
            'Commerce → Customer → Quotation → Sales Order → Inventory Fulfillment → COGS → Customer Invoice → AR → Collection → Treasury → Accounting → Financial Statements',
            'Marketplace → Payment → Fulfillment → Inventory → COGS → Revenue → Finance',
            'POS → Receipt → Customer History → Inventory → COGS → Revenue → Finance',
            'Employee / Procurement / Finance / Commerce → Enterprise Document → immutable Version + SHA-256 → Workflow / Approval Inbox → Notification / Audit → owner-domain Command',
            'Finance Asset → Assignment → Depreciation → Accounting → Financial Statements',
            'Expense → Approval → Treasury → Accounting → Budget Actual',
            'Budget → Commitment → Procurement / Expense → Actual → Budget vs Actual',
            'Source domains → SourceAccountingPostingService → Journal → GL → Trial Balance → Financial Statements',
            'Business domains → Events → Notifications / Approval Inbox / Data Quality / Executive Dashboard',
        ];
    }

    private function moduleRoutes(string $name, string $alias): string
    {
        $paths = [base_path("Modules/{$name}/routes/web.php"), base_path("Modules/{$name}/routes/api.php")];
        $existing = array_values(array_filter($paths, 'is_file'));

        return $existing === [] ? 'No module-owned route file' : implode(', ', array_map(fn (string $path): string => str_replace(base_path().'/', '', $path), $existing)).($alias !== '' ? ' (alias '.$alias.')' : '');
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Navigation\AdminNavigation;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

final class EnterpriseDataCompletenessAuditCommand extends Command
{
    protected $signature = 'app:enterprise-data-completeness-audit';

    protected $description = 'Generate read-only menu, empty-table and business-lineage evidence from the current database.';

    public function handle(AdminNavigation $navigation): int
    {
        $database = DB::connection()->getDatabaseName();
        $tableNames = [];
        foreach (DB::select('SHOW TABLES') as $row) {
            if (! is_object($row)) {
                throw new \RuntimeException('Database returned an invalid table row.');
            }
            $values = get_object_vars($row);
            $name = reset($values);
            if (! is_string($name) || $name === '') {
                throw new \RuntimeException('Database returned an invalid table name.');
            }
            $tableNames[] = $name;
        }
        $tables = collect($tableNames);
        $counts = $tables->mapWithKeys(static fn (string $table): array => [$table => DB::table($table)->count()]);
        $empty = $counts->filter(static fn (int $count): bool => $count === 0)->map(
            fn (int $count, string $table): array => ['table' => $table, 'category' => $this->tableCategory($table), 'population_policy' => $this->populationPolicy($table)]
        )->values();

        $menu = collect($navigation->definitions())->flatMap(function (array $group) use ($counts): array {
            return array_map(function (array $item) use ($group, $counts): array {
                $primary = $this->primaryTables($item['key'], $item['route']);
                $existing = array_values(array_filter($primary, static fn (string $table): bool => $counts->has($table)));
                $rowCount = array_sum(array_map(static fn (string $table): int => (int) $counts->get($table, 0), $existing));
                [$dataState, $runtimeState, $action] = $this->states($existing, $rowCount, $item['route']);
                $route = Route::getRoutes()->getByName($item['route']);

                return [
                    'menu_group' => $group['label'], 'menu_label' => $item['label'], 'route' => $item['route'],
                    'owner_module' => $this->owner($item['route']), 'permission' => $item['permission'], 'primary_tables' => $existing,
                    'current_row_count' => $existing === [] ? null : $rowCount, 'company_scope' => array_key_exists('company_id', $item['parameters']),
                    'branch_scope' => str_contains($item['route'], 'branch') || in_array($item['key'], ['seller-inventory', 'inventory-movements'], true),
                    'upstream_business_source' => $this->upstream($item['key']), 'downstream_consumer' => $this->downstream($item['key']),
                    'canonical_write_boundary' => $this->owner($item['route']).' application/domain service',
                    'has_create_flow' => $route !== null && ! str_contains($item['route'], 'overview'), 'has_approval_flow' => $this->hasApproval($item['key']),
                    'has_accounting_effect' => $this->effect($item['key'], ['sales-receivable', 'procurement', 'finance-operations', 'treasury', 'branch-bonuses', 'payroll-runs']),
                    'has_inventory_effect' => $this->effect($item['key'], ['sales-receivable', 'procurement', 'seller-inventory', 'inventory-movements', 'customer-checkins']),
                    'has_payroll_effect' => str_starts_with($item['key'], 'payroll') || in_array($item['key'], ['attendance', 'leave', 'overtime', 'branch-bonuses'], true),
                    'has_budget_effect' => in_array($item['key'], ['procurement', 'finance-operations', 'cost-center-cost-management'], true),
                    'data_state' => $dataState, 'runtime_state' => $runtimeState, 'action_required' => $action,
                    'notes' => $existing === [] ? 'Read/control surface or no single authoritative table; no row count inferred.' : 'Aggregate of listed owner tables; child rows may exceed business documents.',
                ];
            }, $group['items']);
        })->values();

        $graph = $this->graphEvidence($counts->all());
        $islands = $this->islands();
        $generatedAt = now()->toIso8601String();
        $payload = ['generated_at' => $generatedAt, 'database' => $database, 'menu_total' => $menu->count(), 'table_total' => $tables->count(), 'empty_table_total' => $empty->count(), 'menus' => $menu->all(), 'empty_tables' => $empty->all(), 'business_graph' => $graph, 'disconnected_islands' => $islands];
        $directory = storage_path('app/reports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $jsonPath = $directory.'/enterprise-data-completeness.json';
        file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);
        $markdownPath = base_path('docs/enterprise-data-completeness-audit.md');
        file_put_contents($markdownPath, $this->markdown($database, $generatedAt, array_values($menu->all()), array_values($empty->all()), $graph, $islands, $tables->count()));
        $this->table(['Evidence', 'Value'], [['Database', $database], ['Menu destinations', $menu->count()], ['Tables', $tables->count()], ['Empty tables', $empty->count()], ['Disconnected findings', count($islands)], ['JSON', $jsonPath], ['Human report', $markdownPath]]);

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function primaryTables(string $key, string $route): array
    {
        $exact = [
            'companies' => ['companies'], 'branches' => ['branches'], 'cost-centers' => ['cost_centers'], 'employees' => ['employees'], 'attendance' => ['attendance_records'],
            'leave' => ['leave_requests'], 'overtime' => ['overtime_requests'], 'payroll-periods' => ['payroll_periods'], 'payroll-inputs' => ['payroll_input_snapshots'],
            'payroll-runs' => ['payroll_runs', 'payroll_items'], 'payslips' => ['payslips'], 'sales-receivable' => ['sales_orders', 'customer_invoices', 'customer_collections'],
            'procurement' => ['purchase_requests', 'purchase_orders', 'goods_receipts', 'vendor_bills'], 'treasury' => ['treasury_transactions'],
            'projects' => ['projects'], 'project-overview' => ['projects'], 'maintenance-requests' => ['maintenance_requests'], 'maintenance-work-orders' => ['maintenance_work_orders'],
            'maintenance-plans' => ['preventive_maintenance_plans'], 'enterprise-documents' => ['enterprise_documents', 'enterprise_document_versions'],
            'notifications' => ['notifications'], 'products' => ['products'], 'product-categories' => ['product_categories'], 'seller-orders' => ['orders'],
            'commerce-payments' => ['commerce_payments'], 'pos-receipts' => ['branch_pos_receipts'], 'branch-customers' => ['commerce_customers'],
        ];
        if (isset($exact[$key])) {
            return $exact[$key];
        }
        if (str_contains($route, 'accounting')) {
            return ['payroll_accounting_journals'];
        }
        if (str_contains($route, 'inventory') || str_contains($route, 'warehouse')) {
            return ['inventory_balances', 'inventory_movements'];
        }

        return [];
    }

    /**
     * @param  list<string>  $tables
     * @return array{string,string,string}
     */
    private function states(array $tables, int $count, string $route): array
    {
        if ($tables === []) {
            return ['NOT_APPLICABLE', 'NOT_ENOUGH_EVIDENCE', 'AUDIT_OWNER_READ_MODEL'];
        }
        if ($count > 2) {
            return ['HEALTHY', 'PASS', 'NONE'];
        }
        if ($count > 0) {
            return ['SPARSE_BUT_VALID', 'PASS', 'MONITOR'];
        }
        if (str_contains($route, 'operations.') || str_contains($route, 'configuration')) {
            return ['EMPTY_EXPECTED', 'CONFIGURATION_REQUIRED', 'CONFIGURE_WHEN_DEPLOYED'];
        }

        return ['EMPTY_REQUIRES_OPERATING_DATA', 'PASS', 'POPULATE_ONLY_THROUGH_CANONICAL_OWNER_FLOW'];
    }

    private function tableCategory(string $table): string
    {
        return match (true) {
            str_contains($table, 'cache'), str_contains($table, 'jobs'), str_contains($table, 'sessions') => 'CACHE_SYSTEM',
            str_contains($table, 'audit'), str_contains($table, 'history'), str_contains($table, 'log') => 'AUDIT',
            str_contains($table, 'workflow'), str_contains($table, 'approval'), str_contains($table, 'request') => 'WORKFLOW',
            str_contains($table, 'pivot'), str_ends_with($table, '_user'), str_contains($table, '_has_') => 'PIVOT',
            str_contains($table, 'line'), str_contains($table, 'item'), str_contains($table, 'allocation'), str_contains($table, 'version') => 'BUSINESS_CHILD',
            str_contains($table, 'integration'), str_contains($table, 'webhook'), str_contains($table, 'external') => 'OPTIONAL_FEATURE',
            str_contains($table, 'polic'), str_contains($table, 'categor'), str_contains($table, 'template'), str_contains($table, 'definition') => 'BUSINESS_MASTER',
            default => 'BUSINESS_TRANSACTION',
        };
    }

    private function populationPolicy(string $table): string
    {
        $category = $this->tableCategory($table);

        return match ($category) {
            'CACHE_SYSTEM', 'AUDIT' => 'REMAIN_EMPTY_UNTIL_RUNTIME_EVENT',
            'BUSINESS_CHILD', 'PIVOT', 'WORKFLOW' => 'INDIRECT_ONLY_VIA_OWNER_WORKFLOW',
            'OPTIONAL_FEATURE' => 'REMAIN_EMPTY_UNTIL_CONFIGURED',
            default => 'REQUIRES_OWNER_DOMAIN_REVIEW_BEFORE_DATA',
        };
    }

    private function owner(string $route): string
    {
        return match (true) {
            str_starts_with($route, 'admin.sales.'), str_starts_with($route, 'admin.commerce.'), str_starts_with($route, 'admin.seller.') => 'Commerce',
            str_starts_with($route, 'admin.finance.') => 'Finance', str_starts_with($route, 'admin.procurement.') => 'Procurement',
            str_starts_with($route, 'admin.payroll.') => 'Payroll', str_starts_with($route, 'admin.maintenance.') => 'Maintenance',
            str_starts_with($route, 'admin.projects') => 'Project', str_starts_with($route, 'admin.documents.') => 'Document',
            str_starts_with($route, 'admin.products.'), str_starts_with($route, 'admin.product-categories.') => 'Product',
            str_starts_with($route, 'admin.employees.'), str_starts_with($route, 'admin.hr.') => 'Employee',
            str_starts_with($route, 'admin.iam.') => 'Iam', str_contains($route, 'dashboard'), str_contains($route, 'analytics') => 'Dashboard',
            default => 'CrossProject',
        };
    }

    private function upstream(string $key): string
    {
        return match ($key) {
            'sales-receivable' => 'Customer + Product + Inventory', 'procurement' => 'Vendor + Purchase Request', 'payroll-runs' => 'Employee + Attendance + Leave + OT + Salary', default => 'Owner-domain source records'
        };
    }

    private function downstream(string $key): string
    {
        return match ($key) {
            'sales-receivable', 'procurement' => 'Treasury + Accounting + Dashboard', 'payroll-runs' => 'Payslip + Tax/Insurance + Accounting', default => 'Owner read models + Dashboard/Reporting where configured'
        };
    }

    private function hasApproval(string $key): bool
    {
        return in_array($key, ['sales-receivable', 'procurement', 'finance-operations', 'payroll-runs', 'branch-bonuses', 'employee-lifecycle', 'hr-operations', 'approval-inbox'], true);
    }

    /** @param list<string> $keys */
    private function effect(string $key, array $keys): bool
    {
        return in_array($key, $keys, true);
    }

    /**
     * @param  array<string,int>  $counts
     * @return array<string,array<string,int>>
     */
    private function graphEvidence(array $counts): array
    {
        return [
            'organization_iam' => $this->pickCounts($counts, ['companies', 'branches', 'organization_units', 'cost_centers', 'employees', 'employee_assignments', 'users', 'roles', 'permissions', 'company_user_access']),
            'hr_payroll' => $this->pickCounts($counts, ['employees', 'attendance_records', 'leave_requests', 'overtime_requests', 'salary_assignments', 'payroll_input_snapshots', 'payroll_runs', 'payroll_items', 'payslips']),
            'sales_crm' => $this->pickCounts($counts, ['commerce_customers', 'orders', 'branch_pos_receipts', 'sales_orders', 'sales_fulfillments', 'customer_invoices', 'customer_collections', 'treasury_transactions']),
            'procurement' => $this->pickCounts($counts, ['vendors', 'purchase_requests', 'purchase_orders', 'goods_receipts', 'vendor_bills', 'vendor_payments']),
            'finance' => $this->pickCounts($counts, ['finance_expenses', 'finance_assets', 'payroll_accounting_journals', 'payroll_accounting_journal_lines', 'treasury_transactions']),
            'project' => $this->pickCounts($counts, ['projects', 'project_tasks', 'project_timesheets']), 'maintenance' => $this->pickCounts($counts, ['maintenance_requests', 'maintenance_work_orders', 'preventive_maintenance_plans']),
        ];
    }

    /**
     * @param  array<string,int>  $counts
     * @param  list<string>  $tables
     * @return array<string,int>
     */
    private function pickCounts(array $counts, array $tables): array
    {
        $selected = [];
        foreach ($tables as $table) {
            if (isset($counts[$table])) {
                $selected[$table] = $counts[$table];
            }
        }

        return $selected;
    }

    /** @return list<array<string,int|string|null>> */
    private function islands(): array
    {
        $findings = [];
        $checks = [
            ['employees', 'employee_assignments', 'employee_id', 'Employee without assignment', 'WARNING'],
            ['customer_invoices', 'payroll_accounting_journals', 'source_id', 'Invoice without accounting journal', 'BLOCKING'],
            ['sales_fulfillments', 'inventory_movements', 'source_id', 'Fulfillment without inventory movement', 'BLOCKING'],
        ];
        foreach ($checks as [$source, $target, $foreign, $label, $severity]) {
            if (! DB::getSchemaBuilder()->hasTable($source) || ! DB::getSchemaBuilder()->hasTable($target)) {
                continue;
            }
            $query = DB::table($source.' as s')->leftJoin($target.' as t', function (JoinClause $join) use ($foreign, $source): void {
                $join->on('t.'.$foreign, '=', 's.id');
                if ($source === 'customer_invoices') {
                    $join->where('t.source_type', '=', 'customer_invoice');
                }
                if ($source === 'sales_fulfillments') {
                    $join->where('t.source_type', '=', 'sales_fulfillment');
                }
            })->whereNull('t.id');
            foreach ($query->limit(100)->pluck('s.id') as $id) {
                if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                    $findings[] = ['record_type' => $source, 'id' => (int) $id, 'company' => null, 'expected_upstream' => 'Owner master/source', 'expected_downstream' => $target, 'current_evidence' => $label, 'severity' => $severity, 'action' => 'REVIEW_ONLY_NO_AUTO_FIX'];
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<int,array<string,mixed>>  $menus
     * @param  array<int,array{table:string,category:string,population_policy:string}>  $emptyTables
     * @param  array<string,array<string,int>>  $graph
     * @param  list<array<string,int|string|null>>  $islands
     */
    private function markdown(string $database, string $generatedAt, array $menus, array $emptyTables, array $graph, array $islands, int $tableCount): string
    {
        $lines = ['# Enterprise data completeness audit', '', 'Generated read-only from `'.$database.'` at '.$generatedAt.'.', '', '## Summary', '', '- Menu destinations: '.count($menus), '- Tables: '.$tableCount, '- Empty tables: '.count($emptyTables), '- Disconnected findings: '.count($islands), '', '## Menu classification', '', '| Group | Menu | Route | Owner | Rows | Data | Runtime | Action |', '|---|---|---|---|---:|---|---|---|'];
        foreach ($menus as $row) {
            $group = is_string($row['menu_group'] ?? null) ? $row['menu_group'] : 'Unknown';
            $label = is_string($row['menu_label'] ?? null) ? $row['menu_label'] : 'Unknown';
            $route = is_string($row['route'] ?? null) ? $row['route'] : 'unknown';
            $owner = is_string($row['owner_module'] ?? null) ? $row['owner_module'] : 'Unknown';
            $rows = is_int($row['current_row_count'] ?? null) ? (string) $row['current_row_count'] : 'N/A';
            $data = is_string($row['data_state'] ?? null) ? $row['data_state'] : 'NOT_APPLICABLE';
            $runtime = is_string($row['runtime_state'] ?? null) ? $row['runtime_state'] : 'NOT_ENOUGH_EVIDENCE';
            $action = is_string($row['action_required'] ?? null) ? $row['action_required'] : 'REVIEW';
            $lines[] = '| '.$group.' | '.$label.' | `'.$route.'` | '.$owner.' | '.$rows.' | '.$data.' | '.$runtime.' | '.$action.' |';
        }
        $lines[] = '';
        $lines[] = '## Empty-table classification';
        $lines[] = '';
        $lines[] = '| Table | Category | Population policy |';
        $lines[] = '|---|---|---|';
        foreach ($emptyTables as $row) {
            $lines[] = '| `'.$row['table'].'` | '.$row['category'].' | '.$row['population_policy'].' |';
        }
        $lines[] = '';
        $lines[] = '## Business graph evidence';
        $lines[] = '';
        $lines[] = 'Counts are source-table evidence, not cloned dashboard totals.';
        foreach ($graph as $domain => $evidence) {
            $lines[] = '';
            $lines[] = '### '.str_replace('_', ' / ', strtoupper($domain));
            foreach ($evidence as $table => $count) {
                $lines[] = '- `'.$table.'`: '.$count;
            }
        }
        $lines[] = '';
        $lines[] = '## Disconnected-island findings';
        $lines[] = '';
        $lines[] = $islands === [] ? 'No deterministic findings from implemented checks.' : 'Findings are review-only; this audit performs no repair.';
        foreach ($islands as $finding) {
            $severity = is_string($finding['severity'] ?? null) ? $finding['severity'] : 'INFO';
            $type = is_string($finding['record_type'] ?? null) ? $finding['record_type'] : 'record';
            $id = is_int($finding['id'] ?? null) ? $finding['id'] : 0;
            $evidence = is_string($finding['current_evidence'] ?? null) ? $finding['current_evidence'] : 'Review required';
            $lines[] = '- ['.$severity.'] '.$type.' #'.$id.': '.$evidence;
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}

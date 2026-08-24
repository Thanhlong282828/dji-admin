<?php

namespace App\Support\Navigation;

/**
 * @phpstan-type HelpStatus array{status:string,meaning:string,next:string}
 * @phpstan-type RelatedMenu array{label:string,route:string,parameters:array<string,int|string>,permission:string}
 * @phpstan-type MenuFlowNode array{key:string,label:string,route:string,parameters:array<string,int|string>,permission:string,current:bool}
 * @phpstan-type BaseHelpDefinition array{key:string,title:string,purpose:string,dataSources:list<string>,mainActions:list<string>,workflowSteps:list<string>,businessRules:list<string>,statusGuide:list<HelpStatus>,immutabilityRules:list<string>,approvalRules:list<string>,downstreamEffects:list<string>,relatedMenus:list<RelatedMenu>,permissions:list<string>,warnings:list<string>,technicalFlow:list<string>,fallback:bool,routePatterns:list<string>}
 * @phpstan-type HelpDefinition array{key:string,title:string,purpose:string,dataSources:list<string>,upstreamMenus:list<RelatedMenu>,downstreamMenus:list<RelatedMenu>,mainActions:list<string>,workflowSteps:list<string>,businessRules:list<string>,statusGuide:list<HelpStatus>,immutabilityRules:list<string>,approvalRules:list<string>,downstreamEffects:list<string>,sideEffects:list<string>,relatedMenus:list<RelatedMenu>,permissions:list<string>,warnings:list<string>,menuFlow:list<MenuFlowNode>,technicalFlow:list<string>,fallback:bool,routePatterns:list<string>}
 */
final class AdminHelpRegistry
{
    /** @return HelpDefinition */
    public function resolve(?string $routeName, int $companyId = 1): array
    {
        $route = $routeName ?? '';
        $matches = array_values(array_filter($this->definitions($companyId), static function (array $entry) use ($route): bool {
            foreach ($entry['routePatterns'] as $pattern) {
                $prefix = rtrim($pattern, '.*');
                if ($route === $prefix || str_starts_with($route, $prefix.'.')) {
                    return true;
                }
            }

            return false;
        }));
        usort($matches, static fn (array $a, array $b): int => self::longestPattern($b['routePatterns']) <=> self::longestPattern($a['routePatterns']));
        if ($matches !== []) {
            return $matches[0];
        }

        return $this->decorate($this->make('fallback', 'Hướng dẫn Control Center', 'Trang chi tiết chưa có guide riêng; hãy quay về menu cha.', ['Named route và dữ liệu đã authorization.'], ['Xem dữ liệu được cấp quyền.'], ['Mở menu cha → chọn bản ghi.'], ['Không bỏ qua validation hoặc permission.'], [], ['Không sửa history nếu không có action.'], ['Theo approval của module sở hữu.'], ['Quay về menu cha.'], [], [], ['Không đoán URL/action ẩn.'], ['Route → Controller sở hữu → Service/Query → View'], true), []);
    }

    /** @return list<HelpDefinition> */
    public function definitions(int $companyId = 1): array
    {
        $navigationItems = [];
        foreach ((new AdminNavigation)->definitions($companyId) as $group) {
            foreach ($group['items'] as $item) {
                $navigationItems[$item['key']] = $item;
            }
        }

        $result = [];
        foreach ($navigationItems as $item) {
            $entry = $this->guide($item['key'], $item['label'], $item['permission']);
            $entry['routePatterns'] = $item['patterns'];
            $entry['relatedMenus'] = array_map(
                static fn (string $relatedKey): array => [
                    'label' => $navigationItems[$relatedKey]['label'],
                    'route' => $navigationItems[$relatedKey]['route'],
                    'parameters' => $navigationItems[$relatedKey]['parameters'],
                    'permission' => $navigationItems[$relatedKey]['permission'],
                ],
                array_values(array_filter(
                    $this->relatedKeys($item['key']),
                    static fn (string $relatedKey): bool => isset($navigationItems[$relatedKey]),
                )),
            );
            $result[] = $this->decorate($entry, $navigationItems);
        }

        return $result;
    }

    /**
     * @param  BaseHelpDefinition  $entry
     * @param  array<string, array{key:string,label:string,route:string,patterns:list<string>,permission:string,parameters:array<string,int|string>}>  $navigationItems
     * @return HelpDefinition
     */
    private function decorate(array $entry, array $navigationItems): array
    {
        $flowKeys = $entry['fallback'] ? [] : $this->menuFlowKeys($entry['key']);
        $currentIndex = array_search($entry['key'], $flowKeys, true);
        if ($currentIndex === false && ! $entry['fallback']) {
            $flowKeys[] = $entry['key'];
            $currentIndex = array_key_last($flowKeys);
        }

        $nodes = [];
        foreach ($flowKeys as $key) {
            if (! isset($navigationItems[$key])) {
                continue;
            }
            $item = $navigationItems[$key];
            $nodes[] = [
                'key' => $key,
                'label' => $item['label'],
                'route' => $item['route'],
                'parameters' => $item['parameters'],
                'permission' => $item['permission'],
                'current' => $key === $entry['key'],
            ];
        }

        $upstream = [];
        $downstream = [];
        foreach ($nodes as $node) {
            if ($node['current']) {
                continue;
            }
            $nodeIndex = array_search($node['key'], $flowKeys, true);
            if (is_int($currentIndex) && is_int($nodeIndex) && $nodeIndex < $currentIndex) {
                $upstream[] = self::relatedNode($node);
            } else {
                $downstream[] = self::relatedNode($node);
            }
        }

        return $entry + [
            'upstreamMenus' => $upstream,
            'downstreamMenus' => $downstream,
            'sideEffects' => $entry['downstreamEffects'],
            'menuFlow' => $nodes,
        ];
    }

    /**
     * @param  MenuFlowNode  $node
     * @return RelatedMenu
     */
    private static function relatedNode(array $node): array
    {
        return [
            'label' => $node['label'],
            'route' => $node['route'],
            'parameters' => $node['parameters'],
            'permission' => $node['permission'],
        ];
    }

    /** @return non-empty-list<string> */
    private function menuFlowKeys(string $key): array
    {
        return match ($key) {
            'dashboard' => ['dashboard'],
            'companies' => ['companies', 'organization-units', 'employees'],
            'cost-centers' => ['companies', 'cost-centers', 'organization-units', 'job-positions', 'employees'],
            'organization-units', 'organization-tree' => ['companies', 'cost-centers', 'organization-units', 'organization-tree', 'job-positions', 'employees'],
            'job-positions', 'headcount' => ['organization-units', 'job-positions', 'headcount', 'employees', 'employee-lifecycle'],
            'employees' => ['organization-units', 'job-positions', 'employees', 'employee-lifecycle', 'attendance', 'payroll-inputs'],
            'employee-lifecycle' => ['employees', 'employee-lifecycle', 'payroll-dashboard', 'payroll-inputs'],
            'hr-governance' => ['employees', 'hr-governance', 'audit'],
            'shifts' => ['employees', 'shifts', 'attendance'],
            'attendance' => ['employees', 'shifts', 'attendance', 'payroll-inputs', 'payroll-runs'],
            'leave-management' => ['employees', 'leave-management', 'leave', 'payroll-inputs'],
            'leave' => ['employees', 'leave-management', 'leave', 'attendance', 'payroll-inputs'],
            'overtime' => ['employees', 'shifts', 'attendance', 'overtime', 'payroll-inputs'],
            'recruitment', 'requisitions', 'candidates', 'applications', 'interviews', 'candidate-offers', 'onboarding' => ['requisitions', 'candidates', 'applications', 'interviews', 'candidate-offers', 'onboarding', 'employees'],
            'performance', 'career' => ['employees', 'performance', 'career', 'employee-lifecycle'],
            'analytics' => ['employees', 'organization-units', 'attendance', 'leave', 'recruitment', 'payroll-runs', 'workflow', 'iam', 'data-quality', 'analytics', 'forecasting', 'executive-planning', 'planning-execution'],
            'forecasting' => ['organization-units', 'headcount', 'employees', 'recruitment', 'analytics', 'forecasting', 'executive-planning', 'planning-execution'],
            'executive-planning' => ['organization-units', 'headcount', 'employees', 'recruitment', 'analytics', 'forecasting', 'executive-planning', 'planning-execution'],
            'planning-execution' => ['executive-planning', 'planning-execution', 'employees', 'recruitment', 'payroll-runs', 'enterprise-reports'],
            'enterprise-reports' => ['employees', 'attendance', 'recruitment', 'payroll-runs', 'analytics', 'forecasting', 'executive-planning', 'planning-execution', 'enterprise-reports'],
            'payroll-dashboard' => ['employees', 'payroll-dashboard', 'payroll-inputs', 'payroll-runs'],
            'payroll-periods' => ['payroll-dashboard', 'payroll-periods', 'payroll-inputs', 'payroll-runs'],
            'payroll-inputs' => ['employees', 'attendance', 'leave', 'overtime', 'payroll-periods', 'payroll-inputs', 'payroll-runs'],
            'payroll-runs' => ['payroll-inputs', 'payroll-runs', 'payslips', 'payments', 'accounting'],
            'payroll-adjustments' => ['payroll-runs', 'payroll-adjustments', 'payslips'],
            'payslips' => ['payroll-runs', 'payslips'],
            'payments' => ['payroll-runs', 'payments', 'accounting'],
            'accounting' => ['payroll-runs', 'accounting-configuration', 'accounting', 'erp'],
            'accounting-configuration' => ['payroll-dashboard', 'accounting-configuration', 'accounting'],
            'erp' => ['accounting', 'erp-configuration', 'erp', 'integrations'],
            'erp-configuration' => ['accounting-configuration', 'erp-configuration', 'erp'],
            'tax-administration', 'tax-policies' => ['employees', 'tax-policies', 'tax-administration', 'payroll-runs'],
            'insurance-administration', 'insurance-policies' => ['employees', 'insurance-policies', 'insurance-administration', 'payroll-runs'],
            'government' => ['payroll-runs', 'government'],
            'annual-tax' => ['payroll-runs', 'annual-tax', 'accounting'],
            'workflow-definitions', 'workflow', 'workflow-tasks' => ['workflow-definitions', 'workflow', 'workflow-tasks', 'notifications', 'audit'],
            'notification-templates', 'notifications', 'notification-deliveries' => ['notification-templates', 'notifications', 'notification-deliveries', 'integrations'],
            'integrations' => ['workflow', 'notifications', 'integrations', 'audit'],
            'roles-permissions', 'access-requests', 'access-reviews', 'privileged', 'segregation-of-duties', 'iam' => ['roles-permissions', 'access-requests', 'access-reviews', 'privileged', 'segregation-of-duties', 'iam', 'audit'],
            'audit' => ['employees', 'workflow', 'iam', 'integrations', 'audit'],
            'api-documentation', 'api-contracts' => ['api-documentation', 'api-contracts', 'releases'],
            'configuration' => ['configuration', 'reliability', 'releases'],
            'secrets' => ['secrets', 'security-assurance', 'releases'],
            'data-protection' => ['data-protection', 'data-lifecycle', 'data-quality'],
            'data-lifecycle' => ['data-protection', 'data-lifecycle', 'audit'],
            'data-quality' => ['employees', 'attendance', 'payroll-runs', 'data-quality'],
            'reliability', 'observability', 'performance-engineering' => ['observability', 'performance-engineering', 'reliability', 'releases'],
            'architecture-governance', 'test-quality' => ['architecture-governance', 'test-quality', 'releases'],
            'releases' => ['architecture-governance', 'test-quality', 'security-assurance', 'releases'],
            'continuity' => ['reliability', 'continuity', 'releases'],
            'security-assurance' => ['secrets', 'data-protection', 'security-assurance', 'releases'],
            'go-live-readiness' => ['architecture-governance', 'test-quality', 'security-assurance', 'releases', 'go-live-readiness'],
            'articles' => ['articles', 'article-create'],
            'article-create' => ['articles', 'article-create'],
            default => [$key],
        };
    }

    /** @param non-empty-list<string>|list<string> $patterns */
    private static function longestPattern(array $patterns): int
    {
        $longest = 0;
        foreach ($patterns as $pattern) {
            $longest = max($longest, strlen($pattern));
        }

        return $longest;
    }

    /** @return list<string> */
    private function relatedKeys(string $key): array
    {
        return match ($key) {
            'companies' => ['organization-units', 'employees'],
            'cost-centers' => ['organization-units', 'headcount'],
            'organization-units', 'organization-tree' => ['cost-centers', 'job-positions', 'headcount', 'employees'],
            'job-positions' => ['organization-units', 'employees', 'employee-lifecycle'],
            'headcount' => ['organization-units', 'job-positions', 'employees'],
            'employees' => ['employee-lifecycle', 'job-positions', 'attendance'],
            'employee-lifecycle' => ['employees', 'job-positions', 'payroll-inputs'],
            'attendance' => ['employees', 'leave', 'shifts', 'payroll-inputs'],
            'leave' => ['employees', 'attendance', 'leave-management', 'payroll-inputs'],
            'leave-management' => ['leave', 'employees'],
            'overtime' => ['attendance', 'payroll-inputs'],
            'shifts' => ['attendance', 'overtime'],
            'onboarding' => ['candidate-offers', 'recruitment', 'employees', 'employee-lifecycle'],
            'recruitment' => ['requisitions', 'candidates', 'applications', 'interviews', 'candidate-offers', 'onboarding'],
            'requisitions' => ['recruitment', 'applications', 'job-positions'],
            'candidates' => ['applications', 'interviews', 'candidate-offers'],
            'applications' => ['requisitions', 'candidates', 'interviews'],
            'interviews' => ['applications', 'candidate-offers'],
            'candidate-offers' => ['candidates', 'onboarding', 'employees'],
            'performance', 'career', 'hr-governance' => ['employees', 'employee-lifecycle'],
            'analytics' => ['employees', 'organization-units', 'attendance', 'leave', 'recruitment', 'payroll-runs', 'workflow', 'iam', 'data-quality', 'forecasting', 'executive-planning', 'planning-execution'],
            'forecasting' => ['analytics', 'headcount', 'job-positions', 'requisitions', 'payroll-dashboard', 'executive-planning', 'planning-execution'],
            'executive-planning' => ['analytics', 'forecasting', 'headcount', 'payroll-runs', 'requisitions', 'planning-execution'],
            'planning-execution' => ['executive-planning', 'forecasting', 'headcount', 'requisitions', 'payroll-runs', 'enterprise-reports'],
            'enterprise-reports' => ['analytics', 'forecasting', 'executive-planning', 'planning-execution', 'data-quality'],
            'payroll-dashboard' => ['payroll-periods', 'payroll-inputs', 'payroll-runs'],
            'payroll-periods' => ['payroll-inputs', 'payroll-runs'],
            'payroll-inputs' => ['payroll-periods', 'employees', 'attendance', 'payroll-runs'],
            'payroll-runs' => ['payroll-inputs', 'payroll-adjustments', 'payslips', 'payments', 'accounting'],
            'payroll-adjustments' => ['payroll-runs', 'payslips'],
            'annual-tax' => ['payroll-runs', 'government'],
            'payslips' => ['payroll-runs', 'payments'],
            'payments' => ['payroll-runs', 'accounting'],
            'accounting' => ['payroll-runs', 'erp'],
            'accounting-configuration' => ['accounting'],
            'erp' => ['accounting', 'erp-configuration', 'integrations'],
            'erp-configuration' => ['erp', 'integrations'],
            'tax-administration' => ['tax-policies', 'payroll-runs'],
            'tax-policies' => ['tax-administration'],
            'insurance-administration' => ['insurance-policies', 'payroll-runs'],
            'insurance-policies' => ['insurance-administration'],
            'government' => ['annual-tax', 'payroll-runs', 'integrations'],
            'workflow' => ['workflow-definitions', 'workflow-tasks', 'notifications', 'audit'],
            'workflow-definitions', 'workflow-tasks' => ['workflow'],
            'notifications' => ['notification-templates', 'notification-deliveries', 'workflow', 'integrations'],
            'notification-templates', 'notification-deliveries' => ['notifications'],
            'integrations' => ['notifications', 'audit'],
            'audit' => ['security-assurance', 'data-lifecycle'],
            'iam' => ['access-requests', 'roles-permissions', 'privileged', 'access-reviews', 'audit'],
            'access-requests', 'roles-permissions', 'privileged', 'segregation-of-duties', 'access-reviews' => ['iam', 'audit'],
            'api-documentation' => ['api-contracts'],
            'api-contracts' => ['api-documentation', 'architecture-governance'],
            'data-quality' => ['employees', 'attendance', 'payroll-runs'],
            'data-lifecycle' => ['data-protection', 'audit'],
            'reliability', 'observability', 'performance-engineering' => ['releases'],
            'releases' => ['reliability', 'security-assurance'],
            'go-live-readiness' => ['releases', 'reliability', 'continuity', 'security-assurance'],
            default => [],
        };
    }

    /** @return BaseHelpDefinition */
    private function guide(string $key, string $label, string $permission): array
    {
        $simple = fn (string $purpose, string $sources, string $actions, string $flow, string $warning): array => $this->make($key, $label, $purpose, [$sources], [$actions], ['Chọn phạm vi/bản ghi → thực hiện action đang hiển thị → kiểm tra kết quả.'], ['Giữ company scope, validation và state machine của module.'], [], ['History/evidence đã phát hành không sửa trực tiếp.'], ['Approval chỉ theo action và permission thực.'], ['Kết quả quay về dashboard/list của module.'], [], [$permission], [$warning], [$flow]);
        $ops = fn (string $purpose, string $source, string $flow): array => $this->make($key, $label, $purpose, [$source], ['Xem evidence, trạng thái và finding.'], ['Chọn scope → đọc trạng thái → xử lý tại module/runbook sở hữu.'], ['Trang này là operational/governance evidence; không mutate business data.'], [], ['Evidence/checksum đã phát hành không sửa trực tiếp.'], ['Không duyệt business data tại trang này.'], ['Finding dẫn về module hoặc runbook sở hữu.'], [], [$permission], ['Không dùng số liệu local như production SLA.'], [$flow]);

        return match ($key) {
            'dashboard' => $simple('Xem tổng quan hoạt động theo công ty.', 'Company và read models Employee/Attendance/Leave/Payroll/Recruitment.', 'Xem KPI và mở menu nguồn.', 'DashboardController → scoped read models → Blade', 'Dashboard không phải nơi sửa dữ liệu nguồn.'),
            'companies' => $simple('Quản lý tenant công ty và access scope.', 'companies và company_user_access.', 'Tạo, xem, sửa, deactivate/soft-delete theo action hiện có.', 'CompanyController → CommandBus → Company repository', 'Không chuyển tenant bằng cách sửa company_id business record.'),
            'cost-centers' => $simple('Quản lý trung tâm chi phí.', 'Company và cost_centers.', 'Tạo/xem/sửa/archive theo route.', 'CostCenterController → CommandBus → repository', 'Code phải unique trong company.'),
            'organization-units' => $simple('Quản lý đơn vị/phòng ban và hierarchy.', 'Company, Organization Unit, Cost Center.', 'Tạo/xem/sửa/move theo route.', 'OrganizationUnitController → Organization service → repository', 'Không tạo parent cycle hoặc cross-company parent.'),
            'organization-tree' => $simple('Xem và di chuyển cây tổ chức.', 'Organization Units và parent relationships.', 'Mở node, xem headcount, move nếu được phép.', 'Tree query → bounded hierarchy builder → Blade', 'Không đặt node dưới chính nó hoặc descendant.'),
            'job-positions' => $simple('Quản lý chức vụ trong Unit và Level 1–6.', 'Organization Unit, Job Position, headcount.', 'Tạo/xem/sửa, critical status, approved headcount.', 'JobPositionController → CommandBus → repository', 'Position phải thuộc Unit cùng company; Level bắt buộc 1–6.'),
            'headcount' => $simple('Đối chiếu approved, occupied headcount và vacancy.', 'Unit, Position và current Employee Assignment.', 'Lọc/xem/export nếu route cung cấp.', 'HeadcountController → aggregate query → Blade', 'Occupied lấy từ assignment, không nhập tay.'),
            'employees' => $this->make($key, $label, 'Quản lý hồ sơ Employee, account login và current primary assignment.', ['Employee, User/IAM, Unit, Position, manager, avatar storage.'], ['Thêm, xem, sửa, Employee 360, account enable/disable, avatar, archive.'], ['Tạo Employee → chọn Unit → Position → Manager → tùy chọn account → lưu → mở Lifecycle.'], ['Position phải thuộc Unit; không self-manager; đúng một current primary assignment; disable login không xóa Employee.'], $this->statuses([['active/probation', 'Đang làm việc', 'Có thể edit/lifecycle'], ['terminated/archived', 'Giữ lịch sử', 'Chỉ action được policy cho phép']]), ['Archive giữ history/avatar; không hard-delete ngầm.'], ['Account/role thay đổi theo IAM permission riêng.'], ['Employee xuất hiện trong Lifecycle, Attendance, Payroll và analytics.'], [], [$permission], ['Không chọn manager/position khác company.'], ['EmployeeController → CommandBus → EmployeeLifecycleService → EmployeeAssignmentService → repositories']),
            'employee-lifecycle' => $this->make($key, $label, 'Quản lý vòng đời, assignment, promotion/demotion và history.', ['Employee, primary Assignment, Job Position/career_level, Compensation và histories.'], ['Khởi tạo, onboarding, transfer, promotion, demotion, termination theo state/quyền.'], ['Mở Employee 360 → chọn action → Unit/Position hợp lệ → effective date → reason → lưu.'], ['Promotion cần level cao hơn; demotion cần level thấp hơn; projected compensation không tự ghi salary.'], $this->statuses([['not_started', 'Chưa khởi tạo', 'Khởi tạo'], ['hired/onboarding', 'Đang tiếp nhận', 'Start/complete onboarding'], ['active/probation/confirmed', 'Đang vận hành', 'Career action/termination'], ['terminated/archived', 'Đã kết thúc', 'Đọc history']]), ['Timeline, position/manager history và finalized Payroll không sửa trực tiếp.'], ['Career dùng hr.lifecycle.*; salary thực dùng Payroll configuration riêng.'], ['Employee List/360/Lifecycle đọc assignment mới; Payroll history cũ không đổi.'], [], [$permission, 'payroll.assignment.view (xem compensation)'], ['Target Gross projection không phải Net Pay.'], ['EmployeeLifecycleController → CommandBus → EmployeeLifecycleService → EmployeeAssignmentService → history/events']),
            'attendance' => $this->make($key, $label, 'Vận hành check-in/out, approval, dashboard ngày/tuần và export.', ['Employee/current assignment, shift/work calendar, attendance, overtime approvals.'], ['Tạo/cập nhật hợp lệ, approve/reject, filter, CSV/XLSX, dashboard.'], ['Chọn Employee/ngày → nhập check-in/out → validate → approve nếu cần.'], ['Check-out không trước check-in; Reverb failure không rollback core write.'], $this->statuses([['pending', 'Chờ duyệt', 'Approve/reject'], ['approved', 'Được chấp nhận', 'Payroll Input'], ['rejected', 'Không chấp nhận', 'Sửa source qua action hợp lệ']]), ['Không hard-delete history đã dùng làm evidence.'], ['Approval permission tách khỏi view/edit.'], ['Attendance/overtime vào Payroll Input; Reverb refresh khi khả dụng.'], [], [$permission], ['Không sửa DB trực tiếp để đổi giờ công.'], ['AttendanceController → service/read model → event/Reverb → export']),
            'leave' => $this->make($key, $label, 'Tạo và duyệt yêu cầu nghỉ phép.', ['Employee, leave policy/balance, leave_requests.'], ['Tạo, xem, approve/reject theo permission.'], ['Chọn loại/ngày → submit → reviewer quyết định.'], ['Ngày/balance phải hợp lệ.'], $this->statuses([['pending', 'Chờ duyệt', 'Approve/reject'], ['approved', 'Được chấp nhận', 'Attendance/Payroll source'], ['rejected/cancelled', 'Không áp dụng', 'Giữ history']]), ['Decision history không sửa trực tiếp.'], ['Reviewer dùng permission/policy Leave.'], ['Approved Leave được workforce/payroll đọc khi cấu hình.'], [], [$permission], ['Không tự sửa balance để request pass.'], ['LeaveController → Leave service/state transition → repository/events']),
            'leave-management' => $this->make($key, $label, 'Quản lý policy, assignment, balance và accrual nghỉ phép.', ['Leave types, policies, Employee assignment và balance ledger.'], ['Tạo/deactivate policy, gán policy, opening/adjust balance, accrual/carry-forward/expiry.'], ['Tạo policy → gán Employee → mở balance → chạy nghiệp vụ được phép.'], ['Idempotency và effective date bảo vệ balance; ledger là nguồn giải thích thay đổi.'], [], ['Ledger/history không sửa trực tiếp.'], ['Policy/balance action cần leave.manage-policy.'], ['Policy/balance được Leave Request và workforce read model sử dụng.'], [], [$permission], ['Không chỉnh balance trực tiếp trong DB.'], ['LeaveManagementController → workforce CommandBus → leave services/repositories → ledger']),
            'overtime' => $this->make($key, $label, 'Quản lý policy, assignment và yêu cầu tăng ca.', ['Employee, Attendance, overtime policy/assignment/request.'], ['Tạo policy/assignment/request; submit, approve/reject, reconcile Attendance.'], ['Tạo draft → submit → reviewer approve/reject → reconcile actual time.'], ['Requested/approved/actual/payable minutes là các giá trị khác nhau.'], $this->statuses([['draft', 'Đang soạn', 'Submit'], ['submitted', 'Chờ duyệt', 'Approve/reject'], ['approved', 'Đã duyệt', 'Reconcile'], ['rejected/cancelled', 'Không tính', 'Giữ history']]), ['Decision/reconciliation history không sửa trực tiếp.'], ['Approve tách khỏi request permission.'], ['Payable overtime đi tới Payroll Input khi được reconcile.'], [], [$permission], ['Không nhập payable minutes để bỏ qua Attendance.'], ['OvertimeController → CommandBus → overtime services → Attendance reconciliation → Payroll Input']),
            'shifts' => $simple('Quản lý shift, work calendar và preset workforce.', 'Shift definitions, breaks, work calendar/profile.', 'Tạo/cập nhật cấu hình theo section hiện có.', 'WorkforceManagementController → workforce services → schedule tables', 'Sửa shift tương lai không được rewrite Attendance history.'),
            'onboarding' => $simple('Theo dõi onboarding case, checklist và required task.', 'Hired Candidate/Employee và onboarding case/items.', 'Xem case, complete task, transition khi đủ điều kiện.', 'OnboardingController → Onboarding service → case/checklist/events', 'Required item chưa xong chặn completion.'),
            'recruitment' => $simple('Quản lý Requisition → Candidate → Application → Interview/Offer → Hire.', 'Requisition, Candidate, Application, Interview, Offer.', 'CRUD và stage/state actions đang hiển thị.', 'Recruitment controllers → state services → HireCandidateService → Employee/Onboarding', 'Không transition sai state hoặc upload PII thật vào demo.'),
            'requisitions' => $simple('Quản lý nhu cầu/vacancy tuyển dụng và vòng đời requisition.', 'Job Position, requester Employee và recruitment_requisitions.', 'Tạo draft, chuyển approval/open/paused theo state.', 'RecruitmentController::requisitions → repository → transition service → view', 'Không mở requisition khi position/company scope không hợp lệ.'),
            'candidates' => $simple('Quản lý hồ sơ ứng viên và consent tuyển dụng.', 'Candidate, consent metadata và private resume reference.', 'Tạo, tìm kiếm và mở hồ sơ theo permission.', 'RecruitmentController::candidates → read repository → candidate service → view', 'Không dùng PII thật trong demo hoặc public file URL.'),
            'applications' => $simple('Theo dõi application theo requisition và stage pipeline.', 'Candidate, Requisition, Application và stage history.', 'Lọc pipeline và transition stage theo action hợp lệ.', 'RecruitmentController::pipeline → application read repository → view', 'Không bỏ qua state machine để chuyển stage.'),
            'interviews' => $simple('Lên lịch, đánh giá và theo dõi interview.', 'Application, interviewer assignments và evaluation.', 'Schedule, reschedule, assign, evaluate và transition theo permission.', 'InterviewController → interview/evaluation services → events → view', 'Draft/private evaluation không lộ cho actor không được phép.'),
            'candidate-offers' => $simple('Quản lý offer và hand-off Hire Candidate.', 'Candidate/Application, offer version/document và reservation.', 'Tạo, xem, transition, download và hire khi accepted/reserved.', 'CandidateOfferController → offer service → HireCandidateService → Employee/Onboarding', 'Không hire lặp hoặc tải private document khi thiếu permission.'),
            'analytics' => $this->make($key, $label, 'Cung cấp quyết định quản trị từ số liệu tổng hợp Workforce, Attendance, Leave, Recruitment, Payroll, Organization, Workflow, IAM và Data Quality.', ['Employee/current primary Assignment, Organization Unit/Position, Attendance, Leave, Recruitment funnel, Payroll results, salary assignments, Workflow/IAM và Data Quality snapshot.'], ['Lọc theo company/unit/position/level/date; xem KPI và distribution; xuất CSV aggregate khi có quyền.'], ['Chọn phạm vi → đọc Executive Summary → mở section nguyên nhân → sửa dữ liệu tại menu sở hữu → tải lại analytics.'], ['Mọi query tenant-scoped; không có query-per-employee; số tiền chỉ được tạo trong HTML khi có payroll.assignment.view.'], [], ['Workforce snapshot đã phát hành có checksum/version và không sửa trực tiếp.'], ['Analytics không duyệt business data; approval thực hiện tại module nguồn.'], ['Finding dẫn về Employee/Organization/Attendance/Leave/Recruitment/Payroll/Workflow/IAM/Data Quality; Attendance correction chỉ vào Payroll qua snapshot/version hiện hữu.'], [], [$permission, 'payroll.assignment.view (xem số tiền)', 'analytics.export (xuất aggregate)'], ['Không dùng dashboard để sửa DB, không xem local timing như production SLA, không coi Target Gross là Net Pay.'], ['WorkforceAnalyticsController → AnalyticsFilter → WorkforceDashboardQuery → WorkforceDashboardHandler → WorkforceAnalyticsService → EnterpriseAnalyticsService → grouped SQL/ViewModel']),
            'forecasting' => $this->make($key, $label, 'Lập kế hoạch headcount, recruitment demand và target payroll bằng mô hình deterministic, không phải AI prediction.', ['Approved headcount/Job Position, current primary Assignment, salary assignments/position compensation reference và open Recruitment requisitions.'], ['Chọn tháng/unit; thêm tối đa 50 dòng add/remove/promote; compare baseline/scenario; reset hoặc export CSV.'], ['Chọn scope → nhập dòng what-if và effective month → Compare Scenario → đọc delta/cảnh báo → export nếu cần.'], ['Scenario chỉ tồn tại trong request, không Save; effective month tương lai chưa được tính; promotion phải đi từ level thấp lên level cao.'], [], ['Payroll historical result, Employee assignment và salary assignment không bị sửa.'], ['Forecast không thực hiện approval; headcount/requisition/salary thật phải qua module sở hữu.'], ['Kết quả chỉ là planning estimate; dữ liệu sai phải sửa ở Organization, Employee, Recruitment hoặc Payroll Configuration.'], [], [$permission, 'payroll.assignment.view (xem cost)', 'analytics.export (export scenario)'], ['Target Gross không phải Net Pay; tax/insurance/employer cost không được suy diễn khi thiếu policy; không coi signal là AI.'], ['WorkforceForecastController → WorkforceScenarioLine → WorkforceForecastService → grouped SQL/CompensationReadService → WorkforceForecastViewModel']),
            'executive-planning' => $this->make($key, $label, 'Management cockpit đối chiếu Budget, Actual và Latest Forecast để hỗ trợ quyết định headcount, payroll cost và hiring coverage.', ['Approved Headcount/Job Position budget, current primary Employee Assignment, Position Compensation Reference, Payroll Run, open Recruitment pipeline, Analytics/Forecasting và Data Quality snapshot.'], ['Chọn kỳ/company/scope; đọc KPI và cảnh báo; drill-down module sở hữu; xuất CSV aggregate khi có quyền.'], ['Chuẩn hóa Organization/Headcount → kiểm tra Actual Employee/Payroll → xem Latest Forecast → đối chiếu Variance → xử lý tại module nguồn.'], ['Budget, Actual, Forecast và Scenario là bốn semantics riêng; cockpit chỉ tính read-only và không tạo Approved Plan giả.'], [], ['Historical Payroll Run/Item không bị sửa; scenario 3F-26 vẫn request-scoped.'], ['Chưa có authority/version contract cho persistent Plan nên approval/versioning là N/A; phê duyệt business record vẫn ở module sở hữu.'], ['Vacancy dẫn về Headcount/Recruitment; financial variance dẫn về Payroll/Analytics; forecast dẫn về Workforce Forecasting.'], [], [$permission, 'payroll.assignment.view (xem budget/cost)', 'analytics.export (xuất aggregate)'], ['Không coi forecast là committed payroll; financial values bị loại khỏi HTML khi thiếu salary permission; cảnh báo Data Quality phải xử lý tại module sở hữu.'], ['ExecutivePlanningController → PlanningFilter → ExecutivePlanningService → grouped SQL/CompensationReadService/DataQualityAuditService → ExecutivePlanningViewModel → Blade']),
            'planning-execution' => $this->make($key, $label, 'Theo dõi monthly operating Target so với Actual, Variance và explicit Reforecast để quản lý hành động mà không sửa dữ liệu nguồn.', ['Executive Planning baseline, approved Headcount, current primary Employee Assignment, completed Candidate Offer hires, eligible Payroll Run và Data Quality snapshot.'], ['Lọc phạm vi; đối chiếu Target/Actual; nhập delta Reforecast v2 tạm thời; xem root cause, management attention; xuất CSV aggregate.'], ['Executive Planning → Planning Execution → đọc Actual → tính Variance → so sánh Reforecast v2 → xử lý tại Headcount, Recruitment hoặc Payroll.'], ['Target là operating target tính từ approved data; Actual là record thật; Forecast v1 giữ nguyên; Reforecast v2 chỉ tồn tại trong request; action category deterministic và không tự chạy.'], [], ['Historical Payroll và Forecast v1 không bị sửa; không có persistent target/snapshot trong phase này.'], ['Approval/versioning là N/A vì không lưu target/reforecast; business action vẫn theo Workflow/IAM tại module sở hữu.'], ['Headcount variance dẫn về Organization/Employee; hiring gap dẫn về Recruitment; payroll variance dẫn về Payroll; reforecast dẫn về Forecasting/Planning.'], [], [$permission, 'payroll.assignment.view (xem financial execution)', 'analytics.export (xuất aggregate)'], ['Không coi Reforecast là approved budget; thiếu salary permission thì financial values không được render trong HTML; Data Quality blocking phải xử lý tại module sở hữu.'], ['PlanningExecutionController → PlanningExecutionFilter → PlanningExecutionService → ExecutivePlanningService/grouped actual-hire query → ExecutionVarianceMath → PlanningExecutionViewModel → Blade']),
            'enterprise-reports' => $this->make($key, $label, 'Tạo live management report và Executive Board Pack từ các read model doanh nghiệp đã được quản trị.', ['Organization, Employee, Attendance, Leave, Recruitment, Payroll, Analytics, Forecasting, Planning Execution, IAM, Workflow và Data Quality.'], ['Chọn monthly/quarterly/YTD và phạm vi; Preview; xuất CSV, XLSX hoặc PDF khi có quyền.'], ['Operational Menus → Analytics/Forecasting/Planning → Enterprise Reports → Management Review.'], ['Report tái sử dụng công thức/read model nguồn; không sửa business data, không tạo narrative AI và không gọi external BI.'], [], ['Live report không immutable/certified vì không được persist; historical Payroll/Audit source vẫn giữ immutability riêng.'], ['Report không có approval; management review diễn ra ngoài live generation cho đến khi có artifact authority thật.'], ['Số liệu sai phải sửa tại menu nguồn rồi regenerate; export là live bytes với SHA-256 response evidence.'], [], [$permission, 'analytics.export (xuất)', 'payroll.assignment.view (Payroll/Compensation)'], ['Thiếu salary permission thì section Restricted không tồn tại trong HTML/XLSX/PDF; Data Quality warning không tự sửa nguồn.'], ['EnterpriseReportController → ReportingFilter → EnterpriseReportingService → EnterpriseAnalyticsService/PlanningExecutionService → EnterpriseReportViewModel → HTML/EnterpriseReportExportService']),
            'performance', 'career', 'hr-governance' => $simple($label.' theo company và Employee scope.', 'Employee và records/snapshots thuộc module '.$label.'.', 'Xem dashboard/detail; action chỉ theo permission thực.', $label.'Controller → Query/CommandBus → service/repository → ViewModel', 'Snapshot/history đã phát hành không phải source record để sửa.'),
            'payroll-dashboard' => $simple('Quản lý salary components, Employee assignments và tổng quan Payroll delivery.', 'Salary components, assignments và Payroll Runs.', 'Tạo component/assignment và mở Run/Input theo permission.', 'PayrollDeliveryController::index → PayrollDeliveryReadRepository → delivery view', 'Không sửa component assignment để rewrite finalized Payroll history.'),
            'payroll-periods' => $this->make($key, $label, 'Quản lý kỳ lương: start/end/cutoff/payment date và version.', ['Company payroll calendar và payroll_periods.'], ['Tạo/xem/sửa khi state cho phép; lock/open theo route.'], ['Tạo Period → kiểm tra ngày → tạo Payroll Input.'], ['Khoảng ngày không overlap; locked period không sửa tùy tiện.'], $this->statuses([['open/draft', 'Đang chuẩn bị', 'Tạo input'], ['locked/closed', 'Khóa nguồn kỳ', 'Đọc/tiếp tục run hợp lệ']]), ['Period có snapshot/run history không sửa ngược.'], ['Transition theo payroll-period permission.'], ['Payroll Inputs và Runs dùng Period.'], [], [$permission], ['Không đổi ngày kỳ để né validation.'], ['PayrollPeriodController → PayrollPeriodOperationsService → StateMachine']),
            'payroll-inputs' => $this->make($key, $label, 'Tạo point-in-time snapshot đầu vào tính lương.', ['Payroll Period, Employee/current assignment, Attendance, Leave, Overtime, Salary Assignment.'], ['Tạo snapshot, xem Employee inputs, validate, tạo version, tạo Run khi ready.'], ['Chọn Period → tạo snapshot → xem validation → sửa source nếu sai → tạo version mới → create Run.'], ['Idempotency ngăn duplicate logical snapshot; không tạo lặp để cộng tiền.'], $this->statuses([['draft/generating', 'Đang tạo', 'Validate'], ['ready/validated', 'Đủ điều kiện', 'Create Payroll Run'], ['blocked/validation_failed', 'Có blocking issue', 'Sửa source/tạo version']]), ['Snapshot là point-in-time evidence; sửa nguồn rồi tạo version mới.'], ['Generate/validate/create-run dùng permission riêng.'], ['Snapshot ready đi tới Payroll Runs.'], [], [$permission], ['Không chỉnh snapshot DB để sửa Attendance/Salary.'], ['PayrollInputController → PayrollInputOperationsService → source capture/validation → snapshot → PayrollRun']),
            'payroll-runs' => $this->make($key, $label, 'Calculate, validate, submit, approve và finalize Payroll.', ['Period, immutable Input Snapshot, Salary Assignment, tax/insurance policies.'], ['Calculate, validate, submit, approve/reject, finalize/cancel theo state machine.'], ['Ready Input → Calculate → xử lý issue → Submit → Approve → Finalize.'], ['Không finalize khi blocking; calculate retry idempotent, không cộng tiền.'], $this->statuses([['draft', 'Chưa tính', 'Calculate'], ['calculating', 'Đang tính', 'Chờ'], ['calculated', 'Đã tính', 'Validate/submit'], ['validation_failed', 'Có lỗi', 'Sửa source/recalculate'], ['submitted', 'Chờ duyệt', 'Approve/reject'], ['approved', 'Đã duyệt', 'Finalize'], ['finalized', 'Kết quả khóa', 'Payslip/payment/accounting'], ['failed/cancelled/rejected', 'Không tiếp tục hiện tại', 'Theo StateMachine']]), ['Finalized Run/Item/breakdown không sửa; dùng Adjustment.'], ['Maker-checker theo policy/permission.'], ['Finalized Run cấp Payslip, Payment, Accounting, ERP/Tax evidence.'], [], [$permission], ['Không bấm Calculate nhiều lần để cộng thêm tiền.'], ['PayrollRunController → PayrollRunService/StateMachine → items/breakdowns → downstream']),
            'payroll-adjustments' => $simple('Điều chỉnh sai lệch mà không sửa finalized Payroll history.', 'Finalized Payroll Item, reason/components, validation evidence.', 'Create, validate, review, approve/apply theo lifecycle.', 'PayrollAdjustmentController → PayrollAdjustmentManagementService', 'Original item immutable; adjustment append evidence mới.'),
            'annual-tax' => $simple('Reconcile thuế năm từ Payroll evidence.', 'Finalized items, tax profiles/policies, annual reconciliation.', 'Create/calculate/validate/review/finalize/statement theo route.', 'AnnualTaxController → PayrollAnnualTax services → statement/delivery', 'Local không gửi Government/bank và không tự nhận statutory compliance.'),
            'payslips' => $simple('Sinh/xem phiếu lương từ eligible Payroll Item.', 'Finalized/eligible Run và Item.', 'Xem, request/generate missing, download theo route.', 'PayslipController → PayslipGenerationService → private artifact', 'Idempotency ngăn duplicate; payslip không sửa Payroll Item.'),
            'payments' => $simple('Tạo payment intent/batch từ finalized Payroll.', 'Finalized Run/Items và verified payment accounts.', 'Create/validate/review/approve/release batch.', 'Payment controllers → PayrollPaymentBatchService → artifact', 'Local không gửi bank; maker-checker/checksum giữ nguyên.'),
            'accounting' => $simple('Chuyển Payroll financial lines thành balanced journal.', 'Finalized items/components, accounting policy/mapping/accounts.', 'Config và create/validate/review/approve/post journal.', 'Accounting controllers → PayrollAccountingJournalService → journal/ERP', 'Posted journal không sửa; không đổi mapping để rewrite history.'),
            'accounting-configuration' => $simple('Quản lý policy, chart accounts và component mappings cho journal.', 'Accounting policies, accounts và mappings.', 'Tạo/sửa/xem cấu hình theo permission.', 'AccountingPolicy/Account/MappingController → services → repositories → views', 'Cấu hình mới không rewrite journal đã post.'),
            'erp' => $simple('Xuất journal qua ERP adapter có idempotency/kill switch.', 'Journal, ERP connection và mappings.', 'Config, create/validate/execute/retry export.', 'ERP controllers → ErpExportService → adapter/Integration evidence', 'Local external delivery disabled; không nhập credential vào log/UI.'),
            'erp-configuration' => $simple('Quản lý ERP connection và mappings.', 'Connection metadata, encrypted credential reference và mappings.', 'Tạo/sửa/xem connection/mapping theo permission.', 'ErpExportController connection/mapping actions → services → repository', 'Không hiển thị credential hoặc bypass SSRF policy.'),
            'tax-administration' => $simple('Quản lý Employee tax profile phục vụ Payroll.', 'Employee, tax profile và effective-dated evidence.', 'Xem Employee tax state và cập nhật profile khi được phép.', 'TaxAdministrationController::employees → tax repository/service → views', 'Không expose tax identifier cho actor thiếu quyền.'),
            'tax-policies' => $simple('Quản lý policy thuế effective-dated.', 'Tax policies/brackets và version history.', 'Tạo/xem/sửa policy theo permission.', 'TaxAdministrationController::policies → policy service → repository', 'Không sửa policy cũ để rewrite finalized items.'),
            'insurance-administration' => $simple('Quản lý Employee insurance profile phục vụ Payroll.', 'Employee, insurance profile và enrollment evidence.', 'Xem Employee profile và cập nhật theo permission.', 'InsuranceAdministrationController::employees → insurance service/repository', 'Không expose restricted identifier cho actor thiếu quyền.'),
            'insurance-policies' => $simple('Quản lý policy bảo hiểm effective-dated.', 'Insurance policies, rates và version history.', 'Tạo/xem/sửa policy theo permission.', 'InsuranceAdministrationController::policies → policy service → repository', 'Không sửa policy cũ để rewrite finalized items.'),
            'government' => $this->make($key, $label, 'Tạo và quản lý Government filing artifact từ Payroll/tax evidence.', ['Payroll/tax reconciliation, Employee tax data, filing documents.'], ['Create, store, show, generate, validate, approve, submit, retry, reject, download.'], ['Create draft → generate → validate → approve → submit adapter khi cấu hình.'], ['Checksum/version/idempotency bảo vệ filing; local không gửi Government thật.'], [], ['Approved/submitted artifact giữ evidence.'], ['Approve/submit theo permission/state machine.'], ['Documents và Integration delivery evidence.'], [], [$permission], ['Không dùng production tax/Employee data trong demo.'], ['GovernmentFilingController → GovernmentTaxFilingService → document/integration boundary']),
            'workflow' => $simple('Quản lý Definition, Instance, Task, decision history và outbox.', 'Workflow definitions/versions/steps/instances/tasks/history/outbox.', 'Publish/start/approve/reject theo route/quyền.', 'Workflow controllers → WorkflowExecutionService → history/outbox/consumer', 'Duplicate decision idempotent; maker-checker; completed history append-only.'),
            'workflow-definitions' => $simple('Thiết kế và publish Workflow Definition có version.', 'Definition, version và approval steps.', 'Tạo draft, publish và xem definitions.', 'WorkflowController::definitions/create → WorkflowDefinitionService → repository', 'Published version không sửa; tạo version mới.'),
            'workflow-tasks' => $simple('Xử lý task inbox theo maker-checker.', 'Workflow Instance, Task, assignee và history.', 'Claim, approve hoặc reject theo state/permission.', 'WorkflowController::tasks → WorkflowExecutionService → history/outbox', 'Duplicate decision không tạo duplicate domain mutation.'),
            'notifications' => $simple('Theo dõi Notification, recipient, channel delivery và retry.', 'messages/recipients/deliveries/channel config.', 'Inbox/read/unread, delivery state, retry theo route.', 'NotificationController → services/outbox → channel adapters', 'Kill switch không mark success giả; local không gửi email thật.'),
            'notification-templates' => $simple('Quản lý template và version nội dung Notification.', 'Template drafts và published versions.', 'Tạo draft và publish theo permission.', 'NotificationCenterController::templates → template service → version repository', 'Published version immutable; message pin đúng version.'),
            'notification-deliveries' => $simple('Theo dõi delivery từng channel và retry lỗi.', 'Notification message, recipient và channel attempts.', 'Xem trạng thái và retry failed delivery.', 'NotificationCenterController::deliveries → delivery service → channel adapter', 'Retry idempotent; channel kill switch không mark success giả.'),
            'integrations' => $simple('Quản lý outbound, adapter, retry, dead-letter và checkpoint.', 'connections/messages/attempts/dead letters/checkpoints.', 'Xem/execute/retry/dead-letter theo permission.', 'Integration controllers → delivery service → SSRF/adapter → checkpoint', 'SSRF/idempotency/kill switch không bypass; local external disabled.'),
            'audit' => $this->make($key, $label, 'Tra cứu append-only Audit events, correlation, redaction, archive/export.', ['events/snapshots/correlations/checkpoints/archive artifacts.'], ['Search/filter, xem event/correlation, export/archive theo quyền.'], ['Chọn filter → xem event → mở correlation/resource history.'], ['Event có checksum/redaction và không có edit/delete.'], [], ['Historical Audit event append-only.'], ['Export/archive theo permission.'], ['Evidence phục vụ investigation các module.'], [], [$permission], ['Không đưa secret/PII chưa redaction vào export.'], ['AuditController → read repository → checksum/redaction → protected view/export']),
            'iam', 'privileged', 'access-reviews' => $simple($label.' quản lý request/review/role/permission/company scope.', 'IAM evidence, Spatie roles/permissions và company access.', 'Request/review/approve/deny/provision theo menu.', 'IAM controllers → services/state machine → Spatie mutation adapter → Audit', 'Không sửa Spatie pivot trực tiếp; maker-checker/tenant isolation bắt buộc.'),
            'access-requests' => $simple('Tạo và xử lý yêu cầu cấp/thu hồi access.', 'IAM request/items, subject User và company scope.', 'Create, review, approve/reject và provision theo state.', 'IamController::requests → IAM services/state machine → SpatieAccessProvisioningAdapter', 'Maker khác checker; không mutate Spatie pivot trực tiếp.'),
            'roles-permissions' => $simple('Tra cứu Role và Permission đang được IAM quản trị.', 'Spatie roles/permissions qua governed IAM read model.', 'Xem role/permission và mở access request.', 'IamController::roles → IamReadRepository → view', 'Thay đổi assignment phải qua IAM request/provisioning boundary.'),
            'segregation-of-duties' => $simple('Đối chiếu xung đột Segregation of Duties.', 'IAM assignments và SoD policy findings.', 'Xem finding và xử lý qua access workflow.', 'IamController::sod → IamReadRepository → governed remediation', 'Không nới permission trực tiếp để làm finding biến mất.'),
            'reliability' => $ops('Xem SLO, dependency, queue/scheduler và reliability snapshot.', 'Health, queue, scheduler, DB/Redis/Reverb, SLO read models.', 'ReliabilityController → ReliabilitySnapshotService'),
            'observability' => $ops('Xem HTTP/queue/outbox metrics, alerts và capacity.', 'ObservabilitySnapshotService, Horizon và read models.', 'ObservabilityController → snapshot/alert evaluator'),
            'performance-engineering' => $ops('Xem query/cache/index/export/queue findings.', 'Performance audit snapshot và bounded local evidence.', 'PerformanceController → PerformanceAuditService'),
            'architecture-governance' => $ops('Xem module graph, ownership, CQRS/layer findings.', 'Manifests, routes và static scanner.', 'ArchitectureController → architecture audit service'),
            'test-quality' => $ops('Xem test taxonomy, critical invariants và isolation.', 'Test registry/files và TestQualitySnapshotService.', 'TestQualityController → TestQualitySnapshotService'),
            'api-contracts' => $ops('Xem route/OpenAPI coverage và breaking findings.', 'Route inventory và sanitized OpenAPI.', 'ApiContractsController → ApiContractAuditService'),
            'releases' => $ops('Xem release manifest/certificate, gates và rollback target.', 'Immutable release artifacts và health evidence.', 'ReleaseDashboardController → release readers'),
            'configuration' => $ops('Xem runtime flags, precedence và drift.', 'Typed defaults, environment, runtime overrides.', 'RuntimeConfigurationController → resolver/drift service'),
            'secrets' => $ops('Xem secret-provider readiness, không xem plaintext.', 'SecretProvider metadata và readiness.', 'SecretsController → SecretGovernanceService'),
            'data-protection' => $ops('Xem classification, encryption/redaction và access findings.', 'Classification registry và protected evidence.', 'DataProtectionController → snapshot service'),
            'data-lifecycle' => $this->make($key, $label, 'Xem retention, legal hold, archive eligibility và disposal dry-run.', ['Lifecycle policies, ownership, legal-hold read model.'], ['Xem policy/finding/dry-run; không có purge.'], ['Chọn scope → evaluate → xử lý tại data owner.'], ['Legal hold override disposal; destructive disposal mặc định disabled.'], [], ['Policy/archive evidence không sửa trực tiếp.'], ['Future disposal cần explicit approval.'], ['Không auto-delete; finding về module owner.'], [], [$permission], ['Không bật destructive switch tùy tiện.'], ['DataLifecycleController → DataLifecycleAuditService → decisions']),
            'data-quality' => $this->make($key, $label, 'Đối soát read-only rules, pass/fail, score và checksum.', ['Aggregate Employee, Organization, Attendance, Payroll, Compensation.'], ['Chạy/xem audit; không auto-fix.'], ['Đọc finding → sửa qua module owner → audit lại.'], ['Rule không mutate và không làm yếu để đạt PASS.'], $this->statuses([['passed', 'Count = 0', 'Theo dõi'], ['failed', 'Có critical count', 'Sửa tại module owner']]), ['Checksum evidence không sửa tay.'], [], ['Finding về Employee/Organization/Attendance/Payroll.'], [], [$permission], ['Không sửa DB trực tiếp.'], ['DataQualityController → DataQualityAuditService → findings/checksum']),
            'continuity' => $ops('Xem backup/restore, DR readiness và continuity findings.', 'Backup strategy, restore verification, DR evidence.', 'ContinuityController → ContinuitySnapshotService'),
            'security-assurance' => $ops('Xem security controls, threats và release evidence.', 'Security scanners, route/payload policies.', 'SecurityAssuranceController → assurance service'),
            'go-live-readiness' => $this->make($key, $label, 'Đánh giá khả năng go-live và tách code readiness khỏi blocker hạ tầng/credential.', ['Application config, EnvironmentSafetyGuard, migration inventory, release/DR evidence và external dependency metadata.'], ['Xem checklist, dependency matrix, blocker và mở runbook sở hữu; không có action deploy.'], ['Code → Tests → Build → Release Check → Migration Review → Deploy → Health → Smoke → Monitor → rollback khi cần.'], ['Trang hoàn toàn read-only; không migrate, seed, restart, gọi provider hay truy cập VPS.'], [], ['Release/certificate đã phát hành không sửa trực tiếp.'], ['Production approval và credential verification diễn ra ngoài trang này.'], ['Finding được xử lý trong deployment runbook, Secrets, Reliability hoặc Business Continuity.'], [], [$permission], ['Không coi CONFIGURED là VERIFIED; PRODUCTION_READY cần evidence tại target thật.'], ['GET admin.operations.go-live → GoLiveReadinessController → ProductionReadinessService → configuration/release evidence → Blade']),
            'api-documentation' => $this->make($key, $label, 'Mở Swagger UI được bảo vệ để đọc API contract.', ['OpenAPI JSON /docs và governed API routes.'], ['Duyệt tags, operations và schemas.'], ['/api/documentation → authorize → Swagger tải /docs.'], ['UI và JSON đều cần api.documentation.view.'], [], ['Generated spec regenerate bằng command, không sửa output.'], [], ['API consumers và API Contracts dashboard.'], [], [$permission], ['Không nhập token thật hoặc public docs.'], ['Protected L5-Swagger route → /docs JSON → Swagger UI']),
            'articles', 'article-create' => $simple($label.' trong kho nội dung.', 'articles và authenticated author.', $key === 'article-create' ? 'Nhập/lưu bài mới.' : 'Xem/edit theo permission.', 'ArticleController → Article model → Blade', 'Không chèn secret/PII hoặc dùng quyền Article cho business module.'),
            default => $simple($label.' theo module sở hữu.', 'Module-owned company-scoped data.', 'Chỉ action đang hiển thị.', $label.' route → Controller → Service/Repository → View', 'Không đoán route/action ẩn.'),
        };
    }

    /**
     * @param  list<array{0:string,1:string,2:string}>  $rows
     * @return list<HelpStatus>
     */
    private function statuses(array $rows): array
    {
        return array_map(static fn (array $row): array => ['status' => $row[0], 'meaning' => $row[1], 'next' => $row[2]], $rows);
    }

    /**
     * @param  list<string>  $dataSources
     * @param  list<string>  $mainActions
     * @param  list<string>  $workflowSteps
     * @param  list<string>  $businessRules
     * @param  list<HelpStatus>  $statusGuide
     * @param  list<string>  $immutabilityRules
     * @param  list<string>  $approvalRules
     * @param  list<string>  $downstreamEffects
     * @param  list<RelatedMenu>  $relatedMenus
     * @param  list<string>  $permissions
     * @param  list<string>  $warnings
     * @param  list<string>  $technicalFlow
     * @return BaseHelpDefinition
     */
    private function make(string $key, string $title, string $purpose, array $dataSources, array $mainActions, array $workflowSteps, array $businessRules, array $statusGuide, array $immutabilityRules, array $approvalRules, array $downstreamEffects, array $relatedMenus, array $permissions, array $warnings, array $technicalFlow, bool $fallback = false): array
    {
        return compact('key', 'title', 'purpose', 'dataSources', 'mainActions', 'workflowSteps', 'businessRules', 'statusGuide', 'immutabilityRules', 'approvalRules', 'downstreamEffects', 'relatedMenus', 'permissions', 'warnings', 'technicalFlow', 'fallback') + ['routePatterns' => []];
    }
}

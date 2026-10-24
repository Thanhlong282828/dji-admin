<?php

namespace Modules\Performance\Domain\Services;

final readonly class EnterpriseReportCatalog
{
    /** @return list<array{key:string,title:string,category:string,description:string,formats:list<string>,classification:string}> */
    public function all(bool $salaryAllowed): array
    {
        $reports = [
            ['key' => 'executive-board-pack', 'title' => 'Executive Board Pack', 'category' => 'Executive', 'description' => 'Workforce, planning, risk và quality management review.', 'formats' => ['html', 'csv', 'xlsx', 'pdf'], 'classification' => 'Confidential'],
            ['key' => 'workforce', 'title' => 'Workforce Report', 'category' => 'Workforce', 'description' => 'Headcount, department, position, level và employment composition.', 'formats' => ['html', 'csv', 'xlsx'], 'classification' => 'Confidential'],
            ['key' => 'attendance-leave', 'title' => 'Attendance & Leave', 'category' => 'Attendance', 'description' => 'Attendance rate, late, overtime, absence và leave status.', 'formats' => ['html', 'csv', 'xlsx'], 'classification' => 'Internal'],
            ['key' => 'recruitment', 'title' => 'Recruitment Pipeline', 'category' => 'Recruitment', 'description' => 'Requisition, candidate, application, interview, offer và coverage.', 'formats' => ['html', 'csv', 'xlsx'], 'classification' => 'Confidential'],
            ['key' => 'planning', 'title' => 'Planning & Variance', 'category' => 'Planning', 'description' => 'Target, Actual, Forecast, Reforecast và execution variance.', 'formats' => ['html', 'csv', 'xlsx', 'pdf'], 'classification' => 'Confidential'],
            ['key' => 'risk-governance', 'title' => 'Risk & Governance', 'category' => 'Governance', 'description' => 'IAM, Workflow, Audit và Data Quality aggregate evidence.', 'formats' => ['html', 'csv', 'xlsx'], 'classification' => 'Internal'],
        ];
        if ($salaryAllowed) {
            $reports[] = ['key' => 'payroll', 'title' => 'Payroll & Compensation', 'category' => 'Payroll', 'description' => 'Authorized aggregate Payroll and compensation metrics.', 'formats' => ['html', 'csv', 'xlsx', 'pdf'], 'classification' => 'Restricted'];
        }

        return $reports;
    }
}

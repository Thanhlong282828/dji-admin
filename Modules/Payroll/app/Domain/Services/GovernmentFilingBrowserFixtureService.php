<?php

namespace Modules\Payroll\Domain\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;

final readonly class GovernmentFilingBrowserFixtureService
{
    public function __construct(private ConnectionInterface $db, private FilesystemManager $files, private PayrollAnnualTaxDeliveryFixtureService $base, private GovernmentTaxFilingService $filings) {}

    /** @return array{company_id:int,filing_id:int,email:string,password:string} */
    public function create(string $environment): array
    {
        $this->guard($environment);
        $base = $this->base->create($environment);
        $maker = User::query()->where('email', $base['email'])->firstOrFail();
        $checker = User::query()->where('email', 'browser.adjustment.checker.positive@example.test')->firstOrFail();
        $permissions = ['government.filing.view', 'government.filing.create', 'government.filing.generate', 'government.filing.validate', 'government.filing.approve', 'government.filing.submit', 'government.filing.download'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $maker->givePermissionTo($permissions);
        $checker->givePermissionTo($permissions);
        $filing = $this->filings->create($base['company_id'], $base['payable_id'], 'annual_pit_employer_declaration', $maker->id, (string) Str::uuid());
        $this->filings->generate($base['company_id'], $filing->filingId, $maker->id);
        $this->filings->validate($base['company_id'], $filing->filingId, $maker->id);
        $this->filings->approve($base['company_id'], $filing->filingId, $checker->id);
        $this->filings->submit($base['company_id'], $filing->filingId, $maker->id, (string) Str::uuid());

        return ['company_id' => $base['company_id'], 'filing_id' => $filing->filingId, 'email' => $base['email'], 'password' => $base['password']];
    }

    public function cleanup(string $environment): void
    {
        $this->guard($environment);
        $company = $this->db->table('companies')->where('code', 'BROWSER-ADJUSTMENT-POSITIVE')->value('id');
        if (is_numeric($company)) {
            $companyId = (int) $company;
            $this->files->disk('local')->deleteDirectory('government-filings/'.$companyId);
            foreach (['government_tax_access_logs', 'government_tax_submission_attempts', 'government_tax_validation_issues', 'government_tax_filing_logs', 'government_tax_filing_documents', 'government_tax_filing_items', 'government_tax_filings'] as $table) {
                $this->db->table($table)->where('company_id', $companyId)->delete();
            }
        }
        $this->base->cleanup($environment);
    }

    private function guard(string $environment): void
    {
        if (! in_array($environment, ['local', 'testing'], true) || ! str_ends_with((string) $this->db->getDatabaseName(), '_testing')) {
            throw new RuntimeException('Government Filing browser fixture requires local/testing and a *_testing database.');
        }
    }
}

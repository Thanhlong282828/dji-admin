<?php

namespace Modules\Payroll\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Payroll\Console\Commands\ErpIntegrationBrowserFixtureCommand;
use Modules\Payroll\Console\Commands\GovernmentFilingBrowserFixtureCommand;
use Modules\Payroll\Console\Commands\PayrollAdjustmentBrowserFixtureCommand;
use Modules\Payroll\Console\Commands\PayrollAnnualTaxBrowserFixtureCommand;
use Modules\Payroll\Console\Commands\PayrollAnnualTaxDeliveryFixtureCommand;
use Modules\Payroll\Domain\Adapters\ErpAccountingAdapter;
use Modules\Payroll\Domain\Adapters\GovernmentDeclarationGeneratorInterface;
use Modules\Payroll\Domain\Repositories\AccountingReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\AnnualTaxReconciliationRepositoryInterface;
use Modules\Payroll\Domain\Repositories\ErpExportReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\GovernmentFilingReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\InsuranceAdministrationReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\PaymentReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\PayrollAdjustmentReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\PayrollDeliveryReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\PayrollExportReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\PayrollInputOperationsReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\PayrollPeriodReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\PayslipReadRepositoryInterface;
use Modules\Payroll\Domain\Repositories\TaxAdministrationReadRepositoryInterface;
use Modules\Payroll\Infrastructure\Adapters\GenericCsvErpAdapter;
use Modules\Payroll\Infrastructure\Adapters\GenericXmlDeclarationGenerator;
use Modules\Payroll\Infrastructure\Persistence\EloquentAccountingReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentAnnualTaxReconciliationRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentErpExportReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentGovernmentFilingReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentInsuranceAdministrationReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentPaymentReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentPayrollAdjustmentReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentPayrollDeliveryReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentPayrollExportReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentPayrollInputOperationsReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentPayrollPeriodReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentPayslipReadRepository;
use Modules\Payroll\Infrastructure\Persistence\EloquentTaxAdministrationReadRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PayrollServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Payroll';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'payroll';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [ErpIntegrationBrowserFixtureCommand::class, GovernmentFilingBrowserFixtureCommand::class, PayrollAdjustmentBrowserFixtureCommand::class, PayrollAnnualTaxBrowserFixtureCommand::class, PayrollAnnualTaxDeliveryFixtureCommand::class];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();
        $this->app->bind(AccountingReadRepositoryInterface::class, EloquentAccountingReadRepository::class);
        $this->app->bind(AnnualTaxReconciliationRepositoryInterface::class, EloquentAnnualTaxReconciliationRepository::class);
        $this->app->bind(InsuranceAdministrationReadRepositoryInterface::class, EloquentInsuranceAdministrationReadRepository::class);
        $this->app->bind(PayrollDeliveryReadRepositoryInterface::class, EloquentPayrollDeliveryReadRepository::class);
        $this->app->bind(PayrollExportReadRepositoryInterface::class, EloquentPayrollExportReadRepository::class);
        $this->app->bind(PayrollInputOperationsReadRepositoryInterface::class, EloquentPayrollInputOperationsReadRepository::class);
        $this->app->bind(PayrollPeriodReadRepositoryInterface::class, EloquentPayrollPeriodReadRepository::class);
        $this->app->bind(PayrollAdjustmentReadRepositoryInterface::class, EloquentPayrollAdjustmentReadRepository::class);
        $this->app->bind(PayslipReadRepositoryInterface::class, EloquentPayslipReadRepository::class);
        $this->app->bind(PaymentReadRepositoryInterface::class, EloquentPaymentReadRepository::class);
        $this->app->bind(TaxAdministrationReadRepositoryInterface::class, EloquentTaxAdministrationReadRepository::class);
        $this->app->bind(ErpAccountingAdapter::class, GenericCsvErpAdapter::class);
        $this->app->bind(ErpExportReadRepositoryInterface::class, EloquentErpExportReadRepository::class);
        $this->app->bind(GovernmentDeclarationGeneratorInterface::class, GenericXmlDeclarationGenerator::class);
        $this->app->bind(GovernmentFilingReadRepositoryInterface::class, EloquentGovernmentFilingReadRepository::class);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}

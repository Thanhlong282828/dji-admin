<?php

namespace Tests\Unit\Company;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Company\Application\Commands\UpdateCompany\UpdateCompanyCommand;
use Modules\Company\Application\Commands\UpdateCompany\UpdateCompanyHandler;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;
use Tests\TestCase;

final class UpdateCompanyHandlerTest extends TestCase
{
    public function test_handler_updates_and_dispatches_domain_event(): void
    {
        $company = new Company(['code' => 'SCHEMA-UPDATED']);
        $writer = $this->createMock(CompanyWriteRepositoryInterface::class);
        $writer->expects(self::once())->method('update')->with(12, self::callback(fn (array $data): bool => $data['name'] === 'Updated' && $data['updated_by'] === 8))->willReturn($company);
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch');
        $result = (new UpdateCompanyHandler($writer, $events))->handle(new UpdateCompanyCommand(12, ['name' => 'Updated'], 8));
        $this->assertSame($company, $result);
    }
}

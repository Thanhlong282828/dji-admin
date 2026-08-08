<?php

namespace Tests\Unit\Company;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Company\Application\Commands\CreateCompany\CreateCompanyCommand;
use Modules\Company\Application\Commands\CreateCompany\CreateCompanyHandler;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;
use Tests\TestCase;

final class CreateCompanyHandlerTest extends TestCase
{
    public function test_handler_writes_and_dispatches_domain_event(): void
    {
        $company = new Company(['code' => 'SCHEMA-UNIT', 'name' => 'Schema Unit']);
        $writer = $this->createMock(CompanyWriteRepositoryInterface::class);
        $writer->expects(self::once())->method('create')->with(self::callback(fn (array $data): bool => $data['created_by'] === 7))->willReturn($company);
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch');
        $result = (new CreateCompanyHandler($writer, $events))->handle(new CreateCompanyCommand(['code' => 'SCHEMA-UNIT', 'name' => 'Schema Unit'], 7));
        $this->assertSame($company, $result);
    }
}

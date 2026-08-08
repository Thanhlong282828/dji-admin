<?php

namespace Tests\Unit\Company;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Company\Application\Queries\GetCompanyList\GetCompanyListHandler;
use Modules\Company\Application\Queries\GetCompanyList\GetCompanyListQuery;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;
use Tests\TestCase;

final class GetCompanyListHandlerTest extends TestCase
{
    public function test_handler_forwards_safe_filters_and_maps_view_models(): void
    {
        $company = new Company(['code' => 'SCHEMA-LIST', 'name' => 'Schema List', 'country_code' => 'VN', 'currency_code' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active']);
        $company->setAttribute('uuid', '1ebea722-d8fc-4b85-a764-68eabb3ed020');
        $company->id = 1;
        $page = new LengthAwarePaginator([$company], 1, 15, 1);
        $reader = $this->createMock(CompanyReadRepositoryInterface::class);
        $reader->expects(self::once())->method('paginate')->with('Schema', 'active', 15, 1, 'name', 'asc', false)->willReturn($page);
        $result = (new GetCompanyListHandler($reader))->handle(new GetCompanyListQuery('Schema', 'active', 15, 1, 'name', 'asc'));
        $this->assertSame('SCHEMA-LIST', $result->companies->items()[0]->code);
    }
}

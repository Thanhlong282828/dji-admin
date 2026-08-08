<?php

namespace Modules\Company\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;

final class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $actorId = User::query()->value('id');
        foreach ([
            ['code' => 'SCHEMA-TECH-VN', 'name' => 'Schema Technology Vietnam', 'short_name' => 'Schema Tech', 'tax_code' => 'DEMO-0319000001', 'email' => 'hello@schema.demo', 'phone' => '+84 28 7300 1901', 'website' => 'https://example.com/schema-technology', 'address' => 'Khu Công nghệ cao, Thành phố Hồ Chí Minh', 'legal_representative' => 'Nguyễn Minh Khôi', 'status' => 'active', 'description' => 'Dữ liệu demo: trung tâm nghiên cứu và nền tảng công nghệ doanh nghiệp.'],
            ['code' => 'SCHEMA-DIGITAL', 'name' => 'Schema Digital Solutions', 'short_name' => 'Schema Digital', 'tax_code' => 'DEMO-0319000002', 'email' => 'digital@schema.demo', 'phone' => '+84 28 7300 1902', 'website' => 'https://example.com/schema-digital', 'address' => 'Quận 1, Thành phố Hồ Chí Minh', 'legal_representative' => 'Trần An Nhiên', 'status' => 'active', 'description' => 'Dữ liệu demo: đơn vị tư vấn chuyển đổi số và trải nghiệm khách hàng.'],
            ['code' => 'SCHEMA-ROBOTICS', 'name' => 'Schema Premium Robotics', 'short_name' => 'Schema Robotics', 'tax_code' => 'DEMO-0319000003', 'email' => 'robotics@schema.demo', 'phone' => '+84 28 7300 1903', 'website' => 'https://example.com/schema-robotics', 'address' => 'Thành phố Thủ Đức, Thành phố Hồ Chí Minh', 'legal_representative' => 'Lê Gia Bảo', 'status' => 'inactive', 'description' => 'Dữ liệu demo: phòng thí nghiệm tự động hóa và robotics cao cấp.'],
        ] as $data) {
            Company::withTrashed()->updateOrCreate(['code' => $data['code']], [...$data, 'uuid' => (string) Str::uuid(), 'country_code' => 'VN', 'currency_code' => 'VND', 'currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'is_active' => $data['status'] === 'active', 'created_by' => $actorId, 'updated_by' => $actorId, 'deleted_at' => null]);
        }
    }
}

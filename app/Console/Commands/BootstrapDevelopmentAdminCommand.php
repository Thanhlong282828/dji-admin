<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Company\Domain\Models\Company;
use RuntimeException;

final class BootstrapDevelopmentAdminCommand extends Command
{
    protected $signature = 'app:bootstrap-development-admin
        {--email=admin@local.test : Development administrator email}
        {--password= : Development-only password; required when creating the user}';

    protected $description = 'Idempotently bootstrap a guarded local development company and administrator';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = is_string($connection)
            ? config("database.connections.{$connection}.database")
            : null;

        if (! is_string($database)) {
            $this->components->error('The configured database name is invalid.');

            return self::FAILURE;
        }

        if (! app()->environment('local') || $database !== 'schema_admin') {
            $this->components->error('Refusing to bootstrap outside APP_ENV=local and schema_admin.');

            return self::FAILURE;
        }

        $email = $this->stringOption('email');
        $password = $this->stringOption('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('The development administrator email is invalid.');

            return self::INVALID;
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser === null && $password === '') {
            $this->components->error('The --password option is required when creating the development administrator.');

            return self::INVALID;
        }

        $this->call('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--force' => true,
        ]);

        DB::transaction(function () use ($email, $password, $existingUser): void {
            $user = $existingUser ?? new User;
            $user->name = 'Schema Local Administrator';
            $user->email = $email;

            if (! $user->exists || ($password !== '' && ! Hash::check($password, $user->password))) {
                $user->password = Hash::make($password);
            }

            $user->save();
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->syncRoles(['Super Admin']);

            $company = Company::withTrashed()->firstOrNew(['code' => 'SCHEMA-LOCAL']);
            $company->fill([
                'name' => 'Công ty Cổ phần Công nghệ Minh Long',
                'short_name' => 'Minh Long Tech',
                'tax_code' => null,
                'email' => 'company@local.test',
                'phone' => null,
                'website' => 'http://127.0.0.1:8001',
                'address' => '123 Nguyễn Văn Linh, Phường Tân Phong, Thành phố Hồ Chí Minh',
                'country_code' => 'VN',
                'currency' => 'VND',
                'currency_code' => 'VND',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'legal_representative' => 'Nguyễn Minh Long (Synthetic Demo)',
                'status' => 'active',
                'is_active' => true,
                'description' => 'SYNTHETIC LOCAL DEMO DATA — không phải dữ liệu doanh nghiệp thật.',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $company->deleted_at = null;
            $company->save();

            DB::table('company_user_access')->updateOrInsert(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['created_at' => now(), 'updated_at' => now()],
            );
        });

        $this->components->info('Development administrator bootstrap completed.');
        $this->line("Database: {$database}");
        $this->line("Email: {$email}");
        $this->line('Role: Super Admin');
        $this->line('Company: Công ty Cổ phần Công nghệ Minh Long (SCHEMA-LOCAL)');

        return self::SUCCESS;
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            throw new RuntimeException("The {$name} option must be a string.");
        }

        return trim($value);
    }
}

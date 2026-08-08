<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(['email' => 'admin@schema.vn'], ['name' => 'Schema Super Admin', 'email_verified_at' => now(), 'password' => Hash::make('ChangeMe123!')]);
        $admin->syncRoles(['Super Admin']);
    }
}

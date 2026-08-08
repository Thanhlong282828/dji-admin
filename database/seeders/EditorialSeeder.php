<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class EditorialSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(['email' => 'admin@schema.vn'], ['name' => 'SCHEMA Admin', 'password' => Hash::make('ChangeMe123!')]);
        Article::query()->updateOrCreate(['slug' => 'tu-duy-du-lieu-trong-quan-tri-nhan-su'], ['author_id' => $user->id, 'title' => 'Tư duy dữ liệu trong quản trị nhân sự hiện đại', 'excerpt' => 'Cách doanh nghiệp hợp nhất dữ liệu con người, vận hành và tài chính để ra quyết định chính xác hơn.', 'body' => str_repeat('SCHEMA giúp lãnh đạo chuyển dữ liệu nhân sự thành quyết định có thể hành động. ', 6), 'category' => 'Công nghệ', 'hero_image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=2000&q=90', 'status' => 'published', 'featured' => true, 'published_at' => now()]);
    }
}

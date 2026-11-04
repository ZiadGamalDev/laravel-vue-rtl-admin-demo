<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Demo accounts, one per role (all use the password "password").
        $users = [
            ['demo@example.com', 'مدير تجريبي', Role::Admin],
            ['staff@example.com', 'موظف تجريبي', Role::Staff],
            ['viewer@example.com', 'مشاهد تجريبي', Role::Viewer],
            ['accounts@example.com', 'محاسب تجريبي', Role::Staff],
            ['intern@example.com', 'متدرب تجريبي', Role::Viewer],
        ];
        foreach ($users as [$email, $name, $role]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'role' => $role],
            );
        }

        $this->call([DemoDataSeeder::class, DemoErrorSeeder::class]);
    }
}

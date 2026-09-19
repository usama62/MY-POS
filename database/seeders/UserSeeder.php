<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@pos.local'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => 'admin',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'cashier@pos.local'],
            [
                'name' => 'Cashier',
                'password' => 'password',
                'role' => 'cashier',
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NtsAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User::create([
        //     'name' => 'Nawab Tech Solutions Admin',
        //     'email' => 'ntsadmin@rypl.test',
        //     'password' => Hash::make('ntsadmin@rypl'),
        //     'role' => User::NTS_ADMINISTRATOR_ROLE,
        // ]);
    }
}

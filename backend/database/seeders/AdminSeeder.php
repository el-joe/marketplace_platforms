<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'id' => Str::uuid(),
                'name' => 'Super Admin',
                'email' => 'admin@admin.com',
                'phone' => '+966500000000',
                'password' => Hash::make('123456'),
                'status' => 'active',
            ]
        );

        // Additional staff admins for testing
        $admins = [
            ['name' => 'Sara Al-Rashidi', 'email' => 'sara@admin.com', 'phone' => '+966501111111'],
            ['name' => 'Mohamed Hassan', 'email' => 'mohamed@admin.com', 'phone' => '+201001234567'],
            ['name' => 'Layla Al-Amri', 'email' => 'layla@admin.com', 'phone' => '+971501234567'],
        ];

        foreach ($admins as $data) {
            Admin::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'id' => Str::uuid(),
                    'password' => Hash::make('123456'),
                    'status' => 'active',
                ])
            );
        }
    }
}

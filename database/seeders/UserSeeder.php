<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test teacher
        User::firstOrCreate(
            ['email' => 'guru@example.com'],
            [
                'name' => 'Ibu Siti (Guru)',
                'password' => Hash::make('Teacher@123'),
                'role' => 'teacher',
            ]
        );

        // Create test student
        User::firstOrCreate(
            ['email' => 'murid@example.com'],
            [
                'name' => 'Andi Wijaya (Murid)',
                'password' => Hash::make('Student@123'),
                'role' => 'student',
            ]
        );

        // Additional students
        User::firstOrCreate(
            ['email' => 'budi@example.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('Student@123'),
                'role' => 'student',
            ]
        );

        User::firstOrCreate(
            ['email' => 'siti@example.com'],
            [
                'name' => 'Siti Nurhaliza',
                'password' => Hash::make('Student@123'),
                'role' => 'student',
            ]
        );

        // Additional teachers
        User::firstOrCreate(
            ['email' => 'pak.ahmad@example.com'],
            [
                'name' => 'Pak Ahmad Maulana',
                'password' => Hash::make('Teacher@123'),
                'role' => 'teacher',
            ]
        );

        User::firstOrCreate(
            ['email' => 'ibu.rani@example.com'],
            [
                'name' => 'Ibu Rani Wijaya',
                'password' => Hash::make('Teacher@123'),
                'role' => 'teacher',
            ]
        );
    }
}

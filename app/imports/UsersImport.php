<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class UsersImport implements ToCollection, WithHeadingRow, SkipsOnFailure
{
    use SkipsFailures;

    private string $role;

    public function __construct(string $role)
    {
        $this->role = $role;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['username']) || empty($row['password'])) {
                continue;
            }

            User::updateOrCreate(
                ['email' => $row['username']],
                [
                    'name'     => $row['name'] ?? $row['username'],
                    'email'    => $row['username'],
                    'password' => Hash::make($row['password']),
                    'role'     => $this->role,
                ]
            );
        }
    }
}
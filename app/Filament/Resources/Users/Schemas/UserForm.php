<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->minLength(3)
                    ->maxLength(255)
                    ->autocomplete(false)
                    ->validationMessages([
                        'required' => 'Nama harus diisi',
                        'min' => 'Nama minimal 3 karakter',
                        'max' => 'Nama maksimal 255 karakter',
                    ]),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->regex('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/')
                    ->autocomplete(false)
                    ->validationMessages([
                        'required' => 'Email harus diisi',
                        'email' => 'Format email tidak valid',
                        'unique' => 'Email sudah terdaftar',
                        'regex' => 'Format email tidak valid',
                    ]),

                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required(fn ($context) => $context === 'create')
                    ->minLength(8)
                    ->regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/')
                    ->dehydrated(fn ($state) => filled($state)) 
                    ->dehydrateStateUsing(fn ($state) => $state ? bcrypt($state) : null)
                    ->autocomplete(false)
                    ->validationMessages([
                        'required' => 'Password harus diisi',
                        'min' => 'Password minimal 8 karakter',
                        'regex' => 'Password harus mengandung huruf besar, huruf kecil, dan angka',
                    ]),

                Select::make('role')
                    ->label('Role')
                    ->required()
                    ->options([
                        'teacher' => 'Teacher',
                        'student' => 'Student',
                        'admin' => 'Admin',
                    ])
                    ->validationMessages([
                        'required' => 'Role harus dipilih',
                        'in' => 'Role tidak valid',
                    ]),
            ]);
    }
}

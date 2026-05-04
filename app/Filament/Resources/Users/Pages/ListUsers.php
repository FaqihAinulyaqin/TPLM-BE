<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;

use App\Imports\UsersImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;



class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),


            Action::make('importUsers')
                ->label('Import dari Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Select::make('role')
                        ->label('Role Akun')
                        ->required()
                        ->options([
                            'teacher' => 'Guru',
                            'student' => 'Siswa',
                        ]),

                    FileUpload::make('file')
                        ->label('File Excel (.xlsx / .xls)')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('imports'),
                ])
                ->action(function (array $data) {
                    $path = storage_path('app/private/' . $data['file']);

                    $import = new UsersImport($data['role']);
                    Excel::import($import, $path);

                    $failures = $import->failures();

                    if ($failures->isNotEmpty()) {
                        Notification::make()
                            ->title('Import selesai dengan beberapa error')
                            ->warning()
                            ->body(count($failures) . ' baris gagal diimport.')
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Import berhasil!')
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}


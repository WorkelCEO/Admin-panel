<?php

namespace App\Filament\Resources\BackupResource\Pages;

use App\Exceptions\ApiException;
use App\Filament\Resources\BackupResource;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListBackups extends ListRecords
{
    protected static string $resource = BackupResource::class;

    protected AdminRepository $repository;

    public function boot(AdminRepository $repository): void
    {
        $this->repository = $repository;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_backup')
                ->label('Create Backup')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Optional description for this backup'),
                    Forms\Components\Checkbox::make('include_files')
                        ->label('Include Files')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $repository = app(AdminRepository::class);
                    $backup = $repository->createBackup(
                        $data['description'] ?? null,
                        $data['include_files'] ?? false
                    );
                    
                    if ($backup) {
                        Notification::make()
                            ->title('Backup created successfully')
                            ->success()
                            ->send();
                        
                        $this->refreshTable();
                    } else {
                        Notification::make()
                            ->title('Failed to create backup')
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->refreshTable();
                    Notification::make()
                        ->title('Data refreshed')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        try {
            $backups = $this->repository->getBackups();
            
            // Convert to paginator-like structure
            return new \Illuminate\Pagination\LengthAwarePaginator(
                $backups,
                count($backups),
                15,
                1,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load backups')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->send();

            return new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                15,
                1,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        }
    }
}

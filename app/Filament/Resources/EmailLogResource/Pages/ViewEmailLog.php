<?php

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Filament\Resources\EmailLogResource;
use App\Services\EmailLogsAppApiService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class ViewEmailLog extends ViewRecord
{
    protected static string $resource = EmailLogResource::class;

    protected EmailLogsAppApiService $apiService;

    public function boot(EmailLogsAppApiService $apiService): void
    {
        $this->apiService = $apiService;
    }

    /**
     * Override mount to fetch data from API
     */
    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (!$this->record) {
            Notification::make()
                ->title('Email log not found')
                ->danger()
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));
        }

        $this->authorizeAccess();

        $this->fillForm();
    }

    /**
     * Resolve record from API
     */
    public function resolveRecord(int | string $key): Model
    {
        $emailLog = $this->apiService->getEmailLog($key);
        
        if (!$emailLog) {
            Notification::make()
                ->title('Email log not found')
                ->danger()
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));
        }
        
        return $emailLog;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->apiService->deleteEmailLog($this->record->id)) {
                        Notification::make()
                            ->title('Email log deleted successfully')
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('index'));
                    } else {
                        Notification::make()
                            ->title('Failed to delete email log')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Additional widgets can be added here
        ];
    }

    /**
     * Get custom view data for the page
     */
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'emailLog' => $this->record,
            'metadata' => $this->record->metadata ?? [],
            'isSuccess' => $this->record->isSuccess(),
            'isError' => $this->record->isError(),
        ]);
    }
}


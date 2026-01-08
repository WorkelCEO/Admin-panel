<?php

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Exceptions\ApiException;
use App\Exceptions\ApiNotFoundException;
use App\Filament\Resources\EmailLogResource;
use App\Repositories\EmailLogRepository;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class ViewEmailLog extends ViewRecord
{
    protected static string $resource = EmailLogResource::class;

    protected EmailLogRepository $repository;

    public function boot(EmailLogRepository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * Override mount to fetch data from API
     */
    public function mount(int | string $record): void
    {
        try {
            $this->record = $this->resolveRecord($record);

            if (!$this->record) {
                Notification::make()
                    ->title('Email log not found')
                    ->body('The requested email log could not be found. It may have been deleted or the ID is invalid.')
                    ->danger()
                    ->send();

                $this->redirect(static::getResource()::getUrl('index'));
                return;
            }

            $this->authorizeAccess();
            $this->fillForm();
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load email log')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->actions([
                    Notification::make('retry')
                        ->label('Retry')
                        ->action(fn() => $this->mount($record)),
                    Notification::make('back')
                        ->label('Back to List')
                        ->action(fn() => $this->redirect(static::getResource()::getUrl('index'))),
                ])
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    /**
     * Resolve record from API
     */
    public function resolveRecord(int | string $key): Model
    {
        try {
            $emailLog = $this->repository->getEmailLog('app', $key);
            
            if (!$emailLog) {
                throw new ApiNotFoundException("Email log not found", "/admin/email-logs/{$key}");
            }
            
            return $emailLog;
        } catch (ApiNotFoundException $e) {
            throw $e;
        } catch (ApiException $e) {
            throw $e;
        }
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
                    try {
                        if ($this->repository->deleteEmailLog('app', $this->record->id)) {
                            Notification::make()
                                ->title('Email log deleted successfully')
                                ->success()
                                ->send();

                            $this->redirect(static::getResource()::getUrl('index'));
                        } else {
                            Notification::make()
                                ->title('Failed to delete email log')
                                ->body('Please try again or contact support if the problem persists.')
                                ->danger()
                                ->actions([
                                    Notification::make('retry')
                                        ->label('Retry')
                                        ->action(fn() => $this->repository->deleteEmailLog('app', $this->record->id)),
                                ])
                                ->send();
                        }
                    } catch (ApiException $e) {
                        Notification::make()
                            ->title('Failed to delete email log')
                            ->body('Unable to connect to the API. Please try again.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}


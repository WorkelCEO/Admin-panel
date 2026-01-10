<?php

namespace App\Filament\Resources\WorkelUserResource\Pages;

use App\Exceptions\ApiException;
use App\Filament\Resources\WorkelUserResource;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorkelUser extends EditRecord
{
    protected static string $resource = WorkelUserResource::class;

    protected AdminRepository $repository;

    public function boot(AdminRepository $repository): void
    {
        $this->repository = $repository;
    }

    protected function resolveRecord(int | string $key): Model
    {
        try {
            $user = $this->repository->getUser($key);
            
            if (!$user) {
                throw new ApiException("User not found", 404);
            }

            $data = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'system_role' => $user->getSystemRole() ?? 'user',
                'role' => $user->getSystemRole() ?? 'user',
            ];

            return new class($data) extends Model {
                protected $guarded = [];
            };
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load user')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));

            // Return a dummy model (though redirect should handle navigation)
            return new class([]) extends Model {
                protected $guarded = [];
            };
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove password if empty
        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Map form fields to API fields
        if (isset($data['role'])) {
            $data['system_role'] = $data['role'];
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $updatedUser = $this->repository->updateUser($record->id, $data);
            
            if ($updatedUser) {
                Notification::make()
                    ->title('User updated successfully')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to update user')
                    ->danger()
                    ->send();
            }

            return $record;
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to update user')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->action(function () {
                    $repository = app(AdminRepository::class);
                    
                    if ($repository->deleteUser($this->record->id)) {
                        Notification::make()
                            ->title('User deleted successfully')
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('index'));
                    } else {
                        Notification::make()
                            ->title('Failed to delete user')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

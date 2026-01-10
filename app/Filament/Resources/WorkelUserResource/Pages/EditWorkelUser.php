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
                Notification::make()
                    ->title('User not found')
                    ->body('The user you are trying to edit does not exist.')
                    ->danger()
                    ->send();
                
                $this->redirect(static::getResource()::getUrl('index'));
                
                // Return a dummy model (though redirect should handle navigation)
                return new class([]) extends Model {
                    protected $guarded = [];
                };
            }

            // Map all fields from UserResponse to form data
            $data = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'system_role' => $user->getSystemRole() ?? 'user',
                // Include additional fields if available in the response data
                'phone' => $user->data['phone'] ?? null,
                'address' => $user->data['address'] ?? null,
                'status' => $user->data['status'] ?? 'active',
                'api_type' => $user->data['api_type'] ?? null,
                'source' => $user->data['source'] ?? null,
            ];

            // Ensure ID exists
            if (empty($data['id'])) {
                throw new ApiException("User ID is missing", 400);
            }

            // Create a model instance for Filament compatibility
            $model = new class($data) extends Model {
                protected $guarded = [];
                public $incrementing = false;
                protected $keyType = 'string';
                protected $primaryKey = 'id';
                protected $table = 'workel_users'; // Dummy table name for Filament
                
                public function __construct(array $attributes = [])
                {
                    parent::__construct([]);
                    // Set exists to true so Filament knows this is a persisted record
                    $this->exists = true;
                    foreach ($attributes as $key => $value) {
                        $this->setAttribute($key, $value);
                    }
                    // Ensure ID is set as the primary key value
                    if (isset($attributes['id'])) {
                        $this->setAttribute($this->primaryKey, $attributes['id']);
                        // Also set in attributes array directly for proper access
                        $this->attributes[$this->primaryKey] = $attributes['id'];
                    }
                }
                
                public function getRouteKeyName(): string
                {
                    return 'id';
                }
                
                // Override getRouteKey to return the ID for routing
                public function getRouteKey()
                {
                    $key = $this->attributes[$this->primaryKey] ?? $this->getAttribute($this->getRouteKeyName()) ?? $this->getAttribute('id');
                    // Ensure we return a string (UUIDs are strings)
                    return $key ? (string) $key : null;
                }
                
                // Override getKey to ensure proper key retrieval
                public function getKey()
                {
                    $key = $this->attributes[$this->primaryKey] ?? $this->getAttribute($this->primaryKey);
                    return $key ? (string) $key : null;
                }
                
                // Override getKeyName to return primary key name
                public function getKeyName()
                {
                    return $this->primaryKey;
                }
                
                // Override toArray to ensure ID is included
                public function toArray()
                {
                    $array = parent::toArray();
                    $array['id'] = $this->getKey();
                    return $array;
                }
                
                // Ensure attribute access works correctly
                public function __get($key)
                {
                    // Ensure 'id' is always accessible
                    if ($key === 'id' || $key === $this->primaryKey) {
                        return $this->attributes[$this->primaryKey] ?? parent::__get($key);
                    }
                    return parent::__get($key);
                }
            };

            return $model;
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load user')
                ->body($e->getMessage() ?: 'Unable to connect to the API. Please try again.')
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
        // Remove password if empty (API expects password only when updating)
        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Ensure system_role is set (form uses system_role directly now)
        if (!isset($data['system_role'])) {
            $data['system_role'] = 'user';
        }

        // Remove fields that shouldn't be sent to API
        unset($data['api_type'], $data['source']); // These are read-only from API

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $userId = $record->getKey();
            
            if (!$userId) {
                throw new ApiException("Invalid user ID", 400);
            }

            $updatedUser = $this->repository->updateUser($userId, $data);
            
            if ($updatedUser) {
                Notification::make()
                    ->title('User updated successfully')
                    ->success()
                    ->send();
                
                // Update the record with the returned data
                if ($updatedUser instanceof \App\DTOs\UserResponse) {
                    $recordData = [
                        'id' => $updatedUser->getId(),
                        'name' => $updatedUser->getName(),
                        'email' => $updatedUser->getEmail(),
                        'system_role' => $updatedUser->getSystemRole() ?? 'user',
                        'phone' => $updatedUser->data['phone'] ?? $record->phone ?? null,
                        'address' => $updatedUser->data['address'] ?? $record->address ?? null,
                        'status' => $updatedUser->data['status'] ?? $record->status ?? 'active',
                        'api_type' => $updatedUser->data['api_type'] ?? $record->api_type ?? null,
                        'source' => $updatedUser->data['source'] ?? $record->source ?? null,
                    ];
                    
                    foreach ($recordData as $key => $value) {
                        $record->setAttribute($key, $value);
                        // Also ensure ID is in attributes array
                        if ($key === 'id') {
                            $record->attributes['id'] = $value;
                        }
                    }
                    
                    // Ensure ID is always accessible after update
                    if (isset($recordData['id'])) {
                        $record->attributes[$record->primaryKey] = $recordData['id'];
                    }
                }
            } else {
                Notification::make()
                    ->title('Failed to update user')
                    ->body('The API did not return a successful response.')
                    ->danger()
                    ->send();
            }

            return $record;
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to update user')
                ->body($e->getMessage() ?: 'Unable to update the user. Please try again.')
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete User')
                ->modalDescription('Are you sure you want to delete this user? This action cannot be undone.')
                ->action(function () {
                    $userId = $this->record->getKey();
                    
                    if (!$userId) {
                        Notification::make()
                            ->title('Invalid user ID')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    $repository = app(AdminRepository::class);
                    
                    if ($repository->deleteUser($userId)) {
                        Notification::make()
                            ->title('User deleted successfully')
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('index'));
                    } else {
                        Notification::make()
                            ->title('Failed to delete user')
                            ->body('Please try again or contact support if the problem persists.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

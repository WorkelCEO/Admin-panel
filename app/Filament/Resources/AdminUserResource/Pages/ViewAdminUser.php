<?php

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Exceptions\ApiException;
use App\Filament\Resources\AdminUserResource;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewAdminUser extends ViewRecord
{
    protected static string $resource = AdminUserResource::class;

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

            // Convert to array and create a simple model-like object
            $data = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'system_role' => $user->getSystemRole(),
                'is_super_admin' => $user->isSuperAdmin(),
                'email_verified_at' => $user->getEmailVerifiedAt(),
                'last_seen' => $user->getLastSeen(),
                'workspaces_count' => $user->getWorkspacesCount(),
                'projects_count' => $user->getProjectsCount(),
                'tasks_count' => $user->getTasksCount(),
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
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),
            Actions\EditAction::make(),
        ];
    }
}

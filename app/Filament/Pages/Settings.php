<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiException;
use App\Repositories\AdminRepository;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 40;

    protected AdminRepository $repository;

    public ?array $settings = null;

    public function boot(AdminRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    protected function loadSettings(): array
    {
        try {
            $response = $this->repository->getSettings();
            
            if ($response->isSuccess()) {
                return $response->data ?? [];
            }
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load settings')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->send();
        }

        return [];
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                \Filament\Forms\Form::make()
                    ->schema($this->getFormSchema())
                    ->statePath('data')
            ),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('app_name')
                ->label('Application Name')
                ->required()
                ->maxLength(255),
            Select::make('timezone')
                ->label('Timezone')
                ->options([
                    'UTC' => 'UTC',
                    'America/New_York' => 'America/New_York',
                    'America/Chicago' => 'America/Chicago',
                    'America/Denver' => 'America/Denver',
                    'America/Los_Angeles' => 'America/Los_Angeles',
                    'Europe/London' => 'Europe/London',
                    'Europe/Paris' => 'Europe/Paris',
                    'Asia/Tokyo' => 'Asia/Tokyo',
                ])
                ->required(),
            Select::make('locale')
                ->label('Locale')
                ->options([
                    'en' => 'English',
                    'fr' => 'French',
                    'es' => 'Spanish',
                    'de' => 'German',
                ])
                ->required(),
            Checkbox::make('registration_enabled')
                ->label('Registration Enabled'),
            Checkbox::make('email_verification_required')
                ->label('Email Verification Required'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            $response = $this->repository->updateSettings($data);
            
            if ($response->isSuccess()) {
                Notification::make()
                    ->title('Settings updated successfully')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to update settings')
                    ->body($response->message ?? 'Please try again.')
                    ->danger()
                    ->send();
            }
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to update settings')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

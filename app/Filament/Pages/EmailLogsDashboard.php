<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class EmailLogsDashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected string $view = 'filament.pages.email-logs-dashboard';

    protected static ?string $navigationLabel = 'Email Analytics';

    protected static ?string $title = 'Email Logs Analytics';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 12;
    
    // Disabled: Statistics endpoint not available on API
    protected static bool $shouldRegisterNavigation = false;
}


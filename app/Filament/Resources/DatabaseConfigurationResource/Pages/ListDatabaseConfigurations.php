<?php

namespace App\Filament\Resources\DatabaseConfigurationResource\Pages;

use App\Filament\Resources\DatabaseConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseConfigurations extends ListRecords
{
    protected static string $resource = DatabaseConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


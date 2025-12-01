<?php

namespace App\Filament\Resources\CorsSettingResource\Pages;

use App\Filament\Resources\CorsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCorsSettings extends ListRecords
{
    protected static string $resource = CorsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}








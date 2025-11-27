<?php

namespace App\Filament\Resources\CorsSettingResource\Pages;

use App\Filament\Resources\CorsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCorsSetting extends ViewRecord
{
    protected static string $resource = CorsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}


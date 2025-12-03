<?php

namespace App\Filament\Resources\CapitalInvestmentResource\Pages;

use App\Filament\Resources\CapitalInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCapitalInvestment extends ViewRecord
{
    protected static string $resource = CapitalInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}











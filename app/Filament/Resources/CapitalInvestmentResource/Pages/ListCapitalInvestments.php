<?php

namespace App\Filament\Resources\CapitalInvestmentResource\Pages;

use App\Filament\Resources\CapitalInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCapitalInvestments extends ListRecords
{
    protected static string $resource = CapitalInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}









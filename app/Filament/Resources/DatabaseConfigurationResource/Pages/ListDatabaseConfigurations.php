<?php

namespace App\Filament\Resources\DatabaseConfigurationResource\Pages;

use App\Filament\Resources\DatabaseConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Schema;

class ListDatabaseConfigurations extends ListRecords
{
    protected static string $resource = DatabaseConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Override the table query to prevent errors when table doesn't exist.
     * This is called before any queries are executed.
     */
    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Check if table exists - if not, the resource's table() method will handle empty state
        // But we still need to return a valid query builder to prevent errors
        try {
            $hasTable = Schema::connection('system')->hasTable('database_configurations');
            
            if (!$hasTable) {
                // Return parent query - the resource's table() method will show empty state
                // The whereRaw will prevent actual data retrieval
                $query = parent::getTableQuery();
                return $query->whereRaw('1 = 0');
            }
        } catch (\Exception $e) {
            // If schema check fails, try to return a safe query
            try {
                $query = parent::getTableQuery();
                return $query->whereRaw('1 = 0');
            } catch (\Exception $innerException) {
                // If everything fails, return parent and let error handling catch it
            }
        }

        return parent::getTableQuery();
    }
}


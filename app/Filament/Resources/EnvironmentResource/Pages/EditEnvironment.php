<?php

namespace App\Filament\Resources\EnvironmentResource\Pages;

use App\Filament\Resources\EnvironmentResource;
use App\Models\System\Environment;
use App\Services\EnvironmentVariableService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEnvironment extends EditRecord
{
    protected static string $resource = EnvironmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate uniqueness on system connection
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (Environment::on('system')->where('name', $value)->where('id', '!=', $this->record->id)->exists()) {
                        $fail('The environment name has already been taken.');
                    }
                },
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (Environment::on('system')->where('slug', $value)->where('id', '!=', $this->record->id)->exists()) {
                        $fail('The slug has already been taken.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            throw \Illuminate\Validation\ValidationException::withMessages($validator->errors()->toArray());
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Clear cache
        cache()->forget('environments_count');
        cache()->forget('environment_variables');
        cache()->forget('default_environment');
        
        // Reload variables if this is the default environment
        if ($this->record->is_default) {
            EnvironmentVariableService::reloadVariables();
        }
    }
}


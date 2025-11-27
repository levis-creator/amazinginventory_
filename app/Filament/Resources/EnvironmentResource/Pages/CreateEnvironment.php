<?php

namespace App\Filament\Resources\EnvironmentResource\Pages;

use App\Filament\Resources\EnvironmentResource;
use App\Models\System\Environment;
use App\Services\EnvironmentVariableService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\Rule;

class CreateEnvironment extends CreateRecord
{
    protected static string $resource = EnvironmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate uniqueness on system connection
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (Environment::on('system')->where('name', $value)->exists()) {
                        $fail('The environment name has already been taken.');
                    }
                },
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (Environment::on('system')->where('slug', $value)->exists()) {
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

    protected function afterCreate(): void
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


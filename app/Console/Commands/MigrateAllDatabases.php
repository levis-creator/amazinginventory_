<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateAllDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:all 
                            {--force : Force the operation to run when in production}
                            {--path= : The path to the migrations files to be executed}
                            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                            {--pretend : Dump the SQL queries that would be run}
                            {--seed : Indicates if the seed task should be re-run}
                            {--step : Force the migrations to be run so they can be rolled back individually}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for both system and application databases';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Running migrations for all databases...');
        $this->newLine();

        // Run system database migrations first
        $this->info('📦 Migrating system database...');
        $systemOptions = [
            '--database' => 'system',
            '--path' => 'database/migrations/system',
        ];

        if ($this->option('force')) {
            $systemOptions['--force'] = true;
        }

        if ($this->option('pretend')) {
            $systemOptions['--pretend'] = true;
        }

        if ($this->option('step')) {
            $systemOptions['--step'] = true;
        }

        $systemExitCode = Artisan::call('migrate', $systemOptions);
        
        if ($systemExitCode === 0) {
            $this->info('✅ System database migrations completed successfully');
        } else {
            $this->error('❌ System database migrations failed');
            return $systemExitCode;
        }

        $this->newLine();

        // Run application database migrations
        $this->info('📦 Migrating application database...');
        $appOptions = [];

        if ($this->option('force')) {
            $appOptions['--force'] = true;
        }

        if ($this->option('path')) {
            $appOptions['--path'] = $this->option('path');
        }

        if ($this->option('realpath')) {
            $appOptions['--realpath'] = true;
        }

        if ($this->option('pretend')) {
            $appOptions['--pretend'] = true;
        }

        if ($this->option('step')) {
            $appOptions['--step'] = true;
        }

        $appExitCode = Artisan::call('migrate', $appOptions);
        
        if ($appExitCode === 0) {
            $this->info('✅ Application database migrations completed successfully');
        } else {
            $this->error('❌ Application database migrations failed');
            return $appExitCode;
        }

        $this->newLine();

        // Run seeders if requested
        if ($this->option('seed')) {
            $this->info('🌱 Seeding databases...');
            $seedExitCode = Artisan::call('db:seed', [
                '--force' => $this->option('force'),
            ]);
            
            if ($seedExitCode === 0) {
                $this->info('✅ Database seeding completed successfully');
            } else {
                $this->error('❌ Database seeding failed');
                return $seedExitCode;
            }
        }

        $this->newLine();
        $this->info('✨ All migrations completed successfully!');

        return Command::SUCCESS;
    }
}





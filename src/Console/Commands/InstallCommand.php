<?php

namespace Nawasara\JobVacancy\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\JobVacancy\Console\PrintsJobVacancyLogo;
use Nawasara\JobVacancy\Database\Seeders\PermissionSeeder;

class InstallCommand extends Command
{
    use PrintsJobVacancyLogo;

    protected $signature = 'job-vacancy:install';

    protected $description = 'Install the nawasara/job-vacancy package: (optional publish config), run migrations, seed role + permission, inject the Vault group';

    public function handle(): int
    {
        $this->printJobVacancyLogo();

        $this->newLine();
        $this->info('Installing package nawasara/job-vacancy — the Job Vacancies module, a read-only mirror of data from the upstream service platform.');
        $this->info('Installing Nawasara Job Vacancy...');
        $this->newLine();
        $this->comment('Note: publishing the config is OPTIONAL — without it, the config still comes from the package (mergeConfigFrom) and can still be overridden via .env.');

        if ($this->confirm('Publish config/nawasara-job-vacancy.php? (default: no)', false)) {
            $this->publishConfig();
        }

        if (! $this->runMigrations()) {
            return self::FAILURE;
        }

        $this->seedRoleAndPermissions();
        $this->injectVaultGroup();

        $this->info('Installation complete.');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('  1. Run `php artisan job-vacancy:sync` to fill the snapshot from the upstream service.');
        $this->line('  2. Fill in the credentials in the Vault menu -> group `job-vacancy`.');
        $this->line('  3. Assign role `job-vacancy` to users allowed to access job vacancies.');

        return self::SUCCESS;
    }

    /**
     * Publish config to the app. Idempotent: never overwrites an existing file.
     */
    protected function publishConfig(): void
    {
        $path = config_path('nawasara-job-vacancy.php');

        if (file_exists($path)) {
            $this->comment('Config already published: config/nawasara-job-vacancy.php (skip).');

            return;
        }

        $this->call('vendor:publish', [
            '--provider' => 'Nawasara\JobVacancy\JobVacancyServiceProvider',
            '--tag'      => 'nawasara-job-vacancy-config',
        ]);
    }

    /**
     * Run migrate (without publishing migrations — already loaded by the provider).
     * Laravel's built-in migrate is idempotent: already-run migrations are skipped.
     */
    protected function runMigrations(): bool
    {
        $result = $this->call('migrate', ['--force' => true]);

        return $result === self::SUCCESS;
    }

    /**
     * Seed the `job-vacancy` role + access permission via PermissionSeeder.
     * Idempotent; role & permission are (re)created when missing.
     */
    protected function seedRoleAndPermissions(): void
    {
        $this->callSilently('db:seed', ['--class' => PermissionSeeder::class]);

        $this->info('Role `job-vacancy` + permission job.vacancy.view ready.');
    }

    /**
     * Make sure the published vault config contains the `job-vacancy` group.
     * If the app config is not published, the package config is sufficient
     * (mergeConfigFrom) so this step is skipped.
     */
    protected function injectVaultGroup(): void
    {
        $path = config_path('nawasara-vault.php');

        if (! file_exists($path)) {
            $this->comment('config/nawasara-vault.php not published — group `job-vacancy` is still available via the package config (skip).');

            return;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return;
        }

        if (preg_match("/'job-vacancy'\s*=>/", $content)) {
            $this->comment('Group `job-vacancy` already present in config/nawasara-vault.php (skip).');

            return;
        }

        $block = $this->vaultGroupBlock();

        if (preg_match("/'groups'\s*=>\s*\[/", $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $content = substr($content, 0, $offset)."\n".$block.substr($content, $offset);

            file_put_contents($path, $content);

            $this->info('Group `job-vacancy` injected into config/nawasara-vault.php.');

            return;
        }

        $this->warn('Structure of config/nawasara-vault.php not recognized — injection cancelled. Add the `job-vacancy` group manually.');
    }

    /**
     * PHP block of the `job-vacancy` group for config/nawasara-vault.php.
     * Identical to the definition in the nawasara-vault package config.
     */
    protected function vaultGroupBlock(): string
    {
        return <<<'PHP'
            'job-vacancy' => [
                'label'  => 'Job Vacancies',
                'icon'   => 'lucide-briefcase',
                'test'   => \Nawasara\JobVacancy\Services\JobVacancyClient::class.'@testConnection',
                'fields' => [
                    'base_url'  => ['label' => 'Base URL', 'type' => 'text', 'placeholder' => 'https://jobs.example.com'],
                    'api_token' => ['label' => 'API Token', 'type' => 'password'],
                ],
            ],
        PHP;
    }
}
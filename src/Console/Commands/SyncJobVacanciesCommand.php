<?php

namespace Nawasara\JobVacancy\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\JobVacancy\Console\PrintsJobVacancyLogo;
use Nawasara\JobVacancy\Jobs\SyncJobVacanciesJob;
use Nawasara\JobVacancy\Services\JobVacancyClient;

class SyncJobVacanciesCommand extends Command
{
    use PrintsJobVacancyLogo;

    protected $signature = 'job-vacancy:sync
                            {--sync : Run synchronously (skip the queue) — for first run / debug}';

    protected $description = 'Pull job vacancies from the upstream service into the internal DB snapshot. Default: dispatch the job to the queue.';

    public function handle(): int
    {
        $this->printJobVacancyLogo();

        if (JobVacancyClient::fromVault() === null) {
            $this->error('Vault group job-vacancy is not configured (base_url / api_token).');

            return self::FAILURE;
        }

        $job = new SyncJobVacanciesJob();

        if ($this->option('sync')) {
            try {
                $job->handle();
                $this->info('Job vacancy sync completed synchronously.');
            } catch (\Throwable $e) {
                $this->error('  ✗ Failed: '.$e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        dispatch($job);
        $this->info('Job vacancy sync added to the queue.');

        return self::SUCCESS;
    }
}
<?php

namespace Nawasara\JobVacancy\Console;

trait PrintsJobVacancyLogo
{
    private array $jobVacancyLogoLines = [
        '  ███    ██  █████  ██      ██  █████   ███████   █████  ██████    █████  ',
        '  ████   ██ ██   ██ ██      ██ ██   ██  ██       ██   ██ ██   ██  ██   ██ ',
        '  ██ ██  ██ ███████ ██  ██  ██ ███████  ███████  ███████ ██████   ███████ ',
        '  ██  ██ ██ ██   ██ ██  ██  ██ ██   ██       ██  ██   ██ ██  ██   ██   ██ ',
        '  ██   ████ ██   ██  ████████  ██   ██  ███████  ██   ██ ██   ██  ██   ██ ',
    ];

    private array $jobVacancyLogoGradient = [
        '38;2;110;231;183',
        '38;2;81;221;168',
        '38;2;52;211;153',
        '38;2;16;185;129',
        '38;2;5;150;105',
    ];

    protected function printJobVacancyLogo(): void
    {
        $this->newLine();

        foreach ($this->jobVacancyLogoLines as $i => $line) {
            $this->output->writeln($this->output->isDecorated()
                ? "\033[{$this->jobVacancyLogoGradient[$i]}m{$line}\033[0m"
                : $line);
        }

        $this->newLine();
    }
}
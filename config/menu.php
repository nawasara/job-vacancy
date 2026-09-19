<?php

return [
    [
        'workspace' => 'rakaca',
        'label' => 'Rakaca',
        'icon' => 'lucide-briefcase',
        'group' => 'Services',
        'url' => '',
        'permission' => 'job.vacancy.view',
        'submenu' => [
            [
                'label' => 'Job Vacancies',
                'icon' => 'lucide-briefcase',
                'url' => url('nawasara-job-vacancy/job-vacancies'),
                'permission' => 'job.vacancy.view',
                'navigate' => true,
            ],
        ],
    ],
];
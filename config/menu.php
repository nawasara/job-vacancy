<?php

return [
    [
        'workspace' => 'rakaca',
        'label' => 'Rakaca',
        'icon' => 'lucide-briefcase',

        /*
         * ⚠️ Must be one of WorkspaceManager::GROUP_ORDER, and those values are
         * Indonesian: Layanan, Observability, Keamanan, Aset & Registry,
         * Pengaturan, Lainnya.
         *
         * An unrecognised group is not an error. WorkspaceManager::grouped()
         * silently drops it into 'Lainnya', so the menu still appears, just in
         * the wrong place. This said 'Services' until 20 September 2026 and
         * nobody would have noticed until someone went looking for Rakaca under
         * Layanan and did not find it.
         */
        'group' => 'Layanan',

        'url' => '',
        'permission' => 'job.vacancy.view',
        'submenu' => [
            [
                'label' => 'Lowongan Kerja',
                'icon' => 'lucide-briefcase',
                'url' => url('nawasara-job-vacancy/job-vacancies'),
                'permission' => 'job.vacancy.view',
                'navigate' => true,
            ],
        ],
    ],
];

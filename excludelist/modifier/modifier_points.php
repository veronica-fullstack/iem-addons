<?php

$installer_AddonName = "excludelist";
$data_files = [];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../functions/api/send.php',
    'data' => [
        [
            'default;666;lr;MULTILINKS_RUN1;MULTILINKS_RUN1_REPLACE',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../functions/send.php',
    'data' => [
        [
            'default;947;lr;MULTILINKS_RUN52;MULTILINKS_RUN52_REPLACE',
        ],
        [
            'default;760;lr;MULTILINKS_RUN3;MULTILINKS_RUN3_REPLACE',
        ],
        [
            'default;320;lr;MULTILINKS_RUN2;MULTILINKS_RUN2_REPLACE',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../functions/subscribers_import.php',
    'data' => [
        [
            'default;1040;lr;MULTILINKS_RUN6;MULTILINKS_RUN6_REPLACE',
        ], [
            'default;453;lr;MULTILINKS_RUN5;MULTILINKS_RUN5_REPLACE',
        ], [
            'default;192;lr;MULTILINKS_RUN4;MULTILINKS_RUN4_REPLACE',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../functions/autoresponders.php',
    'data' => [
        [
            'default;886;lr;MULTILINKS_RUN8;MULTILINKS_RUN8_REPLACE',
        ], [
            'default;820;lr;MULTILINKS_RUN7;MULTILINKS_RUN7_REPLACE',
        ], [
            'default;565;lr;MULTILINKS_RUN56;MULTILINKS_RUN56_REPLACE',
        ], [
            'default;1361;lr;MULTILINKS_RUN57;MULTILINKS_RUN57_REPLACE',
        ], [
            'default;618;lr;MULTILINKS_RUN53;MULTILINKS_RUN53_REPLACE',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../functions/api/jobs_autoresponders.php',
    'data' => [
        [
            'default;1083;lr;MULTILINKS_RUN9;MULTILINKS_RUN9_REPLACE',
        ]],
];


$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../com/templates/send_step3.tpl',
    'type' => 'tpl',
    'data' => [
        [
            'default;275;i;MULTILINKS_RUN51;MULTILINKS_RUN51_REPLACE;',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../com/templates/subscribers_import_step1.tpl',
    'type' => 'tpl',
    'data' => [
        [
            'default;37;i;MULTILINKS_RUN51;MULTILINKS_RUN51_REPLACE;',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../com/templates/autoresponder_form_step3.tpl',
    'type' => 'tpl',
    'data' => [
        [
            'default;40;lr;MULTILINKS_RUN51;MULTILINKS_RUN51_REPLACE;',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../com/templates/send_step4_cron.tpl',
    'type' => 'tpl',
    'data' => [
        [
            'default;31;i;MULTILINKS_RUN54;MULTILINKS_RUN54_REPLACE;',
        ]],
];

$data_files[] = [
    'reque' => true,
    'data_file' => IEM_PATH . '/../com/templates/send_step4.tpl',
    'type' => 'tpl',
    'data' => [
        [
            'default;32;lr;MULTILINKS_RUN55;MULTILINKS_RUN55_REPLACE;',
        ]],
];

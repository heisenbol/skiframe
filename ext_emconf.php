<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Iframe filter for HTML Content Elements - skiframe',
    'description' => 'Filters iframes in HTML Content Elements and replaces them with placeholders that need to be activated in order to show the original iframe. Needs fluid_styled_content.',
    'category' => 'fe',
    'author' => 'Stefanos Karasavvidis',
    'author_email' => 'sk@karasavvidis.gr',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.4.99',
            'fluid_styled_content' => '9.5.0-10.4.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

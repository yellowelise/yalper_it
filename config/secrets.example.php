<?php

// Copiare questo file fuori dal webroot in:
// /home/yalperit/.config/yalper/secrets.php
// In sviluppo si puo usare config/secrets.local.php, che non va versionato.
return array(
    'database' => array(
        'host' => 'localhost',
        'name' => 'yalperit_db',
        'user' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
    ),
    'pusher' => array(
        'key' => 'CHANGE_ME',
        'secret' => 'CHANGE_ME',
        'app_id' => 'CHANGE_ME',
        'cluster' => 'eu',
    ),
);


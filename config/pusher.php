<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/secrets_loader.php';

function yalper_create_pusher()
{
    $config = yalper_secret_section('pusher', array('key', 'secret', 'app_id', 'cluster'));

    return new Pusher\Pusher(
        $config['key'],
        $config['secret'],
        $config['app_id'],
        array(
            'cluster' => $config['cluster'],
            'useTLS' => true,
        )
    );
}

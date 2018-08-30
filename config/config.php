<?php

use Piwik\Plugins\GoogleAuthenticator\SessionAuth;

require dirname(dirname(__FILE__)) . '/vendor/autoload.php';

return array(
    'Piwik\Auth' => DI\object('Piwik\Plugins\GoogleAuthenticator\Auth'),
    'GoogleAuthenticator' => DI\factory(function() {
        return new Piwik\Plugins\GoogleAuthenticator\PHPGangsta\GoogleAuthenticator();
    }),
    \Piwik\Session\SessionAuth::class => DI\object(SessionAuth::class),
);
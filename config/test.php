<?php

require dirname(dirname(__FILE__)) . '/vendor/autoload.php';

return array(
    'Piwik\Auth' => DI\object('Piwik\Plugins\GoogleAuthenticator\Auth'),
    'GoogleAuthenticator' => DI\factory(function() {
        return new Piwik\Plugins\GoogleAuthenticator\tests\Mock\GoogleAuthenticator();
    })
);
<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\GoogleAuthenticator;

use Piwik\AuthResult;
use Piwik\Session\SessionFingerprint;
use Piwik\Session\SessionNamespace;

class SessionAuth extends \Piwik\Session\SessionAuth
{
    public function authenticate()
    {
        $sessionFingerprint = new SessionFingerprint();
        if ($sessionFingerprint->getUser()) {
            $storage = new Storage($sessionFingerprint->getUser());
            if (!$storage->isActive()) {
                return parent::authenticate();
            }

            $session = new SessionNamespace('GoogleAuthenticator');
            if (!$session->validatedWithAuthCode) {
                return new AuthResult(Auth::AUTH_CODE_REQUIRED, $sessionFingerprint->getUser(), null);
            }
        }

        return parent::authenticate();
    }
}
<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\GoogleAuthenticator;

use Exception;
use Piwik\Auth as AuthInterface;
use Piwik\AuthResult;
use Piwik\Piwik;
use Piwik\ProxyHttp;
use Piwik\Session\SessionNamespace;

/**
 * Extends SessionInitializer from Login plugin to handle the case that an auth code is required for login
 */
class SessionInitializer extends \Piwik\Session\SessionInitializer
{
    /**
     * Authenticates the user and, if successful, initializes an authenticated session.
     *
     * @param \Piwik\Auth $auth The Auth implementation to use.
     * @throws AuthCodeRequiredException If authentication was successful but an google auth code is required to proceed
     * @throws Exception If authentication fails or the user is not allowed to login for some reason.
     */
    public function initSession(AuthInterface $auth)
    {
        $this->regenerateSessionId();

        $authResult = $this->doAuthenticateSession($auth);

        if (!$authResult->wasAuthenticationSuccessful()) {
            if ($authResult->getCode() === Auth::AUTH_CODE_REQUIRED) {
                // Authenticate user with cookie, but throw exception as auth code is still required
                $this->processSuccessfulSession($authResult);
                throw new AuthCodeRequiredException();
            }

            Piwik::postEvent('Login.authenticate.failed', array($auth->getLogin()));

            $this->processFailedSession();
        } else {
            Piwik::postEvent('Login.authenticate.successful', array($auth->getLogin()));

            $this->processSuccessfulSession($authResult);
        }
    }

    /**
     * Executed when the session was successfully authenticated.
     *
     * @param AuthResult $authResult The successful authentication result.
     * @param bool $rememberMe Whether the authenticated session should be remembered after
     *                         the browser is closed or not.
     */
    protected function processSuccessfulSession(AuthResult $authResult)
    {
        parent::processSuccessfulSession($authResult);

        $storage = new Storage($authResult->getIdentity());

        if ($storage->isActive() && $authResult->wasAuthenticationSuccessful()) {
            $_SESSION['auth_code'] = $this->getHashTokenAuth($authResult->getIdentity(), $storage->getSecret());
        }
    }
}

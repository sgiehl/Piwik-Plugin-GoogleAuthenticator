<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\GoogleAuthenticator;

use \Exception;
use Piwik\AuthResult;
use Piwik\Container\StaticContainer;
use Piwik\Session;
use Piwik\Session\SessionNamespace;

/**
 * Extends Auth class of Login plugin to handle required auth codes
 */
class Auth extends \Piwik\Plugins\Login\Auth
{
    /**
     * Used as AuthResult to indicate missing auth code
     */
    const AUTH_CODE_REQUIRED = -42;

    /**
     * Auth-Code
     * @var string
     */
    protected $authCode = null;

    /**
     * Indicates whether current session was authenticated with an auth code
     * @var bool
     */
    protected $validatedWithAuthCode = null;

    /**
     * Returns authentication module's name
     * @return string
     */
    public function getName()
    {
        return 'GoogleAuthenticator';
    }

    /**
     * Returns if the current session is validated with auth code
     * @return bool
     */
    protected function getValidatedWithAuthCode()
    {
        if (!is_null($this->validatedWithAuthCode)) {
            return $this->validatedWithAuthCode;
        }

        try {
            $session = new SessionNamespace('GoogleAuthenticator');
            $this->validatedWithAuthCode = (boolean)$session->validatedWithAuthCode;
        } catch (Exception $e) {
            // ignore as that should only happen in tests
        }

        return $this->validatedWithAuthCode;
    }

    /**
     * Returns if the user needs to authenticate with an auth code
     * @return bool
     */
    public function isAuthCodeRequired()
    {
        if (empty($this->login)) {
            return false;
        }

        $storage = new Storage($this->login);

        if ($storage->isActive() && !$this->getValidatedWithAuthCode()) {
            return true;
        }

        return false;
    }

    /**
     * Returns if the set auth code is valid and updates the validation status of the current session
     * @return bool
     */
    public function validateAuthCode()
    {
        $storage = new Storage($this->getLogin());

        $secret = $storage->getSecret();
        $googleAuth = StaticContainer::get('GoogleAuthenticator');
        if (!empty($secret) && $googleAuth->verifyCode($secret, $this->authCode, 2)) {
            $this->setValidatedWithAuthCode(true);
            return true;
        }
        return false;
    }

    /**
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        Session::start();

        $sessionAuth = StaticContainer::get(\Piwik\Session\SessionAuth::class);
        $authResult = $sessionAuth->authenticate();
        if ($authResult->wasAuthenticationSuccessful()) {
            return $authResult;
        }

        if ($authResult->getCode() != self::AUTH_CODE_REQUIRED) {
            $authResult = parent::authenticate();
        }

        // if authentication was correct, check if an auth code is required
        if ($authResult->wasAuthenticationSuccessful() || $authResult->getCode() == self::AUTH_CODE_REQUIRED) {
            $this->setLogin($authResult->getIdentity());
            $storage = new Storage($authResult->getIdentity());

            $this->validateAuthCode();

            $settings = new \Piwik\Plugins\GoogleAuthenticator\SystemSettings();
            $globalEnable = $settings->globalEnable->getValue();
            
            if($globalEnable){
                
                // only login if user already validated with auth code
                if ($this->getValidatedWithAuthCode()) {
                        return $authResult;
                }

             }else{
                
                // if Google Authenticator is disabled, or user already validated with auth code
                if (!$storage->isActive() || $this->getValidatedWithAuthCode()) {
                        return $authResult;
                }
                
            }

            $authResult = new AuthResult(self::AUTH_CODE_REQUIRED, $this->login, $this->token_auth);
        }
        return $authResult;
    }

    /**
     * Accessor to set auth code
     *
     * @param string $authCode user's auth code
     */
    public function setAuthCode($authCode)
    {
        $this->authCode = $authCode;
    }

    /**
     * Sets whether the current session is validated with auth code
     * @param bool|true $isValid
     */
    protected function setValidatedWithAuthCode($isValid = true)
    {
        $this->validatedWithAuthCode = $isValid;
        try {
            $session = new SessionNamespace('GoogleAuthenticator');
            $session->validatedWithAuthCode = $isValid;
        } catch (Exception $e) {
            // ignore as that should only happen in tests
        }
    }
}
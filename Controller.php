<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\GoogleAuthenticator;

use Piwik\Common;
use Piwik\Auth as AuthInterface;
use Piwik\Container\StaticContainer;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugins\Login\PasswordResetter;
use Piwik\Url;
use Piwik\View;

/**
 * Google Authenticator Login controller
 */
class Controller extends \Piwik\Plugins\Login\Controller
{
    /**
     * @var Auth
     */
    private $auth;

    private $passwordResetter;

    /**
     * Constructor.
     *
     * @param PasswordResetter $passwordResetter
     * @param AuthInterface $auth
     * @param SessionInitializer $sessionInitializer
     */
    public function __construct($passwordResetter = null, $auth = null, $sessionInitializer = null)
    {
        if (empty($auth)) {
            $auth = StaticContainer::get('Piwik\Auth');
        }
        $this->auth = $auth;

        if (empty($sessionInitializer)) {
            $sessionInitializer = new SessionInitializer();
        }

        if (empty($passwordResetter)) {
            $this->passwordResetter = new PasswordResetter(null, 'GoogleAuthenticator');
        }

        parent::__construct($this->passwordResetter, $auth, $sessionInitializer);
    }

    protected function getAuthCodeForm()
    {
        static $form;
        if (empty($form)) {
            $form = new FormAuthCode();
            $form->removeAttribute('action'); // remove action attribute, otherwise hash part will be lost
        }
        return $form;
    }

    /**
     * Form to ask the users to authenticate with auth code
     * @param string $messageNoAccess
     * @return string
     * @throws \Exception
     */
    public function authcode($messageNoAccess = null)
    {
        $rememberMe = Common::getRequestVar('form_rememberme', '0', 'string') == '1';

        $form = $this->getAuthCodeForm();
        if ($form->getSubmitValue('form_authcode') && $form->validate()) {
            $nonce = $form->getSubmitValue('form_nonce');
            if (Nonce::verifyNonce('Login.login', $nonce)) {
                $this->auth->setAuthCode($form->getSubmitValue('form_authcode'));
                if ($this->auth->validateAuthCode()) {
                    try {
                        $rememberMe = Common::getRequestVar('form_rememberme', '0', 'string') == '1';
                        $this->authenticateAndRedirect($this->auth->getLogin(), null, $rememberMe);
                    } catch (\Exception $e) {
                    }
                }

                Nonce::discardNonce('Login.login');
                $form->getElements()[0]->setError(Piwik::translate('GoogleAuthenticator_AuthCodeInvalid'));
            } else {
                $messageNoAccess = $this->getMessageExceptionNoAccess();
            }
        }

        return $this->renderAuthCode($this->auth->getLogin(), Piwik::translate('Login_LogIn'), $rememberMe, $messageNoAccess);
    }

    /**
     * Renders form to ask user for an auth code
     *
     * @param string $login
     * @param int $rememberMe
     * @param string $messageNoAccess
     * @return string
     */
    public function renderAuthCode($login, $formTitle, $rememberMe = 0, $messageNoAccess = null)
    {
        $view = new View('@GoogleAuthenticator/authcode');
        $view->logouturl = Url::getCurrentUrlWithoutQueryString() . '?' . Url::getQueryStringFromParameters(array(
                'module' => $this->auth->getName(),
                'action' => 'logout'
            ));
        $view->login = $login;
        $view->formTitle = $formTitle;
        $view->AccessErrorString = $messageNoAccess;
        $view->infoMessage = Piwik::translate('GoogleAuthenticator_AuthCodeRequired');
        $view->rememberMe = $rememberMe;
        $this->configureView($view);
        $view->addForm($this->getAuthCodeForm());
        self::setHostValidationVariablesView($view);

        return $view->render();
    }


    /**
     * Pretty the same as in login action of Login plugin
     * - Adds the handling for required auth code for login
     *
     * @param string $messageNoAccess Access error message
     * @param bool $infoMessage
     * @internal param string $currentUrl Current URL
     * @return string
     */
    public function login($messageNoAccess = null, $infoMessage = false)
    {
        if ($this->auth->isAuthCodeRequired()) {
            return $this->authcode();
        }

        if (!Piwik::isUserIsAnonymous()) {
            $urlToRedirect = Url::getCurrentUrlWithoutQueryString();
            Url::redirectToUrl($urlToRedirect);
        }

        $form = new \Piwik\Plugins\Login\FormLogin();
        $form->removeAttribute('action'); // remove action attribute, otherwise hash part will be lost
        if ($form->validate()) {
            $nonce = $form->getSubmitValue('form_nonce');
            if (Nonce::verifyNonce('Login.login', $nonce)) {
                $login = $form->getSubmitValue('form_login');
                $password = $form->getSubmitValue('form_password');
                $rememberMe = $form->getSubmitValue('form_rememberme') == '1';
                try {
                    $this->authenticateAndRedirect($login, $password, $rememberMe);
                } catch (AuthCodeRequiredException $e) {
                    return $this->authcode();
                } catch (\Exception $e) {
                    $messageNoAccess = $e->getMessage();
                }
            } else {
                $messageNoAccess = $this->getMessageExceptionNoAccess();
            }
        }

        $view = new View('@Login/login');
        $view->AccessErrorString = $messageNoAccess;
        $view->infoMessage = nl2br($infoMessage);
        $view->addForm($form);
        $this->configureView($view);
        self::setHostValidationVariablesView($view);

        return $view->render();
    }


    /**
     * Password reset confirmation action. Finishes the password reset process.
     * Users visit this action from a link supplied in an email.
     */
    public function confirmResetPassword($messageNoAccess = null)
    {
        $login = Common::getRequestVar('login', '');
        $storage = new Storage($login);

        $authCodeValidOrNotRequired = !$storage->isActive();

        if (!$authCodeValidOrNotRequired) {
            $googleAuth = new PHPGangsta\GoogleAuthenticator();
            $form = $this->getAuthCodeForm();

            if ($form->getSubmitValue('form_authcode') && $form->validate()) {
                $nonce = $form->getSubmitValue('form_nonce');
                if (Nonce::verifyNonce('Login.login', $nonce)) {
                    if ($googleAuth->verifyCode($storage->getSecret(), $form->getSubmitValue('form_authcode'))) {
                        $authCodeValidOrNotRequired = true;
                    }

                    Nonce::discardNonce('Login.login');
                    $form->getElements()[0]->setError(Piwik::translate('GoogleAuthenticator_AuthCodeInvalid'));
                } else {
                    $messageNoAccess = $this->getMessageExceptionNoAccess();
                }
            }

            if (!$authCodeValidOrNotRequired) {
                return $this->renderAuthCode($login, Piwik::translate('General_ChangePassword'), 0, $messageNoAccess);
            }
        }

        return parent::confirmResetPassword();
    }

    /**
     * The action used after a password is successfully reset. Displays the login
     * screen with an extra message. A separate action is used instead of returning
     * the HTML in confirmResetPassword so the resetToken won't be in the URL.
     */
    public function resetPasswordSuccess()
    {
        $urlToRedirect = Url::getCurrentUrlWithoutQueryString();
        $urlToRedirect .= '?' . Url::getQueryStringFromParameters(array(
                'module' => 'GoogleAuthenticator',
                'action' => 'passwordchanged'
            ));


        Url::redirectToUrl($urlToRedirect);
    }

    public function passwordchanged()
    {
        return $this->login($errorMessage = null, $infoMessage = Piwik::translate('Login_PasswordChanged'));
    }

    /**
     * Configure common view properties
     *
     * @param View $view
     */
    private function configureView($view)
    {
        $this->setBasicVariablesView($view);

        $view->linkTitle = Piwik::getRandomTitle();

        // crsf token: don't trust the submitted value; generate/fetch it from session data
        $view->nonce = Nonce::getNonce('Login.login');
    }

    /**
     * Settings page for the user - allow activating / disabling Google Authenticator and to generate secrets
     *
     * @return string
     * @throws \Exception
     * @throws \Piwik\NoAccessException
     */
    public function settings()
    {
        Piwik::checkUserIsNotAnonymous();

        $view = new View('@GoogleAuthenticator/settings');
        $this->setGeneralVariablesView($view);

        $googleAuth = new PHPGangsta\GoogleAuthenticator();

        $storage = new Storage(Piwik::getCurrentUserLogin());

        $view->activated = $view->disabled = false;
        if (Common::getRequestVar('activate', 0, 'int')) {
            $storage->activate();
            $view->activated = true;
        }

        if (Common::getRequestVar('disable', 0, 'int')) {
            $storage->deactivate();
            $view->disabled = true;
        }

        $secret = $storage->getSecret();

        $view->showSetUp = Common::getRequestVar('setup', 0, 'int');
        $view->googleAuthIsActive = $storage->isActive();
        $view->googleAuthSecret = $secret;
        $view->googleAuthImage = $googleAuth->getQRCodeGoogleUrl(Piwik::getCurrentUserLogin(), $secret,
            'Piwik - ' . Url::getCurrentHost());

        return $view->render();
    }

    const AUTH_CODE_NONCE = 'saveAuthCode';

    /**
     * Action to generate a new Google Authenticator secret for the current user
     *
     * @return string
     * @throws \Exception
     * @throws \Piwik\NoAccessException
     */
    public function regenerate()
    {
        Piwik::checkUserIsNotAnonymous();

        $view = new View('@GoogleAuthenticator/regenerate');
        $this->setGeneralVariablesView($view);

        $googleAuth = new PHPGangsta\GoogleAuthenticator();

        $storage = new Storage(Piwik::getCurrentUserLogin());
        $secret = Common::getRequestVar('gasecret', '', 'string');
        $authCode = Common::getRequestVar('gaauthcode', '', 'string');
        $authCodeNonce = Common::getRequestVar('authCodeNonce', '', 'string');
        $title = Common::getRequestVar('gatitle', $storage->getTitle(), 'string');
        $description = Common::getRequestVar('gadescription', $storage->getDescription(), 'string');

        if (!empty($secret) && !empty($authCode) && Nonce::verifyNonce(self::AUTH_CODE_NONCE, $authCodeNonce) &&
            $googleAuth->verifyCode($secret, $authCode, 2)
        ) {
            $storage->setSecret($secret);
            $storage->setDescription($description);
            $storage->setTitle($title);
            $this->auth->setAuthCode($authCode);
            $this->auth->validateAuthCode();
            Url::redirectToUrl(Url::getCurrentUrlWithoutQueryString() . Url::getCurrentQueryStringWithParametersModified(array(
                    'action' => 'settings',
                    'activate' => '1'
                )));
        }

        if (empty($secret)) {
            $secret = $googleAuth->createSecret(32);
        }

        $view->title = $title;
        $view->description = $description;
        $view->authCodeNonce = Nonce::getNonce(self::AUTH_CODE_NONCE);
        $view->newSecret = $secret;
        $view->googleAuthImage = $googleAuth->getQRCodeGoogleUrl($description, $secret, $title);

        return $view->render();
    }
}

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
use Piwik\Container\StaticContainer;
use Piwik\FrontController;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugin\Manager;

/**
 *
 */
class GoogleAuthenticator extends \Piwik\Plugins\Login\Login
{
    /**
     * Set login name and authentication token for API request.
     * Listens to API.Request.authenticate hook.
     */
    public function ApiRequestAuthenticate($tokenAuth)
    {
        /** @var \Piwik\Auth $auth */
        $auth = StaticContainer::get('Piwik\Auth');
        $auth->setLogin($login = null);
        $auth->setTokenAuth($tokenAuth);
        $auth->setAuthCode(Common::getRequestVar('auth_code', '', 'string'));
    }

    /**
     * Redirects to Login form with error message.
     * Listens to User.isNotAuthorized hook.
     */
    public function noAccess(\Exception $exception)
    {
        $frontController = FrontController::getInstance();

        if (Common::isXmlHttpRequest()) {
            echo $frontController->dispatch('GoogleAuthenticator', 'ajaxNoAccess', array($exception->getMessage()));
            return;
        }

        echo $frontController->dispatch('GoogleAuthenticator', 'login', array($exception->getMessage()));
    }

    public function getJsFiles(&$javascriptFiles)
    {
        parent::getJsFiles($javascriptFiles);
        $javascriptFiles[] = "plugins/GoogleAuthenticator/angularjs/settings.controller.js";
    }

    public function postLoad()
    {
        $this->activate();
    }

    /**
     * Deactivate default Login module, as both cannot be activated together
     *
     * TODO: shouldn't disable Login plugin but have to wait until Dependency Injection is added to core
     */
    public function activate()
    {
        if (Manager::getInstance()->isPluginActivated("Login") == true) {
            Manager::getInstance()->deactivatePlugin("Login");
            $notification = new Notification(Piwik::translate('GoogleAuthenticator_LoginPluginDisabled'));
            $notification->context = Notification::CONTEXT_INFO;
            Notification\Manager::notify('GoogleAuthenticator_LoginPluginDisabled', $notification);
        }
    }

    /**
     * Activate default Login module, as one of them is needed to access Piwik
     */
    public function deactivate()
    {
        if (Manager::getInstance()->isPluginActivated("Login") == false) {
            Manager::getInstance()->activatePlugin("Login");
        }
    }

}

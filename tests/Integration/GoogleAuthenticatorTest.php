<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\GoogleAuthenticator\tests\Integration;

use Piwik\AuthResult;
use Piwik\Container\StaticContainer;
use Piwik\DbHelper;
use Piwik\Plugins\GoogleAuthenticator\Auth;
use Piwik\Plugins\GoogleAuthenticator\PHPGangsta\GoogleAuthenticator;
use Piwik\Plugins\GoogleAuthenticator\Storage;
use Piwik\Plugins\UsersManager\API;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * Class GoogleAuthenticatorTest
 *
 * @group Plugins
 * @group GoogleAuthenticator
 */
class GoogleAuthenticatorTest extends IntegrationTestCase
{

    /**
     * @var Auth
     */
    private $auth;

    private $secret;

    public function setUp()
    {
        parent::setUp();

        // setup the access layer
        FakeAccess::setIdSitesView(array(1, 2));
        FakeAccess::setIdSitesAdmin(array(3, 4));

        //finally we set the user as a Super User by default
        FakeAccess::$superUser = true;

        $this->auth = new Auth();

        $ga = new GoogleAuthenticator();

        // ensure auth object uses correct instance
        StaticContainer::getContainer()->set('GoogleAuthenticator', $ga);

        $this->secret = $ga->createSecret(32);
    }

    public function testAuthenticateFailureNoLoginNoTokenAuth()
    {
        // no login; no token auth
        $rc = $this->auth->authenticate();
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureEmptyLoginNoTokenAuth()
    {
        // empty login; no token auth
        $this->auth->setLogin('');
        $rc = $this->auth->authenticate();
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureNonExistentUser()
    {
        // non-existent user
        $this->auth->setLogin('nobody');
        $rc = $this->auth->authenticate();
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureAnonymousNotExisting()
    {
        // anonymous user doesn't exist yet
        $rc = $this->authenticate($login = 'anonymous', $authToken = '');
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureAnonymousNotExistentEmptyLogin()
    {
        // empty login; anonymous user doesn't exist yet
        $rc = $this->authenticate($login = '', $authToken = 'anonymous');

        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureAnonymousNotExistentEmptyLoginWithTokenAuth()
    {
        // API authentication; anonymous user doesn't exist yet
        $rc = $this->authenticate($login = null, $authToken = 'anonymous');
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureAnonymousNotExistentWithLoginAndTokenAuth()
    {
        // anonymous user doesn't exist yet
        $rc = $this->authenticate($login = 'anonymous', $authToken = 'anonymous');
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureAnonymousWithLogin()
    {
        DbHelper::createAnonymousUser();

        // missing token_auth
        $rc = $this->authenticate($login = 'anonymous', $authToken = '');
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureAnonymousEmptyLoginWithTokenAuth()
    {
        DbHelper::createAnonymousUser();

        // empty login
        $rc = $this->authenticate($login = '', $authToken = 'anonymous');
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureAnonymousLoginTokenAuthMissmatch()
    {
        DbHelper::createAnonymousUser();

        // not equal
        $rc = $this->authenticate($login = 'anonymous', $authToken = 0);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateSuccessAnonymousWithTokenAuth()
    {
        DbHelper::createAnonymousUser();

        // API authentication
        $rc = $this->authenticate($login = null, $authToken = 'anonymous');
        $this->assertUserLogin($rc, $login = 'anonymous', $tokenLength = 9);
    }

    public function testAuthenticateSuccessAnonymous()
    {
        DbHelper::createAnonymousUser();

        // valid login & token auth
        $rc = $this->authenticate($login = 'anonymous', $authToken = 'anonymous');
        $this->assertUserLogin($rc, $login = 'anonymous', $tokenLength = 9);
    }

    public function testAuthenticateFailureUserEmptyTokenAuth()
    {
        $user = $this->_setUpUser();

        // empty token auth
        $rc = $this->authenticate($login = $user['login'], $authToken = '');
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserInvalidTokenAuth()
    {
        $user = $this->_setUpUser();

        // not a token auth
        $rc = $this->authenticate($login = $user['login'], $authToken = $user['password']);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserInvalidTokenAuth2()
    {
        $user = $this->_setUpUser();

        // not a token auth
        $rc = $this->authenticate($login = $user['login'], $authToken = md5($user['password']));
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserEmptyLogin()
    {
        $user = $this->_setUpUser();

        // empty login
        $rc = $this->authenticate($login = '', $user['tokenAuth']);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserWithSuperUserAccessEmptyLogin()
    {
        $user = $this->_setUpUser();
        $this->_setUpSuperUserAccessViaDb();

        // empty login
        $rc = $this->authenticate($login = '', $user['tokenAuth']);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserLoginTokenAuthMissmatch()
    {
        $this->_setUpUser();

        // not equal
        $rc = $this->authenticate($login = 0, $authToken = 0);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserLoginTokenAuthMissmatch2()
    {
        $user = $this->_setUpUser();

        // not equal
        $rc = $this->authenticate($login = 0, $user['tokenAuth']);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserLoginTokenAuthMissmatch3()
    {
        $user = $this->_setUpUser();

        // not equal
        $rc = $this->authenticate($user['login'], $authToken = 0);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserWithSuperUserAccessLoginTokenAuthMissmatch()
    {
        $user = $this->_setUpUser();
        $this->_setUpSuperUserAccessViaDb();

        // not equal
        $rc = $this->authenticate($login = null, $authToken = $user['password']);
        $this->assertFailedLogin($rc);
    }

    public function testAuthenticateFailureUserTokenAuthAuthCodeMissing()
    {
        $user = $this->_setUpUser();
        $this->enableGoogleAuthenticator($user['login'], $this->secret);

        // API authentication
        $rc = $this->authenticate($login = null, $user['tokenAuth']);
        $this->assertFailedLoginAuthCodeMissing($rc);
    }

    public function testAuthenticateFailureUserTokenAuthAuthCodeInvalid()
    {
        $user = $this->_setUpUser();
        $this->enableGoogleAuthenticator($user['login'], $this->secret);

        // API authentication
        $rc = $this->authenticate($login = null, $user['tokenAuth'], $this->getInvalidAuthCode());
        $this->assertFailedLoginAuthCodeMissing($rc);
    }

    public function testAuthenticateSuccessUserTokenAuth()
    {
        $user = $this->_setUpUser();
        $this->enableGoogleAuthenticator($user['login'], $this->secret);

        // API authentication
        $rc = $this->authenticate($login = null, $user['tokenAuth'], $this->getValidAuthCode());
        $this->assertUserLogin($rc);
    }

    public function testAuthenticateSuccessUserWithSuperUserAccessByTokenAuth()
    {
        $user = $this->_setUpUser();
        $this->_setUpSuperUserAccessViaDb();

        // API authentication
        $rc = $this->authenticate($login = null, $user['tokenAuth']);
        $this->assertSuperUserLogin($rc, 'user');
    }

    public function testAuthenticateSuccessUserWithSuperUserAccessByTokenAuthAndAuthCode()
    {
        $user = $this->_setUpUser();
        $this->_setUpSuperUserAccessViaDb();
        $this->enableGoogleAuthenticator($user['login'], $this->secret);

        // API authentication
        $rc = $this->authenticate($login = null, $user['tokenAuth'], $this->getValidAuthCode());
        $this->assertSuperUserLogin($rc, 'user');
    }

    public function testAuthenticateSuccessUserLoginAndTokenAuth()
    {
        $user = $this->_setUpUser();

        // valid login & token auth
        $rc = $this->authenticate($user['login'], $user['tokenAuth']);
        $this->assertUserLogin($rc);
    }

    public function testAuthenticateSuccessUserWithSuperUserAccessLoginAndTokenAuth()
    {
        $user = $this->_setUpUser();
        $this->_setUpSuperUserAccessViaDb();

        // valid login & token auth
        $rc = $this->authenticate($user['login'], $user['tokenAuth']);
        $this->assertSuperUserLogin($rc, 'user');
    }

    protected function _setUpUser()
    {
        $user = array(
            'login'            => 'user',
            'password'         => 'geqgeagae',
            'email'            => 'test@test.com',
            'alias'            => 'alias',
            'superuser_access' => 0
        );

        API::getInstance()->addUser($user['login'], $user['password'], $user['email'], $user['alias']);

        $user['tokenAuth'] = API::getInstance()->getTokenAuth($user['login'], md5($user['password']));

        return $user;
    }

    private function _setUpSuperUserAccessViaDb()
    {
        API::getInstance()->setSuperUserAccess('user', true);
    }

    private function authenticate($login, $tokenAuth, $authCode = null)
    {
        $this->auth->setLogin($login);
        $this->auth->setTokenAuth($tokenAuth);
        if ($authCode) {
            $this->auth->setAuthCode($authCode);
            $this->getValidAuthCode();
        }

        return $this->auth->authenticate();
    }

    private function enableGoogleAuthenticator($login, $secret)
    {
        $storage = new Storage($login);
        $storage->activate();
        $storage->setSecret($secret);
    }

    private function getValidAuthCode()
    {
        $ga = new GoogleAuthenticator();
        return $ga->getCode($this->secret);
    }

    private function getInvalidAuthCode()
    {
        return 'InVaLiD';
    }

    private function assertFailedLogin(AuthResult $authResult)
    {
        $this->assertEquals(AuthResult::FAILURE, $authResult->getCode());
    }

    private function assertFailedLoginAuthCodeMissing(AuthResult $authResult)
    {
        $this->assertEquals(Auth::AUTH_CODE_REQUIRED, $authResult->getCode());
    }

    private function assertSuperUserLogin(AuthResult $authResult, $login = 'superUserLogin', $tokenLength = 32)
    {
        $this->assertEquals(AuthResult::SUCCESS_SUPERUSER_AUTH_CODE, $authResult->getCode());
        $this->assertEquals($login, $authResult->getIdentity());
        $this->assertEquals($tokenLength, strlen($authResult->getTokenAuth()));
    }

    private function assertUserLogin(AuthResult $authResult, $login = 'user', $tokenLength = 32)
    {
        $this->assertEquals(AuthResult::SUCCESS, $authResult->getCode());
        $this->assertEquals($login, $authResult->getIdentity());
        $this->assertEquals($tokenLength, strlen($authResult->getTokenAuth()));
    }

    public function provideContainerConfig()
    {
        return array(
            'Piwik\Access' => new FakeAccess()
        );
    }
}
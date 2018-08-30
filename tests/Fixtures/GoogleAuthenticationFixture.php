<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\GoogleAuthenticator\tests\Fixtures;

use Piwik\Tests\Framework\Fixture;

class GoogleAuthenticationFixture extends Fixture
{
    public function setUp()
    {
        parent::setUp();

        self::updateDatabase();

        self::createWebsite("2012-01-01 00:00:00");
    }
}
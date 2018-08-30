<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\GoogleAuthenticator\tests\Mock;

class GoogleAuthenticator extends \Piwik\Plugins\GoogleAuthenticator\PHPGangsta\GoogleAuthenticator
{
    public function createSecret($secretLength = 16)
    {
        return '3UH6WHFP3DWZNYQBGSCO7GU5CRGR74U7';
    }

    public function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null)
    {
        if ($code == '123456' && $secret == $this->createSecret()) {
            return true;
        }

        return false;
    }
}
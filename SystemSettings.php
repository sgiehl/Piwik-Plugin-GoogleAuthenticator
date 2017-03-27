/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\GoogleAuthenticator;

use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

/**
 * Defines Settings for GoogleAuthenticator.
 *
 * Usage like this:
 * $settings = new SystemSettings();
 * $settings->metric->getValue();
 * $settings->description->getValue();
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $globalEnable;

    protected function init()
    {   
        // Set globaleEnable 
        $this->globalEnable = $this->setGlobalEnableSetting();
    }

    private function setGlobalEnableSetting()
    {   
        return $this->makeSetting('globalEnabled', $default = false, FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Global Enable Setting';
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = array(false => 'Disabled', true => 'Enabled');
            $field->description = 'Choose to enable or disable Google Authticator Globally, if enabled users will only be able to log in if they have a Google Two Factor Secret set';
        });
    }

}
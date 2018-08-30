/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

angular.module('piwikApp').controller('GoogleAuthenticatorSettings', function ($scope, $timeout) {

    $scope.gatitle = '';
    $scope.description = '';

    $scope.showQRCode = function() {
        $('.qrcode').attr('src', 'index.php?module=GoogleAuthenticator&action=showQrCode&cb='+piwik.cacheBuster+'&title='+encodeURIComponent($scope.gatitle)+'&descr='+encodeURIComponent($scope.description));
    };

    $scope.$watchGroup(['gatitle', 'description'], function(newValues, oldValues, scope) {
        scope.showQRCode();
    });

    $timeout(function() {
        $scope.showQRCode()
    }, 250);
});
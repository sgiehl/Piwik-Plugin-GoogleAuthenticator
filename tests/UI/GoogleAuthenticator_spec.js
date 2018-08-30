/*!
 * Piwik - free/libre analytics platform
 *
 * Screenshot tests for Google Authenticator plugin
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("GoogleAuthenticator", function () {
    this.timeout(0);

    this.fixture = "Piwik\\Plugins\\GoogleAuthenticator\\tests\\Fixtures\\GoogleAuthenticationFixture";

    before(function () {
        testEnvironment.testUseMockAuth = 0;
        testEnvironment.save();
    });

    after(function () {
        testEnvironment.testUseMockAuth = 1;
        testEnvironment.save();
    });

    it("should login successfully without GA when not activated", function (done) {
        expect.page("").contains(".site-without-data", function (page) {
            page.load("");
            page.sendKeys("#login_form_login", "superUserLogin");
            page.sendKeys("#login_form_password", "superUserPass");
            page.click("#login_form_submit", 1000);
        }, done);
    });

    it('should load start settings page', function (done) {
        expect.screenshot('disabled').to.be.captureSelector('#content', function (page) {
            page.load("?module=GoogleAuthenticator&action=settings&idSite=1&period=day&date=yesterday");
        }, done);
    });

    it('should load setup settings page', function (done) {
        expect.screenshot('setup').to.be.captureSelector('#content', function (page) {
            page.click("#content .btn-lg");
            page.evaluate(function(){
                $('#gatitle').val('Piwik - localhost').trigger('change');
            }, 500);
        }, done);
    });

    it('should update QR code when changing title or description', function (done) {
        expect.screenshot('qr_changed').to.be.captureSelector('#content', function (page) {
            page.evaluate(function(){
                $('#description').val('my nice description').trigger('change');
            }, 500);
        }, done);
    });

    it('should not change any data when posting form with invalid auth code', function (done) {
        expect.screenshot('qr_changed').to.be.captureSelector('#content', function (page) {
            page.evaluate(function(){
                $('input[type=submit]').click();
            }, 1750);
        }, done);
    });

    it('should activate Google authentication if correct auth code is given', function (done) {
        expect.screenshot('activated').to.be.captureSelector('#content', function (page) {
            page.evaluate(function(){
                $('#gaauthcode').val('123456').trigger('change');
                $('input[type=submit]').click();
            }, 2000);
        }, done);
    });

    it("should ask for auth code after login with name/pass", function (done) {
        expect.screenshot('login').to.be.capture(function (page) {
            page.load("?module=GoogleAuthenticator&action=logout");
            page.sendKeys("#login_form_login", "superUserLogin");
            page.sendKeys("#login_form_password", "superUserPass");
            page.click("#login_form_submit", 1000);
        }, done);
    });

    it("should login successfully without token auth", function (done) {
        expect.current_page.contains(".site-without-data", function (page) {
            page.sendKeys("#login_form_authcode", "123456");
            page.click("#login_form_submit", 1000);
        }, done);
    });
});
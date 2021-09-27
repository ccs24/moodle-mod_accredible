<?php
// This file is part of the Accredible Certificate module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for mod/accredible/classes/local/groups.php
 *
 * @package    mod
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_accredible\apiRest\apiRest;
use mod_accredible\client\client;
use mod_accredible\Html2Text\Html2Text;
use mod_accredible\local\credentials;

class mod_accredible_credentials_testcase extends advanced_testcase {
    /**
     * Setup before every test.
     */
    function setUp(): void {
        $this->resetAfterTest();

        // Add plugin settings.
        set_config('accredible_api_key', 'sometestapikey');
        set_config('is_eu', 0);

        // Unset the devlopment environment variable.
        putenv('ACCREDIBLE_DEV_API_ENDPOINT');

        $this->mockapi = new class {
            /**
             * Returns a mock API response based on the fixture json.
             * @param string $jsonpath
             * @return array
             */
            public function resdata($jsonpath) {
                global $CFG;
                $fixturedir = $CFG->dirroot . '/mod/accredible/tests/fixtures/mockapi/v1/';
                $filepath = $fixturedir . $jsonpath;
                return json_decode(file_get_contents($filepath));
            }
        };

        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    function test_create_credential() {
        global $DB;
        /**
         * When the apiRest returns groups.
         */
        $mockclient1 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/create_success.json');

        // The groupid from the mock response.
        $mockgroupid = 9549;

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/credentials';

        $reqdata = json_encode(array(
            "credential" => array(
                "group_id" => $mockgroupid,
                "recipient" => array(
                    "name" => fullname($this->user),
                    "email" => $this->user->email
                ),
                "issued_on" => null,
                "expired_on" => null,
                "custom_attributes" => null
            )
        ));

        $mockclient1->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url), $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return groups.
        $api = new apiRest($mockclient1);
        $localcredentials = new credentials($api);
        $result = $localcredentials->create_credential($this->user, $mockgroupid);
        $this->assertEquals($result, $resdata->credential);

        /**
         * When the apiRest returns an error response.
         */
        $mockclient2 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/credentials';
        $mockclient2->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apiRest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;

        try {
            $localcredentials->create_credential($this->user, $mockgroupid);
        $this->assertEquals($result, $resdata->credential);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    function test_create_credential_legacy() {
        global $DB;
        /**
         * When the apiRest returns groups.
         */
        $mockclient1 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/create_success.json');

        // The groupid from the mock response.
        $mockgroupid = 9549;


        $instanceid = $DB->insert_record('accredible', array("achievementid" => "moodle-course",
            'name' => 'Moodle Course',
                                                             'course' => $this->course->id,
                                                             'finalquiz' => false,
                                                             'passinggrade' => 0,
                                                             'groupid' => $mockgroupid));
        $instance = $DB->get_record('accredible', array('id' => $instanceid), '*', MUST_EXIST);

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/credentials';

        $courselink = new \moodle_url('/course/view.php', array('id' => $this->course->id));
        $courselink = $courselink->__toString();
        $completeddate = date('Y-m-d', (int) time());

        $reqdata = json_encode(array(
            "credential" => array(
                "group_name" => "moodle-course",
                "recipient" => array(
                    "name" => fullname($this->user),
                    "email" => $this->user->email
                ),
                "issued_on" => $completeddate,
                "expired_on" => null,
                "custom_attributes" => null,
                "name" => "",
                "description" => null,
                "course_link" => $courselink
            )
        ));

        $mockclient1->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url), $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return groups.
        $api = new apiRest($mockclient1);
        $localcredentials = new credentials($api);
        $result = $localcredentials->create_credential_legacy($this->user, "moodle-course",
            "", null, $courselink, $completeddate);
        $this->assertEquals($result, $resdata->credential);

        /**
         * When the apiRest returns an error response.
         */
        $mockclient2 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/credentials';
        $mockclient2->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apiRest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;

        try {
            $localcredentials->create_credential_legacy($this->user, "moodle-course",
            "", null, $courselink, $completeddate);
        $this->assertEquals($result, $resdata->credential);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    function test_get_credentials() {
        /**
         * When the apiRest returns groups.
         */
        $mockclient1 = $this->getMockBuilder('client')
                            ->setMethods(['get'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success.json');

        // Expect to call the endpoint once with page and page_size.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=&page_size=50&page=1";
        $mockclient1->expects($this->once())
                    ->method('get')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to return groups.
        $api = new apiRest($mockclient1);
        $localcredentials = new credentials($api);
        $result = $localcredentials->get_credentials(9549);
        $this->assertEquals($result, $resdata->credentials);

        /**
         * When the apiRest returns an error response.
         */
        $mockclient2 = $this->getMockBuilder('client')
                            ->setMethods(['get'])
                            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $mockclient2->expects($this->once())
                    ->method('get')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apiRest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;
        try {
            $localcredentials->get_credentials(9549);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        /**
         * When the apiRest returns no groups.
         */
        $mockclient3 = $this->getMockBuilder('client')
                            ->setMethods(['get'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success_empty.json');

        // Expect to call the endpoint once with page and page_size.
        $mockclient3->expects($this->once())
                    ->method('get')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apiRest($mockclient3);
        $localcredentials = new credentials($api);
        $result = $localcredentials->get_credentials(9549);
        $this->assertEquals($result, array());
    }
}
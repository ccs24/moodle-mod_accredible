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
 * Unit tests for mod/accredible/locallib.php
 *
 * @package    mod
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/accredible/locallib.php');

use mod_accredible\apiRest\apiRest;

class mod_accredible_locallib_testcase extends advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Add plugin settings.
        set_config('accredible_api_key', 'sometestapikey');
        set_config('is_eu', 0);

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
    }

    /**
     * Test whether it returns group name arrays
     */
    public function test_accredible_get_templates() {
        /**
         * When the apiRest returns groups.
         */
        $mockclient1 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/search_success.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 10000));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient1->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return group name arrays.
        $api = new apiRest($mockclient1);
        $result = accredible_get_templates($api);
        $this->assertEquals($result, array(
            'new group1' => 'new group1',
            'new group2' => 'new group2',
        ));

        /**
         * When the apiRest returns an error response.
         */
        $mockclient2 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 10000));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient2->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apiRest($mockclient2);
        $foundexception = false;
        try {
            accredible_get_templates($api);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        /**
         * When the apiRest returns no groups
         */
        $mockclient3 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/search_success_empty.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 10000));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient3->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apiRest($mockclient3);
        $result = accredible_get_templates($api);
        $this->assertEquals($result, array());
    }
}

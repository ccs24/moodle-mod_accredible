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
use mod_accredible\local\groups;

class mod_accredible_groups_testcase extends advanced_testcase {
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
    }

    /**
     * Test whether it returns groups
     */
    public function test_get_groups() {
        /**
         * When the apiRest returns groups.
         */
        $mockclient1 = $this->getMockBuilder('client')
                            ->setMethods(['get'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/all_groups_success.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=10000&page=1';
        $mockclient1->expects($this->once())
                    ->method('get')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to return groups.
        $api = new apiRest($mockclient1);
        $localgroups = new groups($api);
        $result = $localgroups->get_groups();
        $this->assertEquals($result, array(
            '12472' => 'new group1',
            '12473' => 'new group2',
        ));

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
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=10000&page=1';
        $mockclient2->expects($this->once())
                    ->method('get')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apiRest($mockclient2);
        $localgroups = new groups($api);
        $foundexception = false;
        try {
            $localgroups->get_groups();
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
        $resdata = $this->mockapi->resdata('groups/all_groups_success_empty.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=10000&page=1';
        $mockclient3->expects($this->once())
                    ->method('get')
                    ->with($this->equalTo($url))
                    ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apiRest($mockclient3);
        $localgroups = new groups($api);
        $result = $localgroups->get_groups();
        $this->assertEquals($result, array());
    }

    /**
     * Test whether it returns group name arrays
     */
    public function test_get_templates() {
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
        $localgroups = new groups($api);
        $result = $localgroups->get_templates();
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
        $localgroups = new groups($api);
        $foundexception = false;
        try {
            $localgroups->get_templates();
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        /**
         * When the apiRest returns no groups.
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
        $localgroups = new groups($api);
        $result = $localgroups->get_templates();
        $this->assertEquals($result, array());
    }


    /**
     * Test whether it cretes or updates a group and returns the groupid.
     */
    public function test_sync_group_with() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $courselink = new \moodle_url('/course/view.php', array('id' => $course->id));

        // The groupid from the mock response.
        $mockgroupid = 12472;
        $instanceid = $DB->insert_record('accredible', array('name' => 'Moodle Course',
                                                             'course' => $course->id,
                                                             'finalquiz' => false,
                                                             'passinggrade' => 0,
                                                             'groupid' => $mockgroupid));

        /**
         * When instanceid is passed and the apiRest responds successfully.
         */
        $mockclient1 = $this->getMockBuilder('client')
                            ->setMethods(['put'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/update_success.json');

        $reqdata = json_encode(
            array(
                'group' => array('course_link' => $courselink)
            )
        );

        // Expect to call the update endpoint once with the groupid.
        $url = "https://api.accredible.com/v1/issuer/groups/{$mockgroupid}";
        $mockclient1->expects($this->once())
                    ->method('put')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return the groupid.
        $api = new apiRest($mockclient1);
        $localgroups = new groups($api);
        $result = $localgroups->sync_group_with($course, $instanceid);
        $this->assertEquals($result, $mockgroupid);

        /**
         * When instanceid and groupid are passed and the apiRest responds successfully.
         */
        $groupid = 1;
        $mockclient2 = $this->getMockBuilder('client')
                            ->setMethods(['put'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/update_success.json');

        $reqdata = json_encode(
            array(
                'group' => array('course_link' => $courselink)
            )
        );

        // Expect to call the update endpoint once with the groupid.
        $url = "https://api.accredible.com/v1/issuer/groups/{$groupid}";
        $mockclient2->expects($this->once())
                    ->method('put')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return the groupid.
        $api = new apiRest($mockclient2);
        $localgroups = new groups($api);
        $result = $localgroups->sync_group_with($course, $instanceid, $groupid);
        $this->assertEquals($result, $mockgroupid);

        /**
         * When instanceid is passed and the apiRest returns an error.
         */
        $mockclient3 = $this->getMockBuilder('client')
                            ->setMethods(['put'])
                            ->getMock();

        // Mock API response data.
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $reqdata = json_encode(
            array(
                'group' => array('course_link' => $courselink)
            )
        );

        // Expect to call the update endpoint once with the groupid.
        $url = "https://api.accredible.com/v1/issuer/groups/{$mockgroupid}";
        $mockclient3->expects($this->once())
                    ->method('put')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to raise an error.
        $api = new apiRest($mockclient3);
        $localgroups = new groups($api);
        $foundexception = false;
        try {
            $localgroups->sync_group_with($course, $instanceid);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        /**
         * When instanceid is NOT passed and the apiRest responds successfully.
         */
        $mockclient4 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/create_success.json');

        $reqdata = json_encode(
            array(
                'group' => array(
                    "name" => $course->shortname . date("Ymd") . $course->id,
                    "course_name" => $course->fullname,
                    "course_description" => Html2Text::convert($course->summary),
                    'course_link' => $courselink
                )
            )
        );

        // Expect to call the create endpoint once.
        $url = "https://api.accredible.com/v1/issuer/groups";
        $mockclient4->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return the groupid.
        $api = new apiRest($mockclient4);
        $localgroups = new groups($api);
        $result = $localgroups->sync_group_with($course);
        $this->assertEquals($result, $mockgroupid);

        /**
         * When instanceid is NOT passed, the course does not have a summary, and the apiRest responds successfully.
         */
        // A course without summary
        $course2 = $this->getDataGenerator()->create_course(array('summary' => ''));
        $courselink2 = new \moodle_url('/course/view.php', array('id' => $course2->id));

        $mockclient5 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/create_success.json');

        $reqdata = json_encode(
            array(
                'group' => array(
                    "name" => $course2->shortname . date("Ymd") . $course2->id,
                    "course_name" => $course2->fullname,
                    "course_description" => "Recipient has compeleted the achievement.",
                    'course_link' => $courselink2
                )
            )
        );

        // Expect to call the create endpoint once.
        $url = "https://api.accredible.com/v1/issuer/groups";
        $mockclient5->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to return the groupid.
        $api = new apiRest($mockclient5);
        $localgroups = new groups($api);
        $result = $localgroups->sync_group_with($course2);
        $this->assertEquals($result, $mockgroupid);

        /**
         * When instanceid is NOT passed and the apiRest returns an error.
         */
        $mockclient6 = $this->getMockBuilder('client')
                            ->setMethods(['post'])
                            ->getMock();

        // Mock API response data.
        $mockclient6->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $reqdata = json_encode(
            array(
                'group' => array(
                    "name" => $course->shortname . date("Ymd") . $course->id,
                    "course_name" => $course->fullname,
                    "course_description" => Html2Text::convert($course->summary),
                    'course_link' => $courselink
                )
            )
        );

        // Expect to call the create endpoint once.
        $url = "https://api.accredible.com/v1/issuer/groups";
        $mockclient6->expects($this->once())
                    ->method('post')
                    ->with($this->equalTo($url),
                           $this->equalTo($reqdata),)
                    ->willReturn($resdata);

        // Expect to raise an error.
        $api = new apiRest($mockclient6);
        $localgroups = new groups($api);
        $foundexception = false;
        try {
            $localgroups->sync_group_with($course);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }
}

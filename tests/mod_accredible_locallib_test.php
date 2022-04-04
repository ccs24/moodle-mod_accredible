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

namespace mod_accredible;
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/accredible/locallib.php');

use mod_accredible\apirest\apirest;

/**
 * Unit tests for mod/accredible/locallib.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_locallib_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();

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
     * Get transcript test
     */
    public function test_accredible_get_transcript() {
        global $DB;

        // When no quiz available for user.
        $result = accredible_get_transcript($this->course->id, $this->user->id, 0);

        // It responds with false.
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertEquals($result, false);

        // When user has completed multiple quizzes and has a passing grade.
        $quiz1 = $this->create_quiz_module($this->course->id);
        $quiz2 = $this->create_quiz_module($this->course->id);
        $quiz3 = $this->create_quiz_module($this->course->id);

        $this->create_quiz_grades($quiz1->id, $this->user->id, 10);
        $this->create_quiz_grades($quiz2->id, $this->user->id, 5);
        $this->create_quiz_grades($quiz3->id, $this->user->id, 5);

        $result = accredible_get_transcript($this->course->id, $this->user->id, 0);

        $transcriptitems = [["category" => $quiz1->name, "percent" => 100],
            ["category" => $quiz2->name, "percent" => 50],
            ["category" => $quiz3->name, "percent" => 50]];

        $resdata = array(
            "description" => "Course Transcript",
            "string_object" => json_encode($transcriptitems),
            "category" => "transcript",
            "custom" => true,
            "hidden" => true
        );

        // It responds with transcriptitems.
        $this->assertEquals($result, $resdata);

        // Reset DB.
        $this->setUp();

        // When user has completed multiple quizzes and has a passing grade.
        // The final_quiz_id is one of the valid quizzes and so it will be excluded from the transcripts.
        $quiz1 = $this->create_quiz_module($this->course->id);
        $quiz2 = $this->create_quiz_module($this->course->id);
        $quiz3 = $this->create_quiz_module($this->course->id);

        $this->create_quiz_grades($quiz1->id, $this->user->id, 10);
        $this->create_quiz_grades($quiz2->id, $this->user->id, 5);
        $this->create_quiz_grades($quiz3->id, $this->user->id, 5);

        $result = accredible_get_transcript($this->course->id, $this->user->id, $quiz2->id);

        $transcriptitems = [["category" => $quiz1->name, "percent" => 100],
            ["category" => $quiz3->name, "percent" => 50]];

        $resdata = array(
            "description" => "Course Transcript",
            "string_object" => json_encode($transcriptitems),
            "category" => "transcript",
            "custom" => true,
            "hidden" => true
        );

        // It responds with transcriptitems.
        // Final_quiz_id is excluded from the transcripts returned.
        $this->assertEquals($result, $resdata);

        // Reset DB.
        $this->setUp();

        // When user has completed multiple quizzes and has a failing grade.
        $quiz1 = $this->create_quiz_module($this->course->id);
        $quiz2 = $this->create_quiz_module($this->course->id);
        $quiz3 = $this->create_quiz_module($this->course->id);

        $this->create_quiz_grades($quiz1->id, $this->user->id, 0);
        $this->create_quiz_grades($quiz2->id, $this->user->id, 5);
        $this->create_quiz_grades($quiz3->id, $this->user->id, 5);

        $result = accredible_get_transcript($this->course->id, $this->user->id, 0);

        // It responds with false.
        $this->assertEquals($result, false);
    }

    /**
     * Create quiz module test
     *
     * @param int $courseid
     */
    private function create_quiz_module($courseid) {
        $quiz = array("course" => $courseid, "grade" => 10);
        return $this->getDataGenerator()->create_module('quiz', $quiz);
    }

    /**
     * Create quiz grades test
     *
     * @param int $quizid
     * @param int $userid
     * @param int $grade
     */
    private function create_quiz_grades($quizid, $userid, $grade) {
        global $DB;
        $quizgrade = array("quiz" => $quizid, "userid" => $userid, "grade" => $grade);
        $DB->insert_record('quiz_grades', $quizgrade);
    }
}

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

class mod_accredible_locallib_testcase extends advanced_testcase {
    function setUp(): void {
        $this->resetAfterTest();
        global $DB;
        $this->realDB = $DB;

        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    public function test_accredible_get_transcript() {
        global $DB;

        /**
        * When no quiz available for user.
        */
        $DB = $this->createMockDB();
        $DB->expects($this->once())
                    ->method("get_records_select")
                    ->with($this->equalTo("quiz", "course = :course_id", array("course_id" => $this->course->id)))
                    ->willReturn([]);

        $result = accredible_get_transcript($this->course->id, $this->user->id, 5);

        /**
        * it responds with false
        */
        $this->assertEquals($result, false);

        /**
         * When user has taken multiple quiz.
         */
        $quiz1 = array("id" => 1, "course" => $this->course->id, "name" => "test-quiz1", "grade" => 10, "highest_grade" => 10);
        $quiz2 = array("id" => 2, "course" => $this->course->id, "name" => "test-quiz2", "grade" => 10, "highest_grade" => 5);
        $quiz3 = array("id" => 3, "course" => $this->course->id, "name" => "test-quiz3", "grade" => 10, "highest_grade" => 5);

        $quizzes_array = [$quiz1, $quiz2, $quiz3];

        $quizzes = array_map(function($element) {
            return (object) $element;
        }, $quizzes_array);

        /**
        * When user has completed multiple quizzes and has a passing score.
        */
        $DB = $this->createMockDB();
        $DB->expects($this->exactly(3))
                    ->method("get_field")
                    ->withConsecutive(array("quiz_grades", "grade",
                        array("quiz" => $quiz1["id"], "userid" => $this->user->id)),
                    array("quiz_grades", "grade",
                        array("quiz" => $quiz2["id"], "userid" => $this->user->id)),
                    array("quiz_grades", "grade",
                        array("quiz" => $quiz3["id"], "userid" => $this->user->id)))
                    ->willReturn($this->onConsecutiveCalls(10, 5, 5));

        $DB->expects($this->once())
                    ->method("get_records_select")
                    ->with($this->equalTo("quiz", "course = :course_id", array("course_id" => $this->course->id)))
                    ->willReturn($quizzes);

        $result = accredible_get_transcript($this->course->id, $this->user->id, 5);

        $transcript_items = array();

        foreach ($quizzes as $quiz) {
            array_push($transcript_items, array(
                "category" => $quiz->name,
                "percent" => min( ( $quiz->highest_grade / $quiz->grade ) * 100, 100 )
            ));
        }

        $res_data = array(
            "description" => "Course Transcript",
            "string_object" => json_encode($transcript_items),
            "category" => "transcript",
            "custom" => true,
            "hidden" => true
        );

        /**
        * it responds with transcript_items
        */
        $this->assertEquals($result, $res_data);

        /**
        * When user has completed multiple quizzes and has a failing score.
        */
        $DB = $this->createMockDB();
        $DB->expects($this->exactly(3))
                    ->method("get_field")
                    ->withConsecutive(array("quiz_grades", "grade",
                        array("quiz" => $quiz1["id"], "userid" => $this->user->id)),
                    array("quiz_grades", "grade",
                        array("quiz" => $quiz2["id"], "userid" => $this->user->id)),
                    array("quiz_grades", "grade",
                        array("quiz" => $quiz3["id"], "userid" => $this->user->id)))
                    ->willReturn($this->onConsecutiveCalls(0, 5, 5));

        $DB->expects($this->once())
                    ->method("get_records_select")
                    ->with($this->equalTo("quiz", "course = :course_id", array("course_id" => $this->course->id)))
                    ->willReturn($quizzes);

        $result = accredible_get_transcript($this->course->id, $this->user->id, 5);

        /**
        * it responds with false
        */
        $this->assertEquals($result, false);
    }

    private function createMockDB()
    {
        return $this->getMockBuilder(get_class($this->realDB))
                    ->setMethods(["get_records_select", "get_field"])
                    ->getMock();
    }
}

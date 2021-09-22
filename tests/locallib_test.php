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
        $result = accredible_get_transcript($this->course->id, $this->user->id, 5);

        /**
        * it responds with false
        */
        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertEquals($result, false);

        /**
         * When user has taken multiple quiz.
         */
        $quiz1 = $this->createQuizModule();
        $quiz2 = $this->createQuizModule();
        $quiz3 = $this->createQuizModule();

        /**
        * When user has completed multiple quizzes and has a passing grade.
        */
        $this->createQuizGrades($quiz1, 10);
        $this->createQuizGrades($quiz2, 5);
        $this->createQuizGrades($quiz3, 5);

        $result = accredible_get_transcript($this->course->id, $this->user->id, 0);

        $transcript_items = [["category" => $quiz1->name, "percent" => 100],
            ["category" => $quiz2->name, "percent" => 50],
            ["category" => $quiz3->name, "percent" => 50]];

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

        //reset DB
        $this->setUp();

        /**
         * When user has taken multiple quiz.
         */
        $quiz1 = $this->createQuizModule();
        $quiz2 = $this->createQuizModule();
        $quiz3 = $this->createQuizModule();

        /**
        * When user has completed multiple quizzes and has a failing grade.
        */
        $this->createQuizGrades($quiz1, 0);
        $this->createQuizGrades($quiz2, 5);
        $this->createQuizGrades($quiz3, 5);

        $result = accredible_get_transcript($this->course->id, $this->user->id, 0);

        /**
        * it responds with false
        */
        $this->assertEquals($result, false);
    }

    private function createQuizModule() {
        $quiz = array("course" => $this->course->id, "grade" => 10);
        return $this->getDataGenerator()->create_module('quiz', $quiz);
    }

    private function createQuizGrades($quiz, $grade) {
        global $DB;
        $quiz_grade = array("quiz" => $quiz->id, "userid" => $this->user->id, "grade" => $grade);
        $DB->insert_record('quiz_grades', $quiz_grade);
    }
}

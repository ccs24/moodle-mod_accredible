<?php
// This file is part of Moodle - http://moodle.org/
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
 * Defines all the restore steps that will be used
 *
 * @package    mod_accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/accredible/backup/moodle2/restore_accredible_stepslib.php'); // Because it exists (must).

/**
 * Accredible restore task that provides all the settings and steps to perform one complete restore of the activity.
 *
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_accredible_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Accredible only has one structure step.
        $this->add_step(new restore_accredible_activity_structure_step('accredible_structure', 'accredible.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('accredible', array('id'), 'accredible');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('ACCREDIBLEVIEWBYID', '/mod/accredible/view.php?id=$1', 'course_module');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * when restoring accredible logs. It must return one array
     * of objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('accredible', 'view', 'view.php?id={course_module}', '{accredible}');
        $rules[] = new restore_log_rule('accredible', 'add', 'view.php?id={course_module}', '{accredible}');
        $rules[] = new restore_log_rule('accredible', 'update', 'view.php?id={course_module}', '{accredible}');
        $rules[] = new restore_log_rule('accredible', 'received', 'view.php?id={course_module}', '{accredible}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the when restoring course logs. It must return one array
     * of objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('accredible', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}

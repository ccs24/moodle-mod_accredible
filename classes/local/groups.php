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
 * Local functions related to groups/courses.
 *
 * @package    mod
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_accredible\local;
defined('MOODLE_INTERNAL') || die();

use mod_accredible\apiRest\apiRest;
use mod_accredible\Html2Text\Html2Text;

class groups {
    /**
     * The apiRest object used to call API requests.
     * @var apiRest
     */
    private $apiRest;

    /**
     * A random value for a new group name.
     * @var int
     */
    private $rand;

    public function __construct($apiRest = null, $rand = null) {
        // An apiRest with a mock client is passed when unit testing.
        if ($apiRest) {
            $this->apiRest = $apiRest;
        } else {
            $this->apiRest = new apiRest();
        }

        // A fixed value is passed when unit testing.
        if ($rand) {
            $this->rand = $rand;
        } else {
            $this->rand = mt_rand();
        }
    }

    /**
     * Get the groups for the issuer
     * @return array[stdClass] $groups
     */
    public function get_groups() {
        $response = $this->apiRest->get_groups(10000, 1);
        if (!isset($response->groups)) {
            throw new \moodle_exception('getgroupserror', 'accredible', 'https://help.accredible.com/hc/en-us');
        }

        $groups = array();
        foreach ($response->groups as $group) {
            $groups[$group->id] = $group->name;
        }
        return $groups;
    }

    /**
     * List all of the issuer's templates
     *
     * @return array[stdClass] $templates
     */
    public function get_templates() {
        $response = $this->apiRest->search_groups(10000, 1);
        if (!isset($response->groups)) {
            throw new \moodle_exception('gettemplateserror', 'accredible', 'https://help.accredible.com/hc/en-us');
        }

        $groups = $response->groups;
        $templates = array();
        foreach ($groups as $group) {
            $templates[$group->name] = $group->name;
        }
        return $templates;
    }

    /**
     * Sync the selected course information with a group on Accredible - returns a group ID.
     * Optionally takes a group ID so we can set it and change the assigned group.
     *
     * @param stdClass $course
     * @param int|null $instanceid
     * @return int $groupid
     */
    public function sync_group_with($course, $instanceid = null, $groupid = null) {
        global $DB;

        $courselink = new \moodle_url('/course/view.php', array('id' => $course->id));

        try {
            if ($instanceid != null) {
                // Update the group.
                $accredible = $DB->get_record('accredible', array('id' => $instanceid), '*', MUST_EXIST);
                // Get the group id.
                if (!isset($groupid)) {
                    $groupid = $accredible->groupid;
                }
                $res = $this->apiRest->update_group($groupid, null, null, null, $courselink);
            } else {
                // Create a new Group on Accredible.
                $description = Html2Text::convert($course->summary);
                if (empty($description)) {
                    $description = "Recipient has compeleted the achievement.";
                }
                // Add a random number to deal with duplicate course names.
                $name = $course->shortname . $this->rand;
                $res = $this->apiRest->create_group($name, $course->fullname, $description, $courselink);
            }
            return $res->group->id;
        } catch (\Exception $e) {
            throw new \moodle_exception('syncgroupwitherror',
                                        'accredible',
                                        'https://help.accredible.com/hc/en-us',
                                        $course->id,
                                        $course->id);
        }
    }
}

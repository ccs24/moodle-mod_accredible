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

class groups {
    /**
     * The apiRest object used to call API requests.
     * @var apiRest
     */
    private $apiRest;

    public function __construct($apiRest = null) {
        // An apiRest with a mock client is passed when unit testing.
        if($apiRest) {
            $this->apiRest = $apiRest;
        } else {
            $this->apiRest = new apiRest();
        }
    }

    /**
     * List all of the issuer's templates
     *
     * @return array[stdClass] $templates
     */
    function get_templates() {
        $response = $this->apiRest->search_groups(10000, 1);
        if (!isset($response->groups)) {
            // Throw API exception.
            // Include the achievement id that triggered the error.
            // Direct the user to accredible's support.
            // Dump the achievement id to debug_info.
            throw new \moodle_exception('gettemplateserror', 'accredible', 'https://help.accredible.com/hc/en-us');
        }

        $groups = $response->groups;
        $templates = array();
        foreach ($groups as $group) { $templates[$group->name] = $group->name; }
        return $templates;
    }
}

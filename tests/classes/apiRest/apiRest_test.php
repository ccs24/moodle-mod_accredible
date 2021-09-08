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
 * Unit tests for mod/accredible/classes/apiRest/apiRest.php
 *
 * @package    mod
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_accredible\apiRest\apiRest;

class mod_accredible_apiRest_testcase extends advanced_testcase {
    /**
     * Tests that the default endpoint is used when is_eu is NOT enabled.
     */
    public function test_default_endpoint() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Unset the devlopment environment variable.
        $dev_api_endpoint = getenv("ACCREDIBLE_DEV_API_ENDPOINT");
        putenv('ACCREDIBLE_DEV_API_ENDPOINT');

        set_config("is_eu", "0", "accredible");
        $rest = new apiRest();
        $this->assertEquals($rest->api_endpoint, "https://api.accredible.com/v1/");

        // Reset the devlopment environment variable.
        putenv("ACCREDIBLE_DEV_API_ENDPOINT={$dev_api_endpoint}");
    }

    /**
     * Tests that the EU endpoint is used when is_eu is enabled.
     */
    public function test_eu_endpoint() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Unset the devlopment environment variable.
        $dev_api_endpoint = getenv("ACCREDIBLE_DEV_API_ENDPOINT");
        putenv("ACCREDIBLE_DEV_API_ENDPOINT");

        set_config("is_eu", "1", "accredible");
        $rest = new apiRest();
        $this->assertEquals($rest->api_endpoint, "https://api.accredible.com/v1/");

        // Reset the devlopment environment variable.
        putenv("ACCREDIBLE_DEV_API_ENDPOINT={$dev_api_endpoint}");
    }
}

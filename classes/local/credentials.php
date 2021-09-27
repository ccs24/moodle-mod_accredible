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

namespace mod_accredible\local;
defined('MOODLE_INTERNAL') || die();

use mod_accredible\apiRest\apiRest;

class credentials {
	/**
     * HTTP request apirest.
     * @var apirest
     */
    private $apirest;

    public function __construct($apirest = null) {
        // A mock apirest is passed when unit testing.
        if($apirest) {
            $this->apirest = $apirest;
        } else {
            $this->apirest = new apiRest();
        }
    }

    /**
	 * Create a credential given a user and an existing group
	 * @param stdObject $user
	 * @param int $group_id
	 * @return stdObject
	 */
	function create_credential($user, $group_id, $event = null, $issued_on = null) {
	    global $CFG;

	    try {
	        $credential = $this->apirest->create_credential(fullname($user), $user->email, $group_id, $issued_on);

	        // Log an event now we've created the credential if possible.
	        if ($event != null) {
	            $certificate_event = \mod_accredible\event\certificate_created::create(array(
	                                  'objectid' => $credential->credential->id,
	                                  'context' => context_module::instance($event->contextinstanceid),
	                                  'relateduserid' => $event->relateduserid,
	                                  'issued_on' => $issued_on
	                                ));
	            $certificate_event->trigger();
	        }

	        return $credential->credential;

	    } catch (\Exception $e) {
	        // Throw API exception.
	        // Include the achievement id that triggered the error.
	        // Direct the user to accredible's support.
	        // Dump the achievement id to debug_info.
	        throw new \moodle_exception('credentialcreateerror', 'accredible', 'https://help.accredible.com/hc/en-us', $user->email, $group_id);
	    }
	}

	/**
	 * Create a credential given a user and an existing group
	 * @param stdObject $user
	 * @param int $group_id
	 * @return stdObject
	 */
	function create_credential_legacy($user, $achievement_name, $course_name, $course_description, $course_link, $issued_on, $event = null){
	    global $CFG;

	    try {
	        $credential = $this->apirest->create_credential_legacy(fullname($user), $user->email, $achievement_name, $issued_on, null, $course_name, $course_description, $course_link);
	        // log an event now we've created the credential if possible
	        if ($event != null) {
	            $certificate_event = \mod_accredible\event\certificate_created::create(array(
	                                  'objectid' => $credential->credential->id,
	                                  'context' => context_module::instance($event->contextinstanceid),
	                                  'relateduserid' => $event->relateduserid
	                                ));
	            $certificate_event->trigger();
	        }

	        return $credential->credential;

	    } catch (\Exception $e) {
	        // Throw API exception.
	        // Include the achievement id that triggered the error.
	        // Direct the user to accredible's support.
	        // Dump the achievement id to debug_info.
	        throw new \moodle_exception('credentialcreateerror', 'accredible', 'https://help.accredible.com/hc/en-us', $user->email, $achievement_name);
	    }
	}

	/**
	 * List all of the certificates with a specific achievement id
	 *
	 * @param string $group_id Limit the returned Credentials to a specific group ID.
	 * @param string|null $email Limit the returned Credentials to a specific recipient's email address.
	 * @return array[stdClass] $credentials
	 */
	function get_credentials($group_id, $email= null) {
	    global $CFG;

	    $page_size = 50;
	    $page = 1;
	    // Maximum number of pages to request to avoid possible infinite loop.
	    $loop_limit = 100;

	    try {
	        $loop = true;
	        $count = 0;
	        $credentials = array();
	        // Query the Accredible API and loop until it returns that there is no next page.
	        while ($loop === true) {
	            $credentials_page = $this->apirest->get_credentials($group_id, $email, $page_size, $page);

	            foreach ($credentials_page->credentials as $credential) {
	                $credentials[] = $credential;
	            }

	            $page++;
	            $count++;

	            if ($credentials_page->meta->next_page === null || $count >= $loop_limit) {
	                // If the Accredible API returns that there
	                // is no next page, end the loop.
	                $loop = false;
	            }
	        }
	        return $credentials;
	    } catch (\Exception $e) {
	        // Throw API exception.
	        // Include the achievement id that triggered the error.
	        // Direct the user to accredible's support.
	        // Dump the achievement id to debug_info.
	        $exceptionparam = new stdClass();
	        $exceptionparam->group_id = $group_id;
	        $exceptionparam->email = $email;
	        $exceptionparam->last_response = $credentials_page;
	        throw new moodle_exception('getcredentialserror', 'accredible', 'https://help.accredible.com/hc/en-us', $exceptionparam);
	    }
	}

	/**
	 * Check's if a credential exists for an email in a particular group
	 * @param int $group_id
	 * @param String $email
	 * @return array[stdClass] || false
	 */

	function check_for_existing_credential($group_id, $email) {
	    global $CFG;
	    try {
	        $credentials = $this->apirest->get_credentials($group_id, $email);

	        if ($credentials->credentials and $credentials->credentials[0]) {
	            return $credentials->credentials[0];
	        } else {
	            return false;
	        }
	    } catch (\Exception $e) {
	        // throw API exception
	          // include the achievement id that triggered the error
	          // direct the user to accredible's support
	          // dump the achievement id to debug_info
	          throw new moodle_exception('groupsyncerror', 'accredible', 'https://help.accredible.com/hc/en-us', $group_id, $group_id);
	    }
	}

	function check_for_existing_certificate($achievement_id, $user) {
	    global $DB;
	    $existing_certificate = false;
	    $certificates = $this->get_credentials($achievement_id, $user->email);

	    foreach ($certificates as $certificate) {
	        if ($certificate->recipient->email == $user->email) {
	            $existing_certificate = $certificate;
	        }
	    }
	    return $existing_certificate;
	}
}
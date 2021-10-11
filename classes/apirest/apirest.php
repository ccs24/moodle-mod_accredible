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

namespace mod_accredible\apirest;
defined('MOODLE_INTERNAL') || die();

use mod_accredible\client\client;

class apirest {
    /**
     * API base URL.
     * Use `public` to make unit testing possible.
     * @var string
     */
    public $apiendpoint;

    /**
     * HTTP request client.
     * @var client
     */
    private $client;

    public function __construct($client = null) {
        global $CFG;

        $this->apiendpoint = 'https://api.accredible.com/v1/';

        if ($CFG->is_eu) {
            $this->apiendpoint = 'https://eu.api.accredible.com/v1/';
        }

        $devapiendpoint = getenv('ACCREDIBLE_DEV_API_ENDPOINT');
        if ($devapiendpoint) {
            $this->apiendpoint = $devapiendpoint;
        }

        // A mock client is passed when unit testing.
        if ($client) {
            $this->client = $client;
        } else {
            $this->client = new client();
        }
    }

    /**
     * Get Credentials
     * @param String|null $groupid
     * @param String|null $email
     * @param String|null $page_size
     * @param String $page
     * @return stdObject
     */
    public function get_credentials($groupid = null, $email = null, $pagesize = null, $page = 1) {
        return $this->client->get("{$this->apiendpoint}all_credentials?group_id={$groupid}&email=" .
            rawurlencode($email) . "&page_size={$pagesize}&page={$page}");
    }

    /**
     * Get a Credential with EnvidenceItems
     * @param Integer $credentialid
     * @return stdObject
     */
    public function get_credential($credentialid) {
        return $this->client->get("{$this->apiendpoint}credentials/{$credentialid}");
    }

    /**
     * Generaate a Single Sign On Link for a recipient for a particular credential.
     * @return stdObject
     */
    public function recipient_sso_link($credentialid = null, $recipientid = null,
        $recipientemail = null, $walletview = null, $groupid = null, $redirectto = null) {

        $data = array(
            "credential_id" => $credentialid,
            "recipient_id" => $recipientid,
            "recipient_email" => $recipientemail,
            "wallet_view" => $walletview,
            "group_id" => $groupid,
            "redirect_to" => $redirectto,
        );

        $data = $this->strip_empty_keys($data);

        $data = json_encode($data);

        return $this->client->post("{$this->apiendpoint}sso/generate_link", $data);
    }

    /**
     * Update a Group
     * @param String $id
     * @param String|null $name
     * @param String|null $coursename
     * @param String|null $coursedescription
     * @param String|null $courselink
     * @return stdObject
     */
    public function update_group($id, $name = null, $coursename = null,
        $coursedescription = null, $courselink = null, $designid = null) {

        $data = array(
            "group" => array(
                "name" => $name,
                "course_name" => $coursename,
                "course_description" => $coursedescription,
                "course_link" => $courselink,
                "design_id" => $designid
            )
        );

        $data = $this->strip_empty_keys($data);

        $data = json_encode($data);

        return $this->client->put("{$this->apiendpoint}issuer/groups/{$id}", $data);
    }

    /**
     * Create a new Group
     * @param String $name
     * @param String $coursename
     * @param String $course_description
     * @param String|null $courselink
     * @return stdObject
     */
    public function create_group($name, $coursename, $coursedescription, $courselink = null) {

        $data = array(
            "group" => array(
                "name" => $name,
                "course_name" => $coursename,
                "course_description" => $coursedescription,
                "course_link" => $courselink
            )
        );

        $data = json_encode($data);

        return $this->client->post("{$this->apiendpoint}issuer/groups", $data);
    }

    /**
     * Creates a Credential given an existing Group
     * @param String $recipientname
     * @param String $recipientemail
     * @param String $courseid
     * @param Date|null $issuedon
     * @param Date|null $expiredon
     * @param stdObject|null $custom_attributes
     * @return stdObject
     */
    public function create_credential($recipientname, $recipientemail, $courseid,
        $issuedon = null, $expiredon = null, $customattributes = null) {

        $data = array(
            "credential" => array(
                "group_id" => $courseid,
                "recipient" => array(
                    "name" => $recipientname,
                    "email" => $recipientemail
                ),
                "issued_on" => $issuedon,
                "expired_on" => $expiredon,
                "custom_attributes" => $customattributes
            )
        );

        $data = json_encode($data);

        return $this->client->post("{$this->apiendpoint}credentials", $data);
    }

    /**
     * Creates an evidence item on a given credential. This is a general method used by more specific evidence item creations.
     * @param stdObject $evidenceitem
     * @return stdObject
     */
    public function create_evidence_item($evidenceitem, $credentialid, $throwerror = false) {
        $data = json_encode($evidenceitem);
        $result = $this->client->post("{$this->apiendpoint}credentials/{$credentialid}/evidence_items", $data);
        if ($throwerror && $this->client->error) {
            throw new \moodle_exception(
                'evidenceadderror', 'accredible', 'https://help.accredible.com/hc/en-us', $credentialid, $this->client->error
            );
        }
        return $result;
    }

    /**
     * Creates a Grade evidence item on a given credential.
     * @param String $startdate
     * @param String $enddate
     * @return stdObject
     */
    public function create_evidence_item_duration($startdate, $enddate, $credentialid, $hidden = false) {

        $durationinfo = array(
            'start_date' => date("Y-m-d", strtotime($startdate)),
            'end_date' => date("Y-m-d", strtotime($enddate)),
            'duration_in_days' => floor( (strtotime($enddate) - strtotime($startdate)) / 86400)
        );

        // Multi day duration.
        if ($durationinfo['duration_in_days'] && $durationinfo['duration_in_days'] != 0) {

            $evidenceitem = array(
                "evidence_item" => array(
                    "description" => 'Completed in ' . $durationinfo['duration_in_days'] . ' days',
                    "category" => "course_duration",
                    "string_object" => json_encode($durationinfo),
                    "hidden" => $hidden
                )
            );

            $result = $this->create_evidence_item($evidenceitem, $credentialid);

            return $result;
            // It may be completed in one day.
        } else if ($durationinfo['start_date'] != $durationinfo['end_date']) {
            $durationinfo['duration_in_days'] = 1;

            $evidenceitem = array(
                "evidence_item" => array(
                    "description" => 'Completed in 1 day',
                    "category" => "course_duration",
                    "string_object" => json_encode($durationinfo),
                    "hidden" => $hidden
                )
            );

            $result = $this->create_evidence_item($evidenceitem, $credentialid);

            return $result;

        } else {
            throw new \InvalidArgumentException("Enrollment duration must be greater than 0.");
        }
    }

    /**
     * Creates a Credential given an existing Group. This legacy method uses achievement names rather than group IDs.
     * @param String $recipientname
     * @param String $recipientemail
     * @param String $achievementname
     * @param Date|null $issuedon
     * @param Date|null $expiredon
     * @param stdObject|null $customattributes
     * @return stdObject
     */
    public function create_credential_legacy($recipientname, $recipientemail,
        $achievementname, $issuedon = null, $expiredon = null, $coursename = null,
        $coursedescription = null, $courselink = null, $customattributes = null) {

        $data = array(
            "credential" => array(
                "group_name" => $achievementname,
                "recipient" => array(
                    "name" => $recipientname,
                    "email" => $recipientemail
                ),
                "issued_on" => $issuedon,
                "expired_on" => $expiredon,
                "custom_attributes" => $customattributes,
                "name" => $coursename,
                "description" => $coursedescription,
                "course_link" => $courselink
            )
        );

        $data = json_encode($data);

        return $this->client->post("{$this->apiendpoint}credentials", $data);
    }

    /**
     * Get all Groups
     * @param String $pagesize
     * @param String $page
     * @return stdObject
     */
    public function get_groups($pagesize = 50, $page = 1) {
        return $this->client->get($this->apiendpoint.'issuer/all_groups?page_size=' . $pagesize . '&page=' . $page);
    }

    /**
     * Get all Groups
     * @param Integer $pagesize
     * @param Integer $page
     * @return stdObject
     */
    public function search_groups($pagesize = 50, $page = 1) {
        $data = json_encode(array('page' => $page, 'page_size' => $pagesize));
        return $this->client->post("{$this->apiendpoint}issuer/groups/search", $data);
    }

    /**
     * Creates a Grade evidence item on a given credential.
     * @param String $grade - value must be between 0 and 100
     * @return stdObject
     */
    public function create_evidence_item_grade($grade, $description, $credentialid, $hidden = false) {

        if (is_numeric($grade) && intval($grade) >= 0 && intval($grade) <= 100) {

            $evidenceitem = array(
                "evidence_item" => array(
                    "description" => $description,
                    "category" => "grade",
                    "string_object" => (string) $grade,
                    "hidden" => $hidden
                )
            );

            return $this->create_evidence_item($evidenceitem, $credentialid);
        } else {
            throw new \InvalidArgumentException("$grade must be a numeric value between 0 and 100.");
        }
    }

    /**
     * Updates an evidence item on a given credential.
     * @param Integer $credentialid
     * @param Integer $evidenceitemid
     * @param String $grade - value must be between 0 and 100
     * @return stdObject
     */
    public function update_evidence_item_grade($credentialid, $evidenceitemid, $grade) {
        if (is_numeric($grade) && intval($grade) >= 0 && intval($grade) <= 100) {
            $evidenceitem = array('evidence_item' => array('string_object' => $grade));
            $data = json_encode($evidenceitem);
            $url = "{$this->apiendpoint}credentials/{$credentialid}/evidence_items/{$evidenceitemid}";
            return $this->client->put($url, $data);
        } else {
            throw new \InvalidArgumentException("$grade must be a numeric value between 0 and 100.");
        }
    }

    /**
     * Strip out keys with a null value from an object http://stackoverflow.com/a/15953991
     * @param stdObject $object
     * @return stdObject
     */
    private function strip_empty_keys($object) {

        $json = json_encode($object);
        $json = preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '', $json);
        $object = json_decode($json);

        return $object;
    }
}

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

namespace mod_accredible\client;
defined('MOODLE_INTERNAL') || die();

class client {

    public static function get($url, $token) {
        return self::create_req($url, $token, 'GET');
    }

    public static function post($url, $token, $postBody) {
        return self::create_req($url, $token, 'POST', $postBody);
    }

    public static function put($url, $token, $putBody) {
        return self::create_req($url, $token, 'PUT', $putBody);
    }

    static function create_req($url, $token, $method, $postBody = null) {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if (isset($postBody)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postBody);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Token '.$token,
            'Content-Type: application/json; charset=utf-8'
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }
}

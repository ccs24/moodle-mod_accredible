<?php

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
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FAILONERROR'    => true,
            'CURLOPT_HTTPHEADER'     => array(
                'Authorization: Token '.$token,
                'Content-Type: application/json; charset=utf-8',
                'Accredible-Integration: Moodle'
            )
        );

        switch($method) {
            case 'GET':
                $response = $curl->get($url, $postBody, $options);
                break;
            case 'POST':
                $response = $curl->post($url, $postBody, $options);
                break;
            case 'PUT':
                $response = $curl->put($url, $postBody, $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, $postBody, $options);
                break;
            default:
                throw new \coding_exception('Invalid HTTP method');
        }

        if($curl->error) {
            debugging('<div style="padding-top: 70px; font-size: 0.9rem;"><b>ACCREDIBLE API ERROR</b> ' .
                $curl->error . '<br />' . $method . ' ' . $url . '</div>', DEBUG_DEVELOPER);
        };

        return json_decode($response);
    }
}

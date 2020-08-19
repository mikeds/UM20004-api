<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Callback extends Public_Controller {
    public function after_init() {}

    public function customer_callback_token() {
        header('Content-type: application/json');

        if ($_GET) {
            if (isset($_GET['code'])) {
                $code = $_GET['code'];

                $results = array(
                    'message' => "UBP Customer Token",
                    'value' => array(
                        'code' => $code
                    )
                );

                echo json_encode($results);
                die();
            }
        }

        // unauthorized request
        http_response_code(401);
        die();
    }
}
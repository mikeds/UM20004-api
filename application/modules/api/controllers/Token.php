<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token extends Api_Controller {
	public function after_init() {}

	public function index() {
		$this->load->library('OAuth2', 'oauth2');
		$this->oauth2->get_token();
	}

	public function cancel_token() {
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$this->load->model('api/tokens_model', 'tokens');

			$token = get_bearer_token();

			$row = $this->tokens->get_datum(
				'',
				array(
					'access_token'	=> $token
				)
			)->row();

			if ($row == "") {
				echo json_encode(
					array(
						'error'	=> true,
						'error_description'	=> 'Cannot find token!'
					)
				);
				die();
			}

			$this->tokens->update(
				$row->access_token,
				array(
					'expires'	=> $this->_today
				)
			);

			echo json_encode(
				array(
					'message'	=> "Successfully triggered to force expire the token.",
					'response' => array(
						'token_bearer' 	=> $token,
						'timestamp'		=> $this->_today
					)
				)
			);
			die();
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function ubp() {
		if (!isset($_GET['bank_code'])) {
			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Please provide bank code."
				)
			);
			die();
		}

		$scope = "instapay";

		$bank_code = $_GET['bank_code'];
		
		if ($bank_code == "ubp") {
			$scope = "transfers";
		}

		$curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => UBP_BASE_URL . 'partners/sb/partners/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=password&client_id='. UBP_CLIENT_ID .'&username='. UBP_USERNAME .'&password='. UBP_PASSWORD .'&scope='.$scope,
            CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
            ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
		
		$response_arr = json_decode($response);

        if (!isset($response_arr->access_token)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Error to generate UBP token."
                )
            );
            die();
        }

        echo json_encode(
            array(
                'response' => array(
                    'access_token'  => $response_arr->access_token,
                    'timestamp'     => $this->_today
                )
            )
        );
	}
}

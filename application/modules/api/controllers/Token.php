<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token extends Api_Controller {
	public function after_init() {}

	public function index() {
		$this->load->library('OAuth2', 'oauth2');
		$this->oauth2->get_token();
	}

	public function otp() {
		$this->load->library("oauth2");
		$this->oauth2->get_resource();

		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$this->load->model("api/globe_access_tokens", "globe_access_token");
			$this->load->model("api/client_accounts_model", "client_accounts");

			if (!isset($_GET['code'])) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Please provide code from globeapi callback."
					)
				);
				die();
			}

			$base_url 	= GLOBEBASEURL . "oauth/access_token";

			$code  		= $_GET['code'];
			$app_id     = GLOBEAPPID;
			$app_secret = GLOBEAPPSECRET;
			
			$auth_url	= $base_url . "?app_id={$app_id}&app_secret={$app_secret}&code={$code}";

			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => $auth_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_HTTPHEADER => array(
					"Content-Type: application/json"
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			curl_close($curl);

			if ($err) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Unable to generate token. Curl Error #: {$err}",
						'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
					)
				);
				die();
			}

			$decoded = json_decode($response);

			if (!isset($decoded->access_token)) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Invalid code.",
						'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
					)
				);
				die();
			}

			$access_token 	= $decoded->access_token;
			$mobile_no		= $decoded->subscriber_number;

			$row_token = $this->globe_access_token->get_datum(
				'',
				array(
					'token_mobile_no'	=> $mobile_no,
				)
			)->row();

			if ($row_token == "") {
				$this->globe_access_token->insert(
					array(
						'token_code'			=> $access_token,
						'token_mobile_no'		=> $mobile_no,
						'token_date_added'		=> $this->_today
					)
				);
			} else {
				$this->globe_access_token->update(
					$row_token->token_id,
					array(
						'token_code'			=> $access_token,
						'token_date_added'		=> $this->_today
					)
				);
			}

			echo json_encode(
				array(
					'message'	=> "Successfully generated GLOBE API token.",
					'response' => array(
						'access_token' 		=> $access_token,
						'subscriber_number'	=> $mobile_no
					)
				)
			);
			die();
		}

		// unauthorized access
		$this->output->set_status_header(401);
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

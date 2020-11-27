<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Client_otp extends Client_Controller {
	public function after_init() {}

	public function request() {
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$account    	= $this->_account;
			$auth_bridge_id = $account->oauth_bridge_id;
			$mobile_no 		= $account->account_mobile_no;

			$this->set_sms_otp($auth_bridge_id, $mobile_no);
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function submit() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST' || $this->JSON_POST()) {
			$this->load->model("api/otp_model", "otp");

			$post       = $this->get_post();

			$account    	= $this->_account;
			$auth_bridge_id = $account->oauth_bridge_id;

			if (!isset($post['otp'])) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Please provide otp."
					)
				);
				die();
			}

			$otp = $post['otp'];

			$row = $this->otp->get_datum(
				'',
				array(
					'otp_code' => $otp
				)
			)->row();

			if ($row == "") {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Invalid OTP."
					)
				);
				die();
			}

			if ($auth_bridge_id != $row->otp_auth_bridge_id) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "OTP is belongs to other user."
					)
				);
				die();
			}

			if ($row->otp_status == 1) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "OTP already used."
					)
				);
				die();
			}

			// update OTP as activated
			$this->otp->update(
				$row->otp_number,
				array(
					'otp_status' => 1
				)
			);

			echo json_encode(
				array(
					'message' 	=> "Successfully OTP activated.",
					'timestamp'	=> $this->_today
				)
			);
			die();
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function token() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST' || $this->JSON_POST()) {
			$this->load->model("api/globe_access_tokens", "globe_access_token");

			$post       = $this->get_post();

			if (!isset($post['code'])) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Please provide code from globeapi callback."
					)
				);
				die();
			}

			$account    	= $this->_account;
			$auth_bridge_id = $account->oauth_bridge_id;
			$mobile_no 		= $account->account_mobile_no;

			$base_url 	= GLOBEBASEURL . "oauth/access_token";

			$code  		= $post['code'];
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
						'error_description' => "Unable to send OTP. Curl Error #: {$err}",
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

			$access_token 		= $decoded->access_token;
			$subscriber_number	= $decoded->subscriber_number;

			if ($mobile_no != $subscriber_number) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Invalid mobile no."
					)
				);
				die();
			}

			$this->globe_access_token->insert(
				array(
					'token_code'			=> $access_token,
					'token_auth_bridge_id'	=> $auth_bridge_id,
					'token_date_added'		=> $this->_today
				)
			);

			echo json_encode(
				array(
					'message'	=> "Successfully generated GLOBE API token.",
					'response' => array(
						'access_token' 		=> $access_token,
						'subscriber_number'	=> $subscriber_number
					)
				)
			);
			die();
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Otp_sms extends Api_Controller {
	public function after_init() {
        $this->load->library("oauth2");
		$this->oauth2->get_resource();
    }

	public function token() {
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

	public function request() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST' || $this->JSON_POST()) {

			$post       = $this->get_post();

			if (!isset($post['mobile_no'])) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Please provide mobile no."
					)
				);
				die();
			}

			$mobile_no 		= $post['mobile_no'];
			$module			= isset($post['module']) ? $post['module'] : "";
            
			$this->send_sms_otp($mobile_no, $module);			
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function submit() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST' || $this->JSON_POST()) {
			$this->load->model("api/client_pre_registration_model", "client_pre_registration");
			$this->load->model("api/client_accounts_model", "client_accounts");
			$this->load->model("api/tms_admin_accounts_model", "admin_accounts");
			$this->load->model("api/otp_model", "otp");
			$this->load->model("api/oauth_bridges_model", "bridges");
			$this->load->model("api/merchants_model", "merchants");
			$this->load->model("api/agent_client_referrals_model", "agent_client_referrals");

			$post       = $this->get_post();

			if (!isset($post['mobile_no'])) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Please provide mobile no."
					)
				);
				die();
			}

			if (!isset($post['otp'])) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Please provide OTP no."
					)
				);
				die();
			}

			$mobile_no	= $post['mobile_no'];
			$otp 		= $post['otp'];

			$row = $this->otp->get_datum(
				'',
				array(
					'otp_code' 		=> $otp,
					'otp_status'	=> 0,
					'otp_mobile_no'	=> $mobile_no
				)
			)->row();

			if ($row == "") {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Invalid OTP no."
					)
				);
				die();
			}

			if (strtotime($row->otp_date_expiration) < strtotime($this->_today)) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "OTP no. is expired."
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

			// check otp is for pre_registration
			$row_cpr = $this->client_pre_registration->get_datum(
				'',
				array(
					'account_otp_number' => $row->otp_number
				)
			)->row();

			if ($row_cpr != "") {
				$this->client_pre_registration->update(
					$row_cpr->account_number,
					array(
						'account_sms_status'	=> 1,
						'account_otp_number'	=> ""
					)
				);

				// delete used otp
				$this->otp->delete($row->otp_number);

				echo json_encode(
					array(
						'message' 	=> "Thank you for signing up. Kindly give us a maximum of 48 to 72 hours to review your application.",
						'timestamp'	=> $this->_today
					)
				);
				die();
			}

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
}

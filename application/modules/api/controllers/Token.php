<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token extends Api_Controller {
	private
		$_address           = "9294713423",
		$_address2			= "",
		$_code			 	= "nILkL8Kua5qxehEBzB7F7qeXpSBGb7atMBp5rCRBB4jsy8o4Ef5BGqdU7o7bRt7r4xjsBRKarH5e49yIBbbX7CMrj6AfKA7z7H7Rzqet6AAdpCMdyK5IzeRb8tBgcn7Gr4BiR6gtEayzgI7LAqACq6zpEty4764Hjkj4Ef8qbLnCaB4GLIdnKRzHdj4knsRy7rktd5Gx6UMdoMdfM8BBzsXgppRCX7boKtKGeyxS5Az8EFj4qM9h7GLaKu76ra9I",
		$_code2			 	= "",
        $_shortcode         = "21587007",
        $_app_id            = "AMM8H69MMeCb5Tp4nBiMnGC8kM7MHMba",
        $_app_secret        = "53ecfe76327a3b27fb787f92251edf4b23ad667afa8be63516e5127ee7aba3d4";

	public function after_init() {}

	public function index() {
		$this->load->library('OAuth2', 'oauth2');
		$this->oauth2->get_token();
	}

	public function otp() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST' || $this->JSON_POST()) {
			$this->load->model("api/globe_access_tokens", "globe_access_token");
			$this->load->model("api/client_accounts_model", "client_accounts");

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

			$access_token 		= $decoded->access_token;
			$subscriber_number	= $decoded->subscriber_number;

			// find subscriber_number then get auth_bridge_id
			$row = $this->client_accounts->get_datum(
				'',
				array(
					'account_mobile_no' => $subscriber_number
				)
			)->row();

			if ($row == "") {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Cannot find mobile no. on database.",
					)
				);
				die();
			}

			$oauth_bridge_id = $row->oauth_bridge_id;

			$this->globe_access_token->insert(
				array(
					'token_code'			=> $access_token,
					'token_auth_bridge_id'	=> $oauth_bridge_id,
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

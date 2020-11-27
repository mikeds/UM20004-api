<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Otp_sms extends Api_Controller {
	public function after_init() {
        $this->load->library("oauth2");
		$this->oauth2->get_resource();
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
            
			$this->set_sms_otp($mobile_no);
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function submit() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST' || $this->JSON_POST()) {
			$this->load->model("api/otp_model", "otp");

			$post       = $this->get_post();

			// $account    	= $this->_account;
			// $auth_bridge_id = $account->oauth_bridge_id;

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

			// if ($auth_bridge_id != $row->otp_auth_bridge_id) {
			// 	echo json_encode(
			// 		array(
			// 			'error'             => true,
			// 			'error_description' => "OTP is belongs to other user."
			// 		)
			// 	);
			// 	die();
			// }

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

			$expiration_date = "";

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

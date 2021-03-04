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
						'error_description' => "Please provide otp."
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
						'error_description' => "Invalid OTP."
					)
				);
				die();
			}

			if (strtotime($row->otp_date_expiration) < strtotime($this->_today)) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "OTP is expired."
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

			$token_row = $this->get_token();
			$client_id = $token_row->client_id;

			$row_datum = $this->bridges->get_datum(
				'',
				array(
					'oauth_bridge_id' => $client_id
				)
			)->row();

			$bridge_parent_id = "";
			$admin_oauth_bridge_id = $client_id;

			if ($row_datum != "") {
				$bridge_parent_id = $row_datum->oauth_bridge_parent_id;
			}

			if ($bridge_parent_id != "") {
				$row_admin_datum = $this->admin_accounts->get_datum(
					'',
					array(
						'oauth_bridge_id' => $bridge_parent_id
					)
				)->row();

				if ($row_admin_datum != "") {
					$admin_oauth_bridge_id = $bridge_parent_id;
				}
			}
			
			// check otp is for pre_registration
			$row_cpr = $this->client_pre_registration->get_datum(
				'',
				array(
					'account_otp_number' => $row->otp_number
				)
			)->row();

			if ($row_cpr != "") {
				// move client pre-registration account to client accounts

				$this->client_pre_registration->delete($row_cpr->account_number);

				$bridge_id = $this->generate_code(
					array(
						'account_number' 		=> $row_cpr->account_number,
						'account_date_added'	=> $this->_today,
						'admin_oauth_bridge_id'	=> $admin_oauth_bridge_id
					)
				);

				// do insert bridge id
				$this->bridges->insert(
					array(
						'oauth_bridge_id' 			=> $bridge_id,
						'oauth_bridge_parent_id'	=> $admin_oauth_bridge_id,
						'oauth_bridge_date_added'	=> $this->_today
					)
				);

				$insert_data = array(
					'account_number'			=> $row_cpr->account_number,
					'account_password'			=> $row_cpr->account_password,
					'account_fname'				=> $row_cpr->account_fname,
					'account_mname'				=> $row_cpr->account_mname,
					'account_lname'				=> $row_cpr->account_lname,
					'account_gender'			=> $row_cpr->account_gender,
					'account_dob'				=> $row_cpr->account_dob,
					'account_address'			=> $row_cpr->account_address,
					'account_street'			=> $row_cpr->account_street, 
					'account_brgy'				=> $row_cpr->account_brgy,
					'account_city'				=> $row_cpr->account_city,
					'country_id'				=> $row_cpr->country_id,
					'province_id'				=> $row_cpr->province_id,
					'province_others'			=> $row_cpr->province_others,
					'account_mobile_no'			=> $row_cpr->account_mobile_no,
					'account_email_address'		=> $row_cpr->account_email_address,
					'account_date_added'		=> $this->_today,
					'account_status'			=> 1, 
					'oauth_bridge_id'			=> $bridge_id
				);

				$this->client_accounts->insert(
					$insert_data
				);

				// find ref code
				if ($row_cpr->account_ref_code != "") {
					$row_agent = $this->merchants->get_datum(
						'',
						array(
							'merchant_ref_code' => $row_cpr->account_ref_code
						)
					)->row();

					if ($row_agent != "") {
						$this->agent_client_referrals->insert(
							array(
								'merchant_number' 	=> $row_agent->merchant_number,
								'client_number'		=> $row_cpr->account_number
							)
						);
					}
				}

				$account_number = $row_cpr->account_number;

				// create wallet address
				$this->create_wallet_address($account_number, $bridge_id, $admin_oauth_bridge_id);

				// create token auth for api
				$this->create_token_auth($account_number, $bridge_id);
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

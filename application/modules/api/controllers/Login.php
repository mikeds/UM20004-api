<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends Tms_admin_Controller {

	public function after_init() {}

	public function client() {
		$this->load->model("api/client_accounts_model", "accounts");

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$username = isset($post['username']) ? $post['username'] : "";
			$password = isset($post['password']) ? $post['password'] : "";

			$inner_joints = array(
				array(
					'table_name' 	=> 'oauth_clients',
					'condition'		=> 'client_accounts.oauth_bridge_id = oauth_clients.client_id',
					'type'			=> 'left'
				),
				array(
					'table_name' 	=> 'countries',
					'condition'		=> 'countries.country_id = client_accounts.country_id',
					'type'			=> 'left'
				)
			);

			$row_email = $this->accounts->get_datum(
				'',
				array(
					'account_email_address' 	=> $username
				),
				array(),
				$inner_joints
			)->row();
			
			$mobile_no = $this->filter_mobile_number($username);

			$row_mobile = $this->accounts->get_datum(
				'',
				array(
					'CONCAT(country_code, account_mobile_no) =' 	=> $mobile_no
				),
				array(),
				$inner_joints
			)->row();
	
			$row = $row_email != "" ? $row_email : $row_mobile;

			if ($row == "") {
				generate_error_message("E004-2");
			}

			if ($row->account_email_status != 1) {
				generate_error_message("E005-3");
			}

			if ($row->account_status != 1) {
				generate_error_message("E005-4");
			}

			$qr_code = md5($row->oauth_bridge_id);

			$avatar_image_url = '';
			
			if ($row->account_avatar_base64 != "") {
				$avatar_image_url = base_url() . "avatar/client-accounts/" . md5($row->account_number);
			}

			echo json_encode(
				array(
					'response' => array(
						'username' 		=> $row->account_username,
						// 'avatar' 		=> $row->account_avatar_base64,
						'first_name'	=> $row->account_fname,
						'middle_name'	=> $row->account_mname,
						'last_name'		=> $row->account_lname,
						'email_address'	=> $row->account_email_address,
						'mobile_no'		=> (empty($row->country_code) ? "63" : $row->country_code) . $row->account_mobile_no,
						'secret_code'	=> $row->client_id,
						'secret_key'	=> $row->client_secret,
						'avatar_image'	=> $avatar_image_url,
						'qr_code'		=> base_url() . "qr-code/client-accounts/{$qr_code}",
						'account_status' 	=> $row->account_status == 1 ? "activated" : "deactivated",
						'email_status'		=> $row->account_email_status == 1 ? "activated" : "not_activated"
					)
				)
			);
			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);	
	}

	public function merchant() {
		$this->load->model("api/merchant_accounts_model", "merchant_accounts");

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$username = isset($post['username']) ? $post['username'] : "";
			$password = isset($post['password']) ? $post['password'] : "";

			$inner_joints = array(
				array(
					'table_name' 	=> 'merchants',
					'condition'		=> 'merchants.merchant_number = merchant_accounts.merchant_number'
				),
				array(
					'table_name' 	=> 'oauth_clients',
					'condition'		=> 'merchant_accounts.oauth_bridge_id = oauth_clients.client_id',
					'type'			=> 'left'
				),
				array(
					'table_name' 	=> 'countries',
					'condition'		=> 'countries.country_id = merchants.country_id',
					'type'			=> 'left'
				)
			);

			$row = $this->merchant_accounts->get_datum(
				'',
				array(
					'account_username' 	=> $username,
					'account_password' 	=> $password
				),
				array(),
				$inner_joints,
				array(
					'*',
					'merchant_accounts.oauth_bridge_id as x_oauth_bridge_id'
				)
			)->row();

			if ($row == "") {
				generate_error_message("E004-2");
			}

			if ($row->account_status != 1) {
				generate_error_message("E005-2");
			}

			$qr_code = md5($row->x_oauth_bridge_id);

			$avatar_image_url = '';
			
			if ($row->account_avatar_base64 != "") {
				$avatar_image_url = base_url() . "avatar/merchant-accounts/" . md5($row->account_number);
			}

			echo json_encode(
				array(
					'response' => array(
						'merchant_name'	=> "{$row->merchant_fname} {$row->merchant_mname} {$row->merchant_lname}",
						'username' 		=> $row->account_username,
						// 'avatar' 		=> $row->account_avatar_base64,
						'first_name'	=> $row->account_fname,
						'middle_name'	=> $row->account_mname,
						'last_name'		=> $row->account_lname,
						'email_address'	=> $row->merchant_email_address,
						'mobile_no'		=> (empty($row->country_code) ? "63" : $row->country_code) . $row->merchant_mobile_no,
						'secret_code'	=> $row->client_id,
						'secret_key'	=> $row->client_secret,
						'avatar_image'	=> $avatar_image_url,
						'qr_code'		=> base_url() . "qr-code/merchant-accounts/{$qr_code}",
						'account_status' 	=> $row->merchant_status == 1 ? "verified" : "unverified",
						'email_status'		=> $row->merchant_email_status == 1 ? "activated" : "not_activated"
					)
				)
			);
			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
}

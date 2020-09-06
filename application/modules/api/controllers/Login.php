<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends Tms_admin_Controller {

	public function after_init() {
        
	}

	public function client() {}

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
					'condition'		=> 'merchants.oauth_bridge_id = oauth_clients.client_id'
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

			$qr_code = hash("md5", $row->x_oauth_bridge_id);

			echo json_encode(
				array(
					'merchant_name'	=> "{$row->merchant_fname} {$row->merchant_mname} {$row->merchant_lname}",
					'username' 		=> $row->account_username,
					'avatar' 		=> $row->account_avatar_base64,
					'first_name'	=> $row->account_fname,
					'middle_name'	=> $row->account_mname,
					'last_name'		=> $row->account_lname,
					'email_address'	=> $row->merchant_email_address,
					'mobile_no'		=> $row->merchant_mobile_no,
					'secret_code'	=> $row->client_id,
					'secret_key'	=> $row->client_secret,
					'qr_code'		=> base_url() . "transaction/qr-code-{$qr_code}"
				)
			);
			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
}

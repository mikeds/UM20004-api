<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Merchant extends Api_Controller {
	private
		$_master_account = NULL;

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');
		$this->load->model('api/merchants_model', 'merchants');

		$this->oauth2->get_resource();
		$this->_master_account = $this->get_master_account();
	}

	public function login() {
		/*
			- Username
			- Password
			- Token Bearer - from device auth
		*/
		header('Content-type: application/json');
		$post = json_decode($this->input->raw_input_stream, true);
		
		$message = "";

		if ($this->JSON_POST()) {
			$username = isset($post["username"]) ? $post["username"] : "";
			$password = isset($post["password"]) ? $post["password"] : "";

			$row = $this->merchants->get_datum(
				'',
				array(
					'CONCAT(merchant_mobile_country_code, merchant_mobile_no) ='	=> $username,
					'merchant_password'												=> $password,
					'merchant_status'												=> 1,
					'tms_admin_id'													=> $this->_master_account['account_id']
				)
			)->row();

			$row_email = $this->merchants->get_datum(
				'',
				array(
					'merchant_email_address'	=> $username,
					'merchant_password'			=> $password,
					'merchant_status'			=> 1,
					'tms_admin_id'				=> $this->_master_account['account_id']
				)
			)->row();

			if ($row == "" && $row_email == "") {
				$message = array(
					'error' => true, 
					'error_description' => 'The username or password is/are incorrect!'
				);

				// bad request
				http_response_code(200);
				echo json_encode($message);
				die();
			} else {
				$row = $row != "" ? $row : $row_email;

				$bridge_id = $row->oauth_client_bridge_id;

				// get key and code
				$oauth_key = $this->get_oauth_client($bridge_id);

				$key = $oauth_key['key'];
				$code = $oauth_key['code'];

				$qr_code = hash("md5", $row->merchant_email_address);

				$message = array(
					'value'	=> 
					array(
						'first_name'		=> $row->merchant_fname,
						'middle_name'		=> $row->merchant_mname,
						'last_name'			=> $row->merchant_lname,
						'ext_name'			=> $row->merchant_ext_name,
						'email_address'		=> $row->merchant_email_address,
						'mobile_country_code'	=> $row->merchant_mobile_country_code,
						'mobile_no'				=> $row->merchant_mobile_no,
						'secret_key'			=> $key,
						'secret_code'			=> $code,
						'qr_code'				=> base_url() . "transaction/qr-code-{$qr_code}"
					)
				);
			}
		} else {
			// unauthorized request
			http_response_code(401);
			die();
		}

		echo json_encode($message);
		die();
	}

	public function registration() {
		/*
			- Password
			- First name
			- Middle name
			- Last name
			- Ext name
			- Password
			- Email address
			- Mobile country code
			- Mobile Number
			- Token Bearer - from device auth
		*/

		header('Content-type: application/json');
		$post = json_decode($this->input->raw_input_stream, true);

		$message = "";

		if ($this->JSON_POST()) {
			$password = isset($post["password"]) ? $post["password"] : "";
			$password 	= null_to_empty($password);

			if (!valid_256_hash($password)) {
				$message = array(
					'error' => true, 
					'description' => 'Invalid password format!'
				);

				http_response_code(200);
				echo json_encode($message);
				die();
			}

			$fname = isset($post["first_name"]) ? $post["first_name"] : "";
			$mname = isset($post["middle_name"]) ? $post["middle_name"] : "";
			$lname = isset($post["last_name"]) ? $post["last_name"] : "";
			$ext_name = isset($post["ext_name"]) ? $post["ext_name"] : "";
			$email_address = isset($post["email_address"]) ? $post["email_address"] : "";
			
			$mobile_country_code = isset($post["mobile_country_code"]) ? $post["mobile_country_code"] : "";
			$mobile_no = isset($post["mobile_no"]) ? $post["mobile_no"] : "";

			$username = $mobile_country_code . $mobile_no;

			// filter
			if ($fname == "" or
				$lname == "" or
				$email_address == "" or 
				$mobile_country_code == "" or
				$mobile_no == ""
			) {
				$message = array(
					'error' => true, 
					'description' => 'Incomplete fields!'
				);

				http_response_code(200);
				echo json_encode($message);
				die();
			}

			$row = $this->merchants->get_datum(
				'',
				array(
					'CONCAT(merchant_mobile_country_code, merchant_mobile_no) ='	=> $username
				),
				array(
					'merchant_email_address'	=> $email_address
				)
			)->row();

			if ($row != "") {
				$message = array(
					'error' => true, 
					'error_description' => 'Username already exist!'
				);

				// bad request
				http_response_code(200);
				echo json_encode($message);
				die();
			} else {
				// create new oauth bridge
				$bridge_id = $this->set_oauth_bridge();

				// generate confirmation code
				// update client row
				$code = generate_code(4);
				$code = strtoupper($code);
				$date_expiration = $this->generate_date_expiration(10);

				$data = array(
					'merchant_password'				=> $password,
					'merchant_fname'				=> $fname,
					'merchant_mname'				=> $mname,
					'merchant_lname'				=> $lname,
					'merchant_ext_name'				=> $ext_name,
					'merchant_email_address'		=> $email_address,
					'merchant_mobile_country_code'	=> $mobile_country_code,
					'merchant_mobile_no'			=> $mobile_no,
					'oauth_client_bridge_id'		=> $bridge_id,
					'merchant_code_confirmation'	=> $code,
					'merchant_code_date_expiration'	=> $date_expiration,
					'merchant_date_added'			=> $this->_today,
					'tms_admin_id'					=> $this->_master_account['account_id']
				);

				$this->merchants->insert(
					$data
				);

				// create oauth client and merchannt wallet
				$this->set_oauth_client($bridge_id);

				// send confirmation code
				$email_message = $this->load->view("templates/email_templates/account_verification", array(
					"code" => $code
				), true);

				$this->send_verification($email_address, $email_message);

				// done process
				$message = array(
					'error' => false, 
					'message' => 'Succefully registred!'
				);
			}
		} else {
			// unauthorized request
			http_response_code(401);
			die();
		}

		echo json_encode($message);
		die();
	}
}

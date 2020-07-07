<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Client extends Api_Controller {

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');
		$this->load->model('api/clients_model', 'clients');

		$this->oauth2->get_resource();
	}

	public function login() {
		/*
			- Username
			- Password
			- Token Bearer - from device auth
		*/
		header('Content-type: application/json');
		$message = "";

		if ($_POST) {
			$username = $this->input->post("username");
			$password = $this->input->post("password");
			$password = hash("sha256", $password);

			$row_mobile = $this->clients->get_datum(
				'',
				array(
					'CONCAT(client_mobile_country_code, client_mobile_no) ='	=> $username,
					'client_password'											=> $password,
				)
			)->row();

			$row_email = $this->clients->get_datum(
				'',
				array(
					'client_email_address'	=> $username,
					'client_password'		=> $password,
				)
			)->row();

			if ($row_mobile == "" && $row_email == "") {
				$message = array(
					'error' => true, 
					'error_description' => 'The username or password is/are incorrect!'
				);

				// bad request
				http_response_code(200);
				echo json_encode($message);
				die();
			} else {

				$row = $row_mobile != "" ? row_mobile : $row_email;

				// check if account email verified
				if ($row->client_status == 0) {
					$message = array(
						'error' => true, 
						'error_description' => 'Unverified email address!'
					);
	
					// bad request
					http_response_code(200);
					echo json_encode($message);
					die();
				}

				// check if account kyc verified
				if ($row->client_kyc_status == 0) {
					$message = array(
						'error' => true, 
						'error_description' => 'Unverified KYC!'
					);
	
					// bad request
					http_response_code(200);
					echo json_encode($message);
					die();
				}

				$client_id = $row->client_id;
				$bridge_id = $row->oauth_client_bridge_id;

				// get key and code
				$oauth_key = $this->get_oauth_client($bridge_id);

				$key = $oauth_key['key'];
				$code = $oauth_key['code'];

				$wallet_address = $this->get_wallet_address($key, $code);

				$value = array(
					'first_name'		=> $row->client_fname,
					'middle_name'		=> $row->client_mname,
					'last_name'			=> $row->client_lname,
					'ext_name'			=> $row->client_ext_name,
					'email_address'		=> $row->client_email_address,
					'mobile_country_code'	=> $row->client_mobile_country_code,
					'mobile_no'				=> $row->client_mobile_no,
					// 'wallet_address'		=> $wallet_address,
					'secret_key'			=> $key,
					'secret_code'			=> $code
				);

				$message = array(
					'value' => $value
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
		$message = "";

		if ($_POST) {
			$password 	= null_to_empty($this->input->post("password"));
			$password 	= hash("sha256", $password);

			$fname 			= null_to_empty($this->input->post("first_name"));
			$mname 			= null_to_empty($this->input->post("middle_name"));
			$lname 			= null_to_empty($this->input->post("last_name"));
			$ext_name 		= null_to_empty($this->input->post("ext_name"));
			$email_address 	= null_to_empty($this->input->post("email_address"));
			
			$mobile_country_code 	= null_to_empty($this->input->post("mobile_country_code"));
			$mobile_no 				= null_to_empty($this->input->post("mobile_no"));

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
					'error_description' => 'Incomplete Fields!'
				);

				// bad request
				http_response_code(200);
				echo json_encode($message);
				die();
			}

			$row = $this->clients->get_datum(
				'',
				array(
					'CONCAT(client_mobile_country_code, client_mobile_no) ='	=> $username
				),
				array(
					'client_email_address'	=> $email_address
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

				// create user
				$data = array(
					'client_password'				=> $password,
					'client_fname'					=> $fname,
					'client_mname'					=> $mname,
					'client_lname'					=> $lname,
					'client_ext_name'				=> $ext_name,
					'client_email_address'			=> $email_address,
					'client_mobile_country_code'	=> $mobile_country_code,
					'client_mobile_no'				=> $mobile_no,
					'oauth_client_bridge_id'		=> $bridge_id
				);

				$client_id = $this->clients->insert(
					$data
				);

				$this->set_oauth_client($bridge_id);

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

		end:
		echo json_encode($message);
		die();
	}
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Client extends Api_Controller {

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');
		$this->load->model('api/clients_model', 'clients');
		$this->load->model('api/oauth_clients_model', 'oauth_clients');
		$this->load->model('api/oauth_client_bridges_model', 'oauth_client_bridges');
		$this->load->model('api/wallets_model', 'wallets');

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
					'client_status'												=> 1
				)
			)->row();

			$row_email = $this->clients->get_datum(
				'',
				array(
					'client_email_address'	=> $username,
					'client_password'		=> $password,
					'client_status'			=> 1
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

				$client_id = $row->client_id;

				// get key and code
				$oauth_client_row = $this->oauth_clients->get_datum(
					'',
					array(
						'oauth_client_bridge_id'	=> $row->oauth_client_bridge_id,
						'oauth_client_bridge_id !='	=> 0
					)
				)->row();

				$key = "";
				$code = "";

				if ($oauth_client_row != "") {
					$key = $oauth_client_row->client_id;
					$code = $oauth_client_row->client_secret;
				}

				$wallet_address = $this->get_wallet_address($key, $code);

				$value = array(
					'first_name'		=> $row->client_fname,
					'middle_name'		=> $row->client_mname,
					'last_name'			=> $row->client_lname,
					'ext_name'			=> $row->client_ext_name,
					'email_address'		=> $row->client_email_address,
					'mobile_country_code'	=> $row->client_mobile_country_code,
					'mobile_no'				=> $row->client_mobile_no,
					'wallet_address'		=> $wallet_address,
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
				// create ouath client bridges
				$bridge_id = $this->oauth_client_bridges->insert(
					array(
						'oauth_client_bridge_date_added'	=> $this->_today
					)
				);

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

				$today = date("Y-m-d H:i:s");
				$key = generate_password(19) . strtotime($today);
				$key = hash("sha256", $key);

				$code = generate_password(14) . strtotime($today);
				$code = hash("sha256", $code);

				// create secret key and secret code
				$this->oauth_clients->insert(
					array(
						'client_id'					=> $key,
						'client_secret'				=> $code,
						'oauth_client_bridge_id'	=> $bridge_id
					)
				);

				// create wallet address
				$this->set_wallet_address($key, $code);

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

	private function set_wallet_address($key, $code) {
		// create client wallet
		// combination of client primary id and client id/key
		// $wallet_address = hash_hmac("sha256", "", $code);
		$json = json_encode(
			array(
				'key'	=> $key,
				'code'	=> $code
			)
		);

		$wallet_address = hash_hmac("sha256", $json, $code);
		
		// insert new wallet
		$this->wallets->insert(
			array(
				'wallet_address'	=> $wallet_address
			)
		);
	}

	private function get_wallet_address($key, $code) {
		$wallet_address = "";

		// create client wallet
		// combination of client primary id and client id/key
		// $wallet_address = hash_hmac("sha256", "", $code);
		$json = json_encode(
			array(
				'key'	=> $key,
				'code'	=> $code
			)
		);

		$address = hash_hmac("sha256", $json, $code);

		$row = $this->wallets->get_datum(
			'',
			array(
				'wallet_address'	=> $address
			)
		)->row();

		if ($row != "") {
			$wallet_address = $row->wallet_address;
		}

		return $wallet_address;
	}
}

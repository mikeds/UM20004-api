<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Merchant extends Api_Controller {

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');
		$this->load->model('api/merchants_model', 'merchants');

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

			$row = $this->merchants->get_datum(
				'',
				array(
					'CONCAT(merchant_mobile_country_code, merchant_mobile_no) ='	=> $username,
					'merchant_password'												=> $password,
					'merchant_status'												=> 1
				)
			)->row();

			if ($row == "") {
				$message = array(
					'error' => 'invalid_login', 
					'error_description' => 'The username or password is/are incorrect!'
				);

				// bad request
				http_response_code(400);
				echo json_encode($message);
				die();
			} else {
				$message = array(
					'first_name'		=> $row->merchant_fname,
					'middle_name'		=> $row->merchant_mname,
					'last_name'			=> $row->merchant_lname,
					'ext_name'			=> $row->merchant_ext_name,
					'email_address'		=> $row->merchant_email_address,
					'mobile_country_code'	=> $row->merchant_mobile_country_code,
					'mobile_no'				=> $row->merchant_mobile_no
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
					'error' => 'invalid_login', 
					'description' => 'Incomplete fields!'
				);

				// bad request
				http_response_code(400);
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
					'error' => 'invalid_login', 
					'error_description' => 'Username already exist!'
				);

				// bad request
				http_response_code(400);
				echo json_encode($message);
				die();
			} else {
				$data = array(
					'merchant_password'				=> $password,
					'merchant_fname'				=> $fname,
					'merchant_mname'				=> $mname,
					'merchant_lname'				=> $lname,
					'merchant_ext_name'				=> $ext_name,
					'merchant_email_address'		=> $email_address,
					'merchant_mobile_country_code'	=> $mobile_country_code,
					'merchant_mobile_no'			=> $mobile_no
				);

				$this->merchants->insert(
					$data
				);

				$message = array('success' => true, 'message' => 'Succefully registred!');
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

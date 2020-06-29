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
					'error' => 'invalid_login', 
					'error_description' => 'The username or password is/are incorrect!',
					'value' => []
				);

				// bad request
				http_response_code(200);
				echo json_encode($message);
				die();
			} else {

				$row = $row_mobile != "" ? row_mobile : $row_email;

				$value = array(
					'first_name'		=> $row->client_fname,
					'middle_name'		=> $row->client_mname,
					'last_name'			=> $row->client_lname,
					'ext_name'			=> $row->client_ext_name,
					'email_address'		=> $row->client_email_address,
					'mobile_country_code'	=> $row->client_mobile_country_code,
					'mobile_no'				=> $row->client_mobile_no
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
					'error' => 'invalid_login', 
					'error_description' => 'Incomplete Fields!',
					'value' => []
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
					'error' => 'invalid_login', 
					'error_description' => 'Username already exist!',
					'value' => []
				);

				// bad request
				http_response_code(200);
				echo json_encode($message);
				die();
			} else {
				$data = array(
					'client_password'				=> $password,
					'client_fname'					=> $fname,
					'client_mname'					=> $mname,
					'client_lname'					=> $lname,
					'client_ext_name'				=> $ext_name,
					'client_email_address'			=> $email_address,
					'client_mobile_country_code'	=> $mobile_country_code,
					'client_mobile_no'				=> $mobile_no
				);

				$this->clients->insert(
					$data
				);

				$message = array(
					'error' => false, 
					'message' => 'Succefully registred!',
					'value' => []
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

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
		$post = json_decode($this->input->raw_input_stream, true);

		$message = "";

		if ($this->JSON_POST()) {
			$username = isset($post["username"]) ? $post["username"] : "";
			$password = isset($post["password"]) ? $post["password"] : "";
			// $username = $this->input->post("username");
			// $password = $this->input->post("password");

			// $password = hash("sha256", $password);

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

				$row = $row_mobile != "" ? $row_mobile : $row_email;

				// check if account email verified
				if ($row->client_status == 0) {
					$message = array(
						'error' => true, 
						'error_description' => 'Unverified account!'
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

				$qr_code = hash("md5", $row->client_email_address);

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
					'secret_code'			=> $code,
					'qr_code'				=> base_url() . "transaction/qr-code-{$qr_code}"
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
			// $fname 			= null_to_empty($this->input->post("first_name"));
			// $mname 			= null_to_empty($this->input->post("middle_name"));
			// $lname 			= null_to_empty($this->input->post("last_name"));
			// $ext_name 		= null_to_empty($this->input->post("ext_name"));
			// $email_address 	= null_to_empty($this->input->post("email_address"));
			
			$mobile_country_code = isset($post["mobile_country_code"]) ? $post["mobile_country_code"] : "";
			$mobile_no = isset($post["mobile_no"]) ? $post["mobile_no"] : "";
			// $mobile_country_code 	= null_to_empty($this->input->post("mobile_country_code"));
			// $mobile_no 				= null_to_empty($this->input->post("mobile_no"));

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

				// generate confirmation code
				// update client row
				$code = generate_code(4);
				$code = strtoupper($code);
				$date_expiration = $this->generate_date_expiration(10);

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
					'oauth_client_bridge_id'		=> $bridge_id,
					'client_code_confirmation'		=> $code,
					'client_code_date_expiration'	=> $date_expiration
				);

				$client_id = $this->clients->insert(
					$data
				);

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

		end:
		http_response_code(200);
		echo json_encode($message);
		die();
	}

	public function code_confirmation() {
		header('Content-type: application/json');
		$post = json_decode($this->input->raw_input_stream, true);
		$message = "";

		if ($this->JSON_POST()) {
			$username = isset($post["username"]) ? $post["username"] : "";
			$code = isset($post["code"]) ? $post["code"] : "";
			// $username 	= $this->input->post("username");
			// $code		= $this->input->post("code");

			$row = $this->get_client($username, 0);

			if ($row == "") {
				$message = array(
					'error' => true, 
					'message' => 'Account already activated!'
				);

				goto end;
			}

			$client_code = $row->client_code_confirmation;
			$date_expiration = $row->client_code_date_expiration;

			if (strtoupper($code) != strtoupper($client_code)) {
				$message = array(
					'error' => true, 
					'message' => 'Invalid code!'
				);

				goto end;
			}


			if (strtotime($date_expiration) < strtotime($this->_today)) {
				$message = array(
					'error' => true, 
					'message' => 'Code is already expired!'
				);

				goto end;
			}

			$this->clients->update(
				$row->client_id,
				array(
					'client_status' => 1
				)
			);

			// success
			$message = array(
				'error' => false, 
				'message' => 'Successfully activated!'
			);

			end:
			http_response_code(200);
			echo json_encode($message);
			die();
		}
	}

	public function resend_code_confirmation() {
		header('Content-type: application/json');
		$post = json_decode($this->input->raw_input_stream, true);
		$message = "";

		if ($this->JSON_POST()) {
			$username = isset($post["username"]) ? $post["username"] : "";
			// $username = $this->input->post("username");

			$row = $this->get_client($username, 0);

			if ($row == "") {
				$message = array(
					'error' => true, 
					'message' => 'Unable to find username!'
				);

				goto end;
			}

			$date_expiration = $row->client_code_date_expiration;

			if (strtotime($date_expiration) > strtotime($this->_today)) {
				$message = array(
					'error' => true, 
					'message' => 'You can resend confirmation code after 10 minutes!'
				);

				goto end;
			}

			$email_to = $row->client_email_address;

			// generate confirmation code
			// update client row
			$code = generate_code(4);
			$code = strtoupper($code);

			$this->clients->update(
				$row->client_id,
				array(
					'client_code_confirmation' => $code,
					'client_code_date_expiration' => $this->generate_date_expiration(10) // add expiration datetime after 10 minutes
				)
			);

			$data = array(
				'code' => $code
			);

			$email_message = $this->load->view("templates/email_templates/account_verification", $data, true);

			$this->send_verification($email_to, $email_message);

			$message = array(
				"error" => false,
				"message" => "Successfully resend account verification code!"
			);

			end:
			http_response_code(200);
			echo json_encode($message);
			die();
		}
	}
}

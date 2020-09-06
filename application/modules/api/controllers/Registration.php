<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registration extends Tms_admin_Controller {

	public function after_init() {}

	public function client() {}

	public function merchant() {
		$admin_oauth_bridge_id = $this->_account->oauth_bridge_id;

		$this->load->model("api/merchants_model", "merchants");

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			// $username		= isset($post["username"]) ? $post["username"] : "";
			$fname			= isset($post["first_name"]) ? $post["first_name"] : "";
			$mname			= isset($post["middle_name"]) ? $post["middle_name"] : "";
			$lname			= isset($post["last_name"]) ? $post["last_name"] : "";
			$gender			= isset($post["gender"]) ? $post["gender"] : "";
			$dob			= isset($post["dob"]) ? $post["dob"] : "";
			$address		= isset($post["address"]) ? $post["address"] : "";
			$street			= isset($post["street"]) ? $post["street"] : "";
			$brgy			= isset($post["brgy"]) ? $post["brgy"] : "";
			$city			= isset($post["city"]) ? $post["city"] : "";
			$country_id		= isset($post["country_id"]) ?  $post["country_id"] : "";
			$province_id	= isset($post["province_id"]) ? $post["province_id"] : "";
			$mobile_no		= isset($post["mobile_no"]) ? $post["mobile_no"] : "";
			// $contact_no		= isset($post["contact_no"]) ? $post["contact_no"] : "";
			$email_address	= isset($post["email_address"]) ? $post["email_address"] : "";
			$password		= isset($post["password"]) ? $post["password"] : "";

			if ($this->validate_username("merchant", $email_address) || $email_address == "") {
				generate_error_message("E006-2");
			}

			if ($this->validate_email_address("merchant", $email_address) || $email_address == "") {
				generate_error_message("E007-2");
			}

			if (trim($password) == "") {
				generate_error_message("E008");
			}

			$merchant_number = $this->generate_code(
				array(
					"merchant",
					$admin_oauth_bridge_id,
					$this->_today
				),
				"crc32"
			);

			$bridge_id = $this->generate_code(
				array(
					'merchant_number' 		=> $merchant_number,
					'merchant_date_added'	=> $this->_today,
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

			// generate pin
			$pin 	= generate_code(4, 2);

			// expiration timestamp
			$minutes_to_add = 5;
			$time = new DateTime($this->_today);
			$time->add(new DateInterval('PT' . $minutes_to_add . 'M'));
			$stamp = $time->format('Y-m-d H:i:s');

			$insert_data = array(
				'merchant_number'			=> $merchant_number,
				// 'merchant_code'				=> $merchant_code,
				'merchant_fname'			=> $fname,
				'merchant_mname'			=> $mname,
				'merchant_lname'			=> $lname,
				'merchant_gender'			=> $gender,
				'merchant_dob'				=> $dob,
				'merchant_address'			=> $address,
				'merchant_street'			=> $street,
				'merchant_brgy'				=> $brgy,
				'merchant_city'				=> $city,
				'country_id'				=> $country_id,
				'province_id'				=> $province_id,
				'merchant_mobile_no'		=> $mobile_no,
				// 'merchant_contact_no'		=> $contact_no,
				'merchant_email_address'	=> $email_address,
				'merchant_date_created'		=> $this->_today,
				'merchant_status'			=> 0, 
				'oauth_bridge_id'			=> $bridge_id,
				'merchant_email_activation_pin'			=> $pin,
				'merchant_email_activation_expiration'	=> $stamp
			);

			$this->merchants->insert(
				$insert_data
			);

			$this->create_merchant_account(
				$admin_oauth_bridge_id, 
				$merchant_number, 
				$fname, 
				$mname, 
				$lname, 
				$email_address, 
				$password
			);

			$this->send_email_activation(
				$email_address, 
				$pin
			);

			echo json_encode(
				array(
					'error' => false, 
					'message' => 'Succefully registred!'
				)
			);

			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	private function create_merchant_account($admin_oauth_bridge_id, $merchant_number, $fname, $mname, $lname, $email_address, $password) {
		$this->load->model("api/merchant_accounts_model", "merchant_accounts");

		$account_number = $this->generate_code(
			array(
				"merchant_account",
				$admin_oauth_bridge_id,
				$this->_today
			),
			"crc32"
		);

		$bridge_id = $this->generate_code(
			array(
				'account_number' 			=> $account_number,
				'account_date_added'		=> $this->_today,
				'oauth_bridge_parent_id'	=> $admin_oauth_bridge_id
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

		$account_data = array(
			'merchant_number'		=> $merchant_number,
			'account_number'		=> $account_number,
			'account_fname'			=> $fname,
			'account_mname'			=> $mname,
			'account_lname'			=> $lname,
			'account_username'		=> $email_address,
			'account_password'		=> $password,
			'account_date_added'	=> $this->_today,
			'oauth_bridge_id'		=> $bridge_id,
			'account_status'		=> 0
		);

		$this->merchant_accounts->insert(
			$account_data
		);
	}
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registration extends Tms_admin_Controller {

	public function after_init() {}

	public function uploads() {
		if ($_FILES && $_POST) {
			if (isset($_FILES['avatar_image'])) {
				$files = $_FILES['avatar_image'];

				echo "test1";
				die();

				$upload_avatar_results = $this->upload_files(
					"test",
					$files,
					"avatar_image",
					true,
					5,
					"jpg|jpeg|JPG|JPEG|PNG|png|bmp"
				);

				if (isset($upload_avatar_results['results'])) {
					$upload_results = $upload_avatar_results['results'];

					if (isset($upload_results['is_data'])) {
						if ($upload_results['is_data']) {
							// get base64 image
							if (isset($upload_results['data'][0])) {
								$first_data = $upload_results['data'][0];
								$base64_image = $first_data['base64_image'];

								// // update merchant
								// $this->merchant_accounts->update(
								// 	$account_number,
								// 	array(
								// 		'account_avatar_base64' => $base64_image
								// 	)
								// );

								echo "test2";
								die();
							}
						}
					}
				}

				// print_r($upload_avatar_results);
			}
		}
	}

	public function client() {
		$admin_oauth_bridge_id = $this->_account->oauth_bridge_id;

		$this->load->model("api/client_accounts_model", "client_accounts");

		if ($_POST) {
			/*
			{
				"username": "username",
				"first_name" : "Client 1",
				"middle_name" : "",
				"last_name" : "Client 1",
				"gender" : 1,
				"dob": "1990-06-23",
				"house_no": "23",
				"street": "street",
				"brgy": "brgy",
				"city": "quezon city",
				"country_id": 169,
				"province_id": 4883,
				"mobile_no": "09294713423",
				"email_address": "marknel.pineda@gmail.com",
				"password": ""
			}
			*/

			if ($this->JSON_POST()) {
				$post = $this->get_post();
				// $username		= isset($post["username"]) ? $post["username"] : "";
				$fname			= isset($post["first_name"]) ? $post["first_name"] : "";
				$mname			= isset($post["middle_name"]) ? $post["middle_name"] : "";
				$lname			= isset($post["last_name"]) ? $post["last_name"] : "";
				$gender			= isset($post["gender"]) ? $post["gender"] : "";
				$dob			= isset($post["dob"]) ? $post["dob"] : "";
				$house_no		= isset($post["house_no"]) ? $post["house_no"] : "";
				$street			= isset($post["street"]) ? $post["street"] : "";
				$brgy			= isset($post["brgy"]) ? $post["brgy"] : "";
				$city			= isset($post["city"]) ? $post["city"] : "";
				$country_id		= isset($post["country_id"]) ?  $post["country_id"] : "";
				$province_id	= isset($post["province_id"]) ? $post["province_id"] : "";
				$mobile_no		= isset($post["mobile_no"]) ? $post["mobile_no"] : "";
				$email_address	= isset($post["email_address"]) ? $post["email_address"] : "";
				$password		= isset($post["password"]) ? $post["password"] : "";
				$others			= isset($post["others"]) ? $post["others"] : "";
			} else {
				// $username		= $this->input->post("username");
				$fname			= $this->input->post("first_name");
				$mname			= $this->input->post("middle_name");
				$lname			= $this->input->post("last_name");
				$gender			= $this->input->post("gender");
				$dob			= $this->input->post("dob");
				$house_no		= $this->input->post("house_no");
				$street			= $this->input->post("street");
				$brgy			= $this->input->post("brgy");
				$city			= $this->input->post("city");
				$country_id		= $this->input->post("country_id");
				$province_id	= $this->input->post("province_id");
				$mobile_no		= $this->input->post("mobile_no");
				$email_address	= $this->input->post("email_address");
				$password		= $this->input->post("password");
				$others			= $this->input->post("others");
			}

			// $username = trim(strtolower($username));

			if ($email_address == "") {
				generate_error_message("E006-3");
			}

			// if ($this->validate_username("client", $email_address)) {
			// 	generate_error_message("E006-2");
			// }

			if ($this->validate_email_address("client", $email_address)) {
				generate_error_message("E007-2");
			}

			$country_id 	= is_numeric($country_id) ? $country_id : 169; // default PH
			$province_id 	= is_numeric($province_id) ? $province_id : 0;
			
			if ($this->validate_mobile_no("client", $country_id, $mobile_no)) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Mobile no. already used."
					)
				);
				die();
			}

			if (trim($password) == "") {
				generate_error_message("E008");
			}

			$account_number = $this->generate_code(
				array(
					"client",
					$admin_oauth_bridge_id,
					$this->_today
				),
				"crc32"
			);

			$bridge_id = $this->generate_code(
				array(
					'account_number' 		=> $account_number,
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

			// generate pin
			$pin 	= generate_code(4, 2);

			// expiration timestamp
			$minutes_to_add = 5;
			$time = new DateTime($this->_today);
			$time->add(new DateInterval('PT' . $minutes_to_add . 'M'));
			$stamp = $time->format('Y-m-d H:i:s');

			$insert_data = array(
				'account_number'			=> $account_number,
				'account_username'			=> $email_address,
				'account_password'			=> $password,
				'account_fname'				=> $fname,
				'account_mname'				=> $mname,
				'account_lname'				=> $lname,
				'account_gender'			=> $gender,
				'account_dob'				=> $dob,
				'account_address'			=> $house_no,
				'account_street'			=> $street,
				'account_brgy'				=> $brgy,
				'account_city'				=> $city,
				'country_id'				=> $country_id,
				'province_id'				=> $province_id,
				'province_others'			=> $others,
				'account_mobile_no'			=> $mobile_no,
				'account_email_address'		=> $email_address,
				'account_date_added'		=> $this->_today,
				'account_status'			=> 0, 
				'oauth_bridge_id'			=> $bridge_id,
				'account_email_activation_pin'			=> $pin,
				'account_email_activation_expiration'	=> $stamp
			);

			$this->client_accounts->insert(
				$insert_data
			);

			if ($_FILES) {
				if (isset($_FILES['avatar_image'])) {
					$avatar_image = $_FILES['avatar_image'];

					$upload_avatar_results = $this->upload_files(
						$account_number,
						$avatar_image,
						"avatar_image",
						true,
						5,
						"jpg|jpeg|JPG|JPEG|PNG|png|bmp"
					);

					if (isset($upload_avatar_results['results'])) {
						$upload_results = $upload_avatar_results['results'];

						if (isset($upload_avatar_results['results'])) {
							$upload_results = $upload_avatar_results['results'];
		
							if (isset($upload_results['is_data'])) {
								if ($upload_results['is_data']) {
									// get base64 image
									if (isset($upload_results['data'][0])) {
										$first_data = $upload_results['data'][0];
										$base64_image = $first_data['base64_image'];
		
										// update merchant
										$this->client_accounts->update(
											$account_number,
											array(
												'account_avatar_base64' => $base64_image
											)
										);
									}
								}
							}
						}
					}
				}
			}

			$this->send_email_activation(
				$email_address, 
				$pin
			);

			echo json_encode(
				array(
					'error' => false, 
					'message' => 'Succefully registered!'
				)
			);

			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function merchant() {
		$admin_oauth_bridge_id = $this->_account->oauth_bridge_id;

		$this->load->model("api/merchants_model", "merchants");

		if ($_POST) {
			/*
			{
				"first_name" : "Merchant 2",
				"middle_name" : "",
				"last_name" : "Merchant 2",
				"gender" : 1,
				"dob": "1990-06-23",
				"house_no": "23",
				"street": "street",
				"brgy": "brgy",
				"city": "quezon city",
				"country_id": 169,
				"province_id": 4883,
				"mobile_no": "09294713423",
				"email_address": "marknel.pineda@gmail.com",
				"password": ""
			}
			*/

			if ($this->JSON_POST()) {
				$post = $this->get_post();
				// $username		= isset($post["username"]) ? $post["username"] : "";
				$fname			= isset($post["first_name"]) ? $post["first_name"] : "";
				$mname			= isset($post["middle_name"]) ? $post["middle_name"] : "";
				$lname			= isset($post["last_name"]) ? $post["last_name"] : "";
				$gender			= isset($post["gender"]) ? $post["gender"] : "";
				$dob			= isset($post["dob"]) ? $post["dob"] : "";
				$house_no		= isset($post["house_no"]) ? $post["house_no"] : "";
				$street			= isset($post["street"]) ? $post["street"] : "";
				$brgy			= isset($post["brgy"]) ? $post["brgy"] : "";
				$city			= isset($post["city"]) ? $post["city"] : "";
				$country_id		= isset($post["country_id"]) ?  $post["country_id"] : "";
				$province_id	= isset($post["province_id"]) ? $post["province_id"] : "";
				$mobile_no		= isset($post["mobile_no"]) ? $post["mobile_no"] : "";
				$email_address	= isset($post["email_address"]) ? $post["email_address"] : "";
				$password		= isset($post["password"]) ? $post["password"] : "";
				$others			= isset($post["others"]) ? $post["others"] : "";
			} else {
				// $username		= $this->input->post("username");
				$fname			= $this->input->post("first_name");
				$mname			= $this->input->post("middle_name");
				$lname			= $this->input->post("last_name");
				$gender			= $this->input->post("gender");
				$dob			= $this->input->post("dob");
				$house_no		= $this->input->post("house_no");
				$street			= $this->input->post("street");
				$brgy			= $this->input->post("brgy");
				$city			= $this->input->post("city");
				$country_id		= $this->input->post("country_id");
				$province_id	= $this->input->post("province_id");
				$mobile_no		= $this->input->post("mobile_no");
				$email_address	= $this->input->post("email_address");
				$password		= $this->input->post("password");
				$others			= $this->input->post("others");
			}

			if ($this->validate_email_address("merchant", $email_address) || $email_address == "") {
				generate_error_message("E007-2");
			}

			// if ($this->validate_username("merchant", $email_address) || $email_address == "") {
			// 	generate_error_message("E006-2");
			// }

			$country_id 	= is_numeric($country_id) ? $country_id : 169; // default PH
			$province_id 	= is_numeric($province_id) ? $province_id : 0;

			if ($this->validate_mobile_no("merchant", $country_id, $mobile_no)) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Mobile no. already used."
					)
				);
				die();
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
				'merchant_address'			=> $house_no,
				'merchant_street'			=> $street,
				'merchant_brgy'				=> $brgy,
				'merchant_city'				=> $city,
				'country_id'				=> $country_id,
				'province_id'				=> $province_id,
				'province_others'			=> $others,
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

			$account_number = $this->create_merchant_account(
				$admin_oauth_bridge_id, 
				$merchant_number, 
				$fname, 
				$mname, 
				$lname, 
				$email_address, 
				$password
			);

			if ($_FILES) {
				if (isset($_FILES['files'])) {
					$files = $_FILES['files'];
	
					$upload_files_results = $this->upload_files(
						$merchant_number,
						$files
					);
				}

				if (isset($_FILES['avatar_image'])) {
					$avatar_image = $_FILES['avatar_image'];

					$upload_avatar_results = $this->upload_files(
						$merchant_number,
						$avatar_image,
						"avatar_image",
						true,
						5,
						"jpg|jpeg|JPG|JPEG|PNG|png|bmp"
					);

					if (isset($upload_avatar_results['results'])) {
						$upload_results = $upload_avatar_results['results'];

						if (isset($upload_avatar_results['results'])) {
							$upload_results = $upload_avatar_results['results'];
		
							if (isset($upload_results['is_data'])) {
								if ($upload_results['is_data']) {
									// get base64 image
									if (isset($upload_results['data'][0])) {
										$first_data = $upload_results['data'][0];
										$base64_image = $first_data['base64_image'];
		
										// update merchant
										$this->merchant_accounts->update(
											$account_number,
											array(
												'account_avatar_base64' => $base64_image
											)
										);
									}
								}
							}
						}
					}
				}
			}

			$this->send_email_activation(
				$email_address, 
				$pin
			);

			echo json_encode(
				array(
					'error' => false, 
					'message' => 'Succefully registered!'
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

		return $account_number;
	}
}

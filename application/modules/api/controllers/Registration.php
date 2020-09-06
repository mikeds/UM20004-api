<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registration extends Tms_admin_Controller {

	public function after_init() {
        
	}

	public function client() {}

	public function merchant() {
		$this->load->model("api/merchant_accounts_model", "merchant_accounts");

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$username		= isset($post["username"]) ? $post["username"] : "";
			$fname			= isset($post["first_name"]) ? $post["first_name"] : "";
			$mname			= isset($post["middle_name"]) ? $post["middle_name"] : "";
			$lname			= isset($post["last_name"]) ? $post["last_name"] : "";
			$gender			= isset($post["gender"]) ? $post["gender"] : "";
			$dob			= isset($post["dob"]) ? $post["dob"] : "";
			$address		= isset($post["address"]) ? $post["address"] : "";
			$street			= isset($post["street"]) ? $post["street"] : "";
			$brgy			= isset($post["brgy"]) ? $post["brgy"] : "";
			$city			= isset($post["city"]) ? $post["city"] : "";
			$country_id		= isset($post["country"]) ?  $post["country"] : "";
			$province_id	= isset($post["province"]) ? $post["province"] : "";
			$mobile_no		= isset($post["mobile_no"]) ? $post["mobile_no"] : "";
			$contact_no		= isset($post["contact_no"]) ? $post["contact_no"] : "";
			$email_address	= isset($post["email_address"]) ? $post["email_address"] : "";
			$password		= isset($post["password"]) ? $post["password"] : "";

			if ($this->validate_username("merchant", $email_address)) {
				generate_error_message("E006-2");
			}

			if ($this->validate_email_address("merchant", $email_address)) {
				generate_error_message("E007-2");
			}

			
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
}

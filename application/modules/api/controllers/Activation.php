<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activation extends Tms_admin_Controller {

	public function after_init() {}

	public function merchant_email_activation() {
		$this->load->model("api/merchants_model", "merchants");
		$this->load->model("api/merchant_accounts_model", "merchant_accounts");

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$pin			= isset($post['pin']) ? $post['pin'] : "";
			$email_address 	= isset($post['email_address']) ? $post['email_address'] : "";

			if ($pin == "") {
				generate_error_message("E009-1-1");
			}

			if ($email_address == "") {
				generate_error_message("E009-1");
			}

			$row = $this->merchants->get_datum(
				'',
				array(
					'merchant_email_address' 		=> $email_address,
					'merchant_email_activation_pin'	=> $pin,
					'merchant_email_status'			=> 0
				)
			)->row();

			if ($row == "") {
				generate_error_message("E009-1-1");
			}

			$expiration_date = $row->merchant_email_activation_expiration;

			if ($expiration_date != "") {
				if ($expiration_date < $this->_today) {
					generate_error_message("E010-3");
				}
			}

			$this->merchants->update(
				$row->merchant_number,
				array(
					'merchant_email_status' => 1
				)
			);

			// get first account to activate
			$results = $this->merchant_accounts->get_data(
				array(
					'*'
				),
				array(
					'merchant_number' => $row->merchant_number
				),
				array(),
				array(),
				array(
					'filter'	=> 'account_date_added',
					'sort'		=> 'DESC'
				),
				0,
				1
			);

			if (count($results) != 0) {
				$first_row = $results[0];
				$account_number = $first_row['account_number'];
				
				$this->merchant_accounts->update(
					$account_number,
					array(
						'account_status' => 1
					)
				);
			}

			echo json_encode(
				array(
					'error' => false,
					'message' => "Successfully account email is activated!"
				)
			);
			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function merchant_email_resend() {
		$this->load->model("api/merchants_model", "merchants");

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();
			$email_address = isset($post['email_address']) ? $post['email_address'] : "";

			if ($email_address == "") {
				generate_error_message("E009-1");
			}

			$row = $this->merchants->get_datum(
				'',
				array(
					'merchant_email_address' 	=> $email_address,
					'merchant_email_status'		=> 0
				)
			)->row();

			if ($row == "") {
				generate_error_message("E009-2");
			}

			$minutes_to_add = 5;
			$time = new DateTime($this->_today);
			$time->add(new DateInterval('PT' . $minutes_to_add . 'M'));
			$stamp = $time->format('Y-m-d H:i:s');

			// generate pin
			$pin 	= generate_code(4, 2);

			$this->send_email_activation(
				$email_address, 
				$pin, 
				$row->merchant_email_activation_expiration
			);

			$this->merchants->update(
				$row->merchant_number,
				array(
					'merchant_email_activation_pin' 		=> $pin,
					'merchant_email_activation_expiration'	=> $stamp
				)
			);

			echo json_encode(
				array(
					'error' => false,
					'message' => "Successfully send activation pin!"
				)
			);

			return;
		}
		
		// unauthorized access
		$this->output->set_status_header(401);
	}
}

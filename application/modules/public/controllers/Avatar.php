<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Avatar extends Public_Controller {

	public function after_init() {}

	public function client_accounts($id) {
		$this->load->model("api/client_accounts_model", "accounts");

		$row = $this->accounts->get_datum(
			'',
			array(
				'MD5(account_number)' 	=> $id,
				'account_status'		=> 1
			)
		)->row();

		if ($row != "") {
			$data = $row->account_avatar_base64;

			if ($data != "") {
				header('Content-Type: image/jpeg');
				echo base64_decode($data);
				return;
			}
		}
		
		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function merchant_accounts($id) {
		$this->load->model("api/merchant_accounts_model", "accounts");

		$row = $this->accounts->get_datum(
			'',
			array(
				'MD5(account_number)' 	=> $id,
				'account_status'		=> 1
			)
		)->row();

		if ($row != "") {
			$data = $row->account_avatar_base64;

			if ($data != "") {
				header('Content-Type: image/jpeg');
				echo base64_decode($data);
				return;
			}
		}
		
		// unauthorized access
		$this->output->set_status_header(401);
	}
	
}

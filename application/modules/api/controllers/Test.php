<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends Api_Controller {
	public function after_init() {}

	public function index() {}

	public function reset_mobile_no() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->load->model("api/client_accounts_model", "client_accounts");

			$mobile_no = $this->input->post("mobile_no");

			$row = $this->client_accounts->get_datum(
				'',
				array(
					'account_mobile_no'	=> $mobile_no
				)
			)->row();

			if ($row == "") {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Cannot find mobile no."
					)
				);
				die();
			}

			$random_number = generate_code(10);

			$this->client_accounts->update(
				$row->account_number,
				array(
					'account_mobile_no'	=> $row->account_mobile_no . "-" . strtolower($random_number)
				)
			);

			echo json_encode(
				array(
					'message'	=> "Successfully triggered to force reset mobile no.",
					'response' => array(
						'mobile_no' => $mobile_no,
						'timestamp'	=> $this->_today
					)
				)
			);

			die();
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Callback extends Api_Controller {

	public function after_init() {
		if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function ubp_code() {
		if ($_GET) {
			if (isset($_GET['code'])) {
				$code = $_GET['code'];

				echo json_encode(
					array(
						'response' => array(
							'code' => $code
						)
					)
				);
				
				return;
			}
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function globelabs() {
		if ($_GET) {
			if (isset($_GET['code'])) {
				$code = $_GET['code'];

				
			}
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
}

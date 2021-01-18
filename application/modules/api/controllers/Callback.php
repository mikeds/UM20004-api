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

	public function paynamics_response() {
		echo json_encode(
			array(
				'message' => 'Successfully done transaction on our payment gateway.',
				'response' => array(
					'timestamp' => $this->_today
				)
			)
		);
	}

	public function paynamics_cancel() {
		echo json_encode(
			array(
				'error'		=> true,
				'message' 	=> 'Payment gateway is cancelled, Invalid transaction.',
				'response' 	=> array(
					'timestamp' => $this->_today
				)
			)
		);
	}

	public function paynamics_notification() {
		echo json_encode(
			array(
				'message' 	=> 'Payment gateway notification callback.',
				'response' 	=> array(
					'timestamp' => $this->_today
				)
			)
		);
	}
}

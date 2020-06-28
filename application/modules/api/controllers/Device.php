<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Device extends Api_Controller {

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');
		$this->load->model('api/devices_model','devices');

		$this->oauth2->get_resource();
	}

	public function auth() {
		/*
			- UUID

			return 
			- Token Bearer - default token from server
		*/

		header('Content-type: application/json');
		$message = "";

		if ($_POST) {
			$uuid = $this->input->post("uuid");
			$uuid = trim($uuid);
			$uuid = strtolower($uuid);

			// validate if UUID registered
			$row = $this->devices->get_datum(
				'',
				array(
					'device_uuid'	=> $uuid
				)
			)->row();

			if ($row == "") {
				// return error
				$message = array('error' => true, 'message' => 'Device terminal is not registered!');
			} else {
				$message = array('success' => true, 'message' => 'Device terminal is registered!');
			}
			
		} else {
			// return error
			header("HTTP/1.1 404 Not Found");
			die();
		}

		echo json_encode($message);
		die();
	}

	public function registration() {
		/*
			- Token Bearer - token from device
			- UUID

			return 
			- message - success | error
		*/

		header('Content-type: application/json');
		$message = "";

		if ($_POST) {
			$uuid = $this->input->post("uuid");
			$uuid = trim($uuid);
			$uuid = strtolower($uuid);

			// validate if UUID registered
			$row = $this->devices->get_datum(
				'',
				array(
					'device_uuid'	=> $uuid
				)
			)->row();

			if ($row != "") {
				// return error
				$message = array('error' => true, 'message' => 'Device UUID is already registered!');
				
			} else {
				$data = array(
					'device_uuid' => $uuid
				);
	
				$this->devices->insert(
					$data
				);
	
				$message = array('success' => true, 'message' => 'Successfully registered!');
			}
		} else {
			// return error
			header("HTTP/1.1 404 Not Found");
			die();
		}

		echo json_encode($message);
		die();
	}
}

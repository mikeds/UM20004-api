<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token extends Api_Controller {

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');
	}

	public function index() {
		$this->oauth2->get_token();
	}
}

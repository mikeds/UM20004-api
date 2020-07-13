<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Merchant_transaction extends Api_Controller {

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');

		$this->oauth2->get_resource();
	}

    
}

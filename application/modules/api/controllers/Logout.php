<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Logout extends Api_Controller {
	public function after_init() {
		$this->_today = date("Y-m-d H:i:s");
	}

	public function index(){
		
	}
}





























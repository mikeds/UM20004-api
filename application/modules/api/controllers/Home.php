<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends Api_Controller {

	public function after_init() {}

	public function index() {
		header('Content-type: application/json');
        $success = array('Description' => "Bambupay API", 'message' => 'Bambupay');
        echo json_encode($success);
	}
	
}

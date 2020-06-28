<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Error_404 extends Api_Controller {

	public function after_init(){
 		$this->_today = date("Y-m-d H:i:s");
	}

	public function index() {
        header('Content-type: application/json');
        $success = array('error' => true, 'message' => 'Invalid Page!');
        echo json_encode($success);
	}
}





















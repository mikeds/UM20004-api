<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ledger_client extends Client_Controller {

	public function after_init() {}

	public function index() {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }

        
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Clients extends Client_Controller {

	public function after_init() {}

	public function balance() {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }
        
        $account    = $this->_account;

        $balance    = $this->decrypt_wallet_balance($account->wallet_balance);

        echo json_encode(
            array(
                'response' => array(
                    'account_number'=> $account->account_number,
                    'balance'       => $balance
                )
            )
        );
    }
}

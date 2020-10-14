<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Scanpayqr_merchant extends Merchant_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function create() {
		$account                = $this->_account;
        $transaction_type_id    = "txtype_scanpayqr1";
		$post                   = $this->get_post();
		
		$account_oauth_bridge_id    = $account->account_oauth_bridge_id;
		$merchant_oauth_bridge_id	= $account->merchant_oauth_bridge_id;
		$admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;
		
		$amount = 0;
        $fee = 0;

        if (!isset($post["amount"])) {
            die();
        }

        if (is_decimal($amount)) {
            die();
        }

        if (!is_numeric($amount)) {
            die();
        }
        
		$amount = $post["amount"];
		
		$tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
			$account_oauth_bridge_id,
            ""
        );
        
		$transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];
		        
        echo json_encode(
            array(
                'message' => "Successfully created ScanPayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
	}
}

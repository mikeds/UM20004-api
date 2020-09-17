<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Createpayqr_client extends Client_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
	}

    public function create() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_createpayqr1"; // scanpayqr
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $client_oauth_bridge_id    = $account->oauth_bridge_id;
        $client_balanace           = $this->decrypt_wallet_balance($account->wallet_balance);

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

        $total_amount   = $amount + $fee;

        if ($client_balanace < $total_amount) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "insufficient balance."
                )
            );
            die();
        }

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
			"",
            $client_oauth_bridge_id,
            $client_oauth_bridge_id
        );
        
		$transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];

        echo json_encode(
            array(
                'message' => "Successfully created CreatePayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}"
                )
            )
        );
    }
}

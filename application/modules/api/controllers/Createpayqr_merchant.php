<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Createpayqr_merchant extends Merchant_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
	}

    public function create() {
        $this->load->model("api/transactions_model", "transactions");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_createpayqr1";
		$post                   = $this->get_post(); // get post

        $admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;
		$account_oauth_bridge_id    = $account->account_oauth_bridge_id;
        $merchant_oauth_bridge_id	= $account->merchant_oauth_bridge_id;
        
        if (!isset($post["amount"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Amount."
                )
            );
            die();
        }

        // get amount
        $amount = $post["amount"];
        
        if (is_decimal($amount)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "No decimal value."
                )
            );
            die();
        }

        if (!is_numeric($amount)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Not numeric value."
                )
            );
            die();
        }

        // get fee
        $fee = 0;

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $merchant_oauth_bridge_id, // requested by
            "",
            $merchant_oauth_bridge_id // created by
        );

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];

        echo json_encode(
            array(
                'message' => "Successfully created CreatePayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
    }
}

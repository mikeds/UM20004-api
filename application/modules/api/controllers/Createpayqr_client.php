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
        $this->load->model("api/transactions_model", "transactions");

        $legder_desc            = "createpayqr";
        $account                = $this->_account;
        $transaction_type_id    = "txtype_createpayqr1"; // scanpayqr
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $client_oauth_bridge_id    = $account->oauth_bridge_id;
        $client_balance             = $this->decrypt_wallet_balance($account->wallet_balance);

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

        $debit_amount = $amount + $fee;

        if ($client_balance < $debit_amount) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Insufficient Balance!"
                )
            );
            die();
        }

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $client_oauth_bridge_id, // requested by
            "",
            $client_oauth_bridge_id // created by
        );

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];

        echo json_encode(
            array(
                'message' => "Successfully created CreatePayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'amount'        => $amount,
                    'fee'           => $fee,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
    }
}

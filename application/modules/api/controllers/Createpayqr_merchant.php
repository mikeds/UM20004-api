<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Createpayqr_merchant extends Merchant_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function accept() {
        $this->load->model("api/transactions_model", "transactions");

		$account                = $this->_account;
        $transaction_type_id    = "txtype_createpayqr1";
		$post                   = $this->get_post();
        
        $admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;
		$account_oauth_bridge_id    = $account->account_oauth_bridge_id;
		$merchant_oauth_bridge_id	= $account->merchant_oauth_bridge_id;
		
		$amount = 0;
        $fee = 0;

        if (!isset($post["sender_ref_id"])) {
            die();
        }

        $sender_ref_id = $post['sender_ref_id'];

        if (trim($sender_ref_id) == "") {
            die();
        }

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id,
                'transaction_type_id'       => $transaction_type_id
            )
        )->row();

        if ($row == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Refference ID."
                )
            );
            die();
        }

        $expiration_date = $row->transaction_date_expiration;

        if (strtotime($expiration_date) < strtotime($this->_today)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Transaction is expired."
                )
            );
            die();
        }

        $transaction_id = $row->transaction_id;
        $amount         = $row->transaction_amount;
        $fee            = $row->transaction_fee;

        $total_amount   = $amount + $fee;

        $client_oauth_bridge_id = $row->transaction_requested_to;

        // create ledger
        $debit_amount	= $total_amount;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        $debit_total_amount 	= 0 - $debit_amount; // make it negative
        $credit_total_amount	= $credit_amount;
        
        $credit_wallet_address	    = $this->get_wallet_address($merchant_oauth_bridge_id);
        $debit_wallet_address		= $this->get_wallet_address($client_oauth_bridge_id);

        if ($credit_wallet_address == "" || $debit_wallet_address == "") {
            die();
        }

        $debit_new_balances = $this->update_wallet($debit_wallet_address, $debit_total_amount);
        if ($debit_new_balances) {
            // record to ledger
            $this->new_ledger_datum(
                "createpayqr_debit", 
                $transaction_id, 
                $credit_wallet_address, // credit to wallet
                $debit_wallet_address, // debit from wallet
                $debit_new_balances
            );
        }

        $credit_new_balances = $this->update_wallet($credit_wallet_address, $credit_total_amount);
        if ($credit_new_balances) {
            // record to ledger
            $this->new_ledger_datum(
                "createpayqr_credit", 
                $transaction_id, 
                $debit_wallet_address, // debit from wallet address
                $credit_wallet_address, // credit to wallet address
                $credit_new_balances
            );
        }

        $this->transactions->update(
            $row->transaction_id,
            array(
                'transaction_status'        => 1,
                'transaction_requested_by'  => $account_oauth_bridge_id,
                'transaction_approved_by'   => $account_oauth_bridge_id,
                'transaction_date_approved' => $this->_today
            )
        );

        echo json_encode(
            array(
                'message' => "Successfully accepted CreatePayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}"
                )
            )
        );
	}
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Scanpayqr_client extends Client_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
        }
        
        $this->load->model("api/transactions_model", "transactions");
	}

	public function accept() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_scanpayqr1"; // scanpayqr
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $client_oauth_bridge_id    = $account->oauth_bridge_id;
        $client_balanace           = $this->decrypt_wallet_balance($account->wallet_balance);

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
            ),
            array(),
            array(
                array(
                    'table_name'    => 'merchant_accounts',
                    'condition'     => 'merchant_accounts.oauth_bridge_id = transactions.transaction_requested_by'
                ),
                array(
                    'table_name'    => 'merchants',
                    'condition'     => 'merchants.merchant_number = merchant_accounts.merchant_number'
                )
            ),
            array(
                '*',
                'merchants.oauth_bridge_id as merchant_oauth_bridge_id'
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
        
        // merchant
        $transaction_requested_by = $row->merchant_oauth_bridge_id;

        // client
        $transaction_requested_to = $client_oauth_bridge_id;

        $transaction_id = $row->transaction_id;
        $amount         = $row->transaction_amount;
        $fee            = $row->transaction_fee;

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

        // create ledger
        $debit_amount	= $total_amount;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        $debit_total_amount 	= 0 - $debit_amount; // make it negative
        $credit_total_amount	= $credit_amount;

        $credit_wallet_address	    = $this->get_wallet_address($transaction_requested_by);
        $debit_wallet_address		= $this->get_wallet_address($transaction_requested_to);

        if ($credit_wallet_address == "" || $debit_wallet_address == "") {
            die();
        }
        
        $debit_new_balances = $this->update_wallet($debit_wallet_address, $debit_total_amount);
        if ($debit_new_balances) {
            // record to ledger
            $this->new_ledger_datum(
                "scanpayqr_debit", 
                $transaction_id, 
                $credit_wallet_address, // request from credit wallet
                $debit_wallet_address, // requested to debit wallet
                $debit_new_balances
            );
        }

        $credit_new_balances = $this->update_wallet($credit_wallet_address, $credit_total_amount);
        if ($credit_new_balances) {
            // record to ledger
            $this->new_ledger_datum(
                "scanpayqr_credit", 
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
                'transaction_requested_to'  => $transaction_requested_to,
                'transaction_date_approved' => $this->_today
            )
        );

        echo json_encode(
            array(
                'message' => "Successfully done ScanPayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
	}
}

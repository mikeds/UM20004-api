<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Otp_send_to extends Client_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
    }

	public function activation() {
        $this->load->model("api/transactions_model", "transactions");
        
        $transaction_type_id = "txtype_transfer1"; // transfer

        $account    = $this->_account;

        $post       = $this->get_post();
        
        if (!isset($post["sender_ref_id"])) {
            die();
        }

        if (!isset($post["pin"])) {
            die();
        }

        $sender_ref_id = $post["sender_ref_id"];

        if (trim($sender_ref_id) == "") {
            die();
        }

        $pin = $post["pin"];

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id,
                'transaction_otp_pin'       => $pin,
                'transaction_otp_status'    => 0,
                'transaction_type_id'       => $transaction_type_id,
                'transaction_requested_by'  => $account->oauth_bridge_id
            )
        )->row();

        if ($row == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Refference ID or PIN."
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

        // create ledger
        $debit_amount	= $amount;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        $debit_total_amount 	= 0 - $debit_amount; // make it negative
        $credit_total_amount	= $credit_amount;

        $debit_wallet_address		= $this->get_wallet_address($row->transaction_requested_by);
        $credit_wallet_address	    = $this->get_wallet_address($row->transaction_requested_to);
        
        if ($credit_wallet_address == "" || $debit_wallet_address == "") {
            die();
        }

        $debit_new_balances = $this->update_wallet($debit_wallet_address, $debit_total_amount);
        if ($debit_new_balances) {
            // record to ledger
            $this->new_ledger_datum(
                "cash_in_debit", 
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
                "cash_in_credit", 
                $transaction_id, 
                $debit_wallet_address, // debit from wallet address
                $credit_wallet_address, // credit to wallet address
                $credit_new_balances
            );
        }

        $this->transactions->update(
            $row->transaction_id,
            array(
                'transaction_status' 		=> 1,
                'transaction_otp_status'    => 1,
                'transaction_date_approved' => $this->_today
            )
        );

        echo json_encode(
            array(
                'message' => "Successfully transfered.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
    }
    
    public function resend() {
        $this->load->model("api/transactions_model", "transactions");
        
        $account    = $this->_account;

        $post       = $this->get_post();
        
        if (!isset($post["sender_ref_id"])) {
            die();
        }

        $sender_ref_id = $post["sender_ref_id"];

        if (trim($sender_ref_id) == "") {
            die();
        }

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id,
                'transaction_otp_status'    => 0,
                'transaction_requested_by'  => $account->oauth_bridge_id
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

        $pin = generate_code(4, 2);

        $this->transactions->update(
            $row->transaction_id,
            array(
                'transaction_otp_pin' => $pin
            )
        );

        $email_address = $account->account_email_address;

        $this->send_otp_pin(
            "BambuPAY Resend Transfer OTP PIN",
            $email_address, 
            $pin
        );

        echo json_encode(
            array(
                'message' => "Successfully resend OTP PIN.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
    }
}

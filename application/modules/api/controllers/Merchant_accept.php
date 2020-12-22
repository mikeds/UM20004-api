<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Merchant_accept extends Merchant_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
    }

    public function index() {
        $post = $this->get_post();

        $type = isset($post["type"]) ? $post["type"] : "";

        if ($type == 'otc') {
            $this->otc();
            return;
        }

        $this->output->set_status_header(401);
    }

	public function cash_in() {
        $this->load->model("api/transactions_model", "transactions");

        $account                        = $this->_account;
        $transaction_type_group_id      = 3; // all cash in request
        $transaction_type_user_to       = 2; // all merchant
        $post                           = $this->get_post();

        $admin_oauth_bridge_id          = $account->oauth_bridge_parent_id;
        $merchant_oauth_bridge_id       = $account->merchant_oauth_bridge_id;
        $account_oauth_bridge_id        = $account->account_oauth_bridge_id;
        $merchant_balance               = $this->decrypt_wallet_balance($account->wallet_balance);

        $merchant_no                    = $account->merchant_number;

        if (!isset($post["sender_ref_id"])) {
            die();
        }

        $sender_ref_id = $post['sender_ref_id'];

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id,
                'transaction_type_group_id' => $transaction_type_group_id,
                'transaction_type_user_to'  => $transaction_type_user_to,
                'transaction_otp_status'    => 1,
                'transaction_status'        => 0
            ),
            array(),
            array(
                array(
                    'table_name'    => 'transaction_types',
                    'condition'     => 'transaction_types.transaction_type_id = transactions.transaction_type_id'
                ),
                array(
                    'table_name'    => 'client_accounts',
                    'condition'     => 'client_accounts.oauth_bridge_id = transactions.transaction_requested_by'
                )
            )
        )->row();

        if ($row == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid or expired Sender Ref ID."
                )
            );
            die();
        }

        $expiration_date = $row->transaction_date_expiration;

        if (strtotime($expiration_date) < strtotime($this->_today)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Cash-in request is expired."
                )
            );
            die();
        }

        $transaction_id = $row->transaction_id;

        $amount			= 0;
        $fee			= 0;
        $total_amount 	= 0;

        $amount = $row->transaction_amount;
        $fee 	= $row->transaction_fee;

        $total_amount 	= $amount + $fee;

        if ($merchant_balance < $total_amount) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "insufficient balance."
                )
            );
            die();
        }

        $debit_amount	= $amount + $fee;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        $total_amount   = $amount + $fee;

        $debit_total_amount 	= 0 - $debit_amount; // make it negative
        $credit_total_amount	= $credit_amount;

        $debit_wallet_address		= $this->get_wallet_address($merchant_oauth_bridge_id);
        $credit_wallet_address	    = $this->get_wallet_address($row->oauth_bridge_id);
        
        if ($credit_wallet_address == "" || $debit_wallet_address == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Cannot find wallet, Please contact system administrator."
                )
            );
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

        // do income sharing
        $this->distribute_income_shares(
			$transaction_id,
			$merchant_no,
			$fee_amount
		);

        $this->transactions->update(
            $transaction_id,
            array(
                'transaction_status' 		=> 1,
                'transaction_date_approved'	=> $this->_today,
                'transaction_requested_to'  => $account_oauth_bridge_id
            )
        );

        echo json_encode(
            array(
                'message'   => "Successfully accepted cash-in",
                'response'  => array(
                    'tx_amount'         => $amount,
                    'tx_fee'            => $fee,
                    'tx_total_amount'   => $total_amount,
                    'transaction_id'    => $row->transaction_id,
                    'sender_ref_id'     => $row->transaction_sender_ref_id,
                    'timestamp'         => $this->_today
                )
            )
        );
    }
}

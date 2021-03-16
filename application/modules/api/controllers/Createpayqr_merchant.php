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
        $this->load->model("api/client_accounts_model", "clients");

        $legder_desc            = "createpayqr";
        $account                = $this->_account;
        $transaction_type_id    = "txtype_createpayqr1";
		$post                   = $this->get_post(); // get post

        $admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;
		$account_oauth_bridge_id    = $account->account_oauth_bridge_id;
        $merchant_oauth_bridge_id	= $account->merchant_oauth_bridge_id;

        if (!isset($post["sender_ref_id"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Sender Ref. ID is required!"
                )
            );
            die();
        }

        $sender_ref_id = $post["sender_ref_id"];

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
                    'error_description' => "Invalid Sender Ref. No."
                )
            );
            die();
        }

        if ($row->transaction_status == 1 || $row->transaction_status == 2) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Transaction is already done/cancelled!"
                )
            );
            die();
        }

        if (strtotime($row->transaction_date_expiration) < strtotime($this->_today)) {
            $this->transactions->update(
                $row->transaction_id,
                array(
                    'transaction_status' => 2
                )
            );

            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Transaction is expired!"
                )
            );
            die();
        }

        $client_oauth_bridge_id = $row->transaction_requested_by;

        // get client balance
        $row_client = $this->clients->get_datum(
            '',
            array(
                'client_accounts.oauth_bridge_id' => $client_oauth_bridge_id
            ),
            array(),
            array(
                array(
                    'table_name' 	=> 'wallet_addresses',
                    'condition'		=> 'wallet_addresses.oauth_bridge_id = client_accounts.oauth_bridge_id'
                )
            )
        )->row();

        if ($row_client == "") {
            $this->transactions->update(
                $row->transaction_id,
                array(
                    'transaction_status' => 2
                )
            );

            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Transaction!"
                )
            );
            die();
        }

        // get client balance
        $client_balance = $this->decrypt_wallet_balance($row_client->wallet_balance);

        // get transaction details
        $transaction_id = $row->transaction_id;
        $amount         = $row->transaction_amount;
        $fee            = $row->transaction_fee;

        $debit_from     = $client_oauth_bridge_id; // client oauth_bridge_id
        $credit_to      = $merchant_oauth_bridge_id; 

        // create ledger
        $debit_amount	= $amount + $fee;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        if ($client_balance < $debit_amount) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Insufficient Balance!"
                )
            );
            die();
        }

        $debit_oauth_bridge_id 	= $debit_from;
        $credit_oauth_bridge_id = $credit_to;

        $this->create_ledger(
            $legder_desc, 
            $transaction_id, 
            $amount, 
            $fee,
            $debit_oauth_bridge_id, 
            $credit_oauth_bridge_id
        );

        // update
        $this->transactions->update(
            $transaction_id,
            array(
                'transaction_status'        => 1,
                'transaction_requested_to'  => $merchant_oauth_bridge_id,
                'transaction_approved_by'   => $merchant_oauth_bridge_id,
                'transaction_date_approved' => $this->_today
            )
        );

        echo json_encode(
            array(
                'message' => "Successfully accepted CreatePayQR.",
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

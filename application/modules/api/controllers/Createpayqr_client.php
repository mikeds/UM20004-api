<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Createpayqr_client extends Client_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
	}

    public function accept() {
        $this->load->model("api/transactions_model", "transactions");

        $legder_desc            = "createpayqr";
        $account                = $this->_account;
        $transaction_type_id    = "txtype_createpayqr1"; // scanpayqr
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $client_oauth_bridge_id    = $account->oauth_bridge_id;
        $client_balanace           = $this->decrypt_wallet_balance($account->wallet_balance);

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

        if ($row->transaction_status == 1 || $transaction_status == 2) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Transaction is already done/cancelled!"
                )
            );
            die();
        }

        if (strtotime($row->transaction_expiration_date) < strtotime($this->_today)) {
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

        // get transaction details
        $transaction_id = $row->transaction_id;
        $amount         = $row->transaction_amount;
        $fee            = $row->transaction_fee;

        $debit_from     = $client_oauth_bridge_id;
        $credit_to      = $row->transaction_requested_by; // merchant oauth_bridge_id
        
        // create ledger
        $debit_amount	= $amount + $fee;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        if ($client_balanace < $debit_amount) {
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
                'transaction_approved_by'   => $client_oauth_bridge_id,
                'transaction_date_approved' => $this->_today
            )
        );

        echo json_encode(
            array(
                'message' => "Successfully Accepted CreatePayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
    }
}

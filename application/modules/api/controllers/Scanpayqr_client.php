<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Scanpayqr_client extends Client_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
        }
	}

	public function accept() {
        $this->load->model("api/transactions_model", "transactions");
        $this->load->model("api/merchant_accounts_model", "merchant_accounts");

        $legder_desc            = "scanpayqr";
        $account                = $this->_account;
        $transaction_type_id    = "txtype_scanpayqr1"; // scanpayqr
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
                'transaction_type_id'       => $transaction_type_id,
                'transaction_status'        => 0
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
                    'error_description' => "Refference ID is expired or invalid."
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
        $debit_from = $client_oauth_bridge_id;
        $credit_to  = $row->merchant_oauth_bridge_id;

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

        $debit_oauth_bridge_id 	= $debit_from;
        $credit_oauth_bridge_id = $credit_to;

        // create ledger
        $balances = $this->create_ledger(
            $legder_desc, 
            $transaction_id, 
            $amount, 
            $fee,
            $debit_oauth_bridge_id, 
            $credit_oauth_bridge_id
        );

        $this->transactions->update(
            $row->transaction_id,
            array(
                'transaction_status'        => 1,
                'transaction_requested_to'  => $debit_oauth_bridge_id,
                'transaction_approved_by'   => $debit_oauth_bridge_id,
                'transaction_date_approved' => $this->_today
            )
        );

        // do income sharing
        $this->distribute_income_shares(
            $transaction_id
        );

        // send notification to receiver client
        $receiver_oauth_bridge_id = $debit_oauth_bridge_id; // debit to client

        $merchant_row = $this->merchant_accounts->get_datum(
            '',
            array(
                'merchants.oauth_bridge_id' => $receiver_oauth_bridge_id
            ),
            array(),
            array(
                array(
                    'table_name' 	=> 'merchants',
                    'condition'		=> 'merchants.merchant_number = merchant_accounts.merchant_number'
                ),
                array(
                    'table_name' 	=> 'wallet_addresses',
                    'condition'		=> 'wallet_addresses.oauth_bridge_id = merchants.oauth_bridge_id'
                )
            )
        )->row();

        if ($merchant_row != "") {
            $m_mobile_no        = $merchant_row->merchant_mobile_no;
            $m_email_address    = $merchant_row->merchant_email_address;
            $merchant_balance   = $this->decrypt_wallet_balance($merchant_row->wallet_balance);

            $mobile_no          = $account->account_mobile_no;
            $email_address      = $account->account_email_address;

            $amount     = number_format($debit_amount, 2, '.', '');
            $m_amount   = number_format($credit_amount, 2, '.', '');

            $merchant_balance   = number_format($merchant_balance, 2, '.', '');

            // message to client
            $title      = "BambuPAY - CreatePayQR";
            $message    = "Your payment of PHP {$amount} to {$m_mobile_no} has been successfully processed on {$this->_today}. Ref No. {$sender_ref_id}";

            $this->_send_sms($mobile_no, $message);
            $this->_send_email($email_address, $title, $message);

            // message to merchant
            $message    = "You have received PHP {$m_amount} on {$this->_today}. New balance is PHP {$merchant_balance} Ref No. {$sender_ref_id}";

            $this->_send_sms($m_mobile_no, $message);
            $this->_send_email($m_email_address, $title, $message);
        }

        echo json_encode(
            array(
                'message' => "Successfully accepted CreatePayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'amount'        => $amount,
                    'fee'           => $fee,
                    'total_amount'  => $total_amount,
                    'timestamp'     => $this->_today
                )
            )
        );
	}
}

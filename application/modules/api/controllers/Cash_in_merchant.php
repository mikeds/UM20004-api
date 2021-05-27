<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cash_in_merchant extends Merchant_Controller {

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

	public function accept() {
        $this->load->model("api/transactions_model", "transactions");
        $this->load->model("api/client_accounts_model", "clients");

        $legder_desc                    = "cash_in";
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
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Sender Ref. ID is required!"
                )
            );
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

        // GET ROW DATA
        $transaction_id         = $row->transaction_id;
        $transaction_type_id    = $row->transaction_type_id;
        $client_oauth_bridge_id = $row->oauth_bridge_id;
        $expiration_date        = $row->transaction_date_expiration;
        $amount                 = $row->transaction_amount;
        $fee 	                = $row->transaction_fee;

        if (strtotime($expiration_date) < strtotime($this->_today)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Cash-in request is expired."
                )
            );
            die();
        }

        // GET NEW FEE
        $fee = $this->get_fee(
            $amount,
            $transaction_type_id,
            $merchant_oauth_bridge_id
        );

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

        $debit_oauth_bridge_id 	= $merchant_oauth_bridge_id;
        $credit_oauth_bridge_id = $client_oauth_bridge_id; // credit to client

        $balances = $this->create_ledger(
            $legder_desc, 
            $transaction_id, 
            $amount, 
            $fee,
            $debit_oauth_bridge_id, 
            $credit_oauth_bridge_id
        );

        $this->transactions->update(
            $transaction_id,
            array(
                'transaction_fee'           => $fee,
                'transaction_total_amount'  => $total_amount,
                'transaction_status' 		=> 1,
                'transaction_date_approved'	=> $this->_today,
                'transaction_approved_by'   => $account_oauth_bridge_id,
                'transaction_requested_to'  => $account_oauth_bridge_id
            )
        );

        // do income sharing
        $this->distribute_income_shares(
            $transaction_id
        );

        // send notification to receiver client
        $receiver_oauth_bridge_id = $row->oauth_bridge_id;

        $client_row = $this->clients->get_datum(
            '',
            array(
                'client_accounts.oauth_bridge_id' => $receiver_oauth_bridge_id
            ),
            array(),
            array(
                array(
					'table_name' 	=> 'wallet_addresses',
					'condition'		=> 'wallet_addresses.oauth_bridge_id = client_accounts.oauth_bridge_id'
				)
            )
        )->row();

        if ($client_row != "") {
            $client_email       = $client_row->account_email_address;
            $client_mobile_no   = $client_row->account_mobile_no;
            $client_balance     = $this->decrypt_wallet_balance($client_row->wallet_balance);

            $amount         = number_format($amount, 2, '.', '');
            $client_balance = number_format($client_balance, 2, '.', '');

            $title      = "BambuPay - Cash In";
            $message    = "You have received PHP {$amount} on {$this->_today}. New balance is PHP {$client_balance} Ref No. {$sender_ref_id}";

           $this->_send_sms($client_mobile_no, $message);
           $this->_send_email($client_email, $title, $message);

           // Send email notification to merchant
           $data['post'] = array(
                'sender_ref_id'     => $row->transaction_sender_ref_id,
                'tx_amount'         => $amount,
                'timestamp'         => $this->_today,
                'balance'           => $merchant_balance
            );
           $merchant_email_notif = $this->load->view('templates/cash_in_merchant_email_notif', $data,true);
           $this->_send_email($account->merchant_email_address, $title, $merchant_email_notif);
        
        }

        echo json_encode(
            array(
                'message'   => "Successfully accepted cash-in",
                'response'  => array(
                    'transaction_id'    => $row->transaction_id,
                    'sender_ref_id'     => $row->transaction_sender_ref_id,
                    'tx_amount'         => $amount,
                    'tx_fee'            => $fee,
                    'tx_total_amount'   => $total_amount,
                    'timestamp'         => $this->_today
                )
            )
        );
    }
}

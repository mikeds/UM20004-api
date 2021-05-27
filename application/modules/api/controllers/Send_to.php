<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Send_to extends Client_Controller {

	public function after_init() {
		if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function direct() {
		$this->load->model("api/client_accounts_model", "client_accounts");
		
        $account                = $this->_account;

        $transaction_type_id    = "txtype_transfer1"; // C2C - Client to Client
		$post                   = $this->get_post();
        $username				= $post['username'];
        $message                = isset($post['message']) ? $post['message'] : "";

        if (!isset($post["amount"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Please provide amount."
                )
            );
            die();
        }

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
        
        if ($amount < 0) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Amount."
                )
            );
            die();
        }

		$row_email = $this->client_accounts->get_datum(
			'',
			array(
                'account_email_address' 	=> $username,
                'account_status'            => 1,
                // 'account_email_status'      => 1
                // 'account_number !=' => $account->account_number
			)
        )->row();
        
        $mobile_no = $this->filter_mobile_number($username);

		$row_mobile = $this->client_accounts->get_datum(
			'',
			array(
                'CONCAT(country_code, account_mobile_no) =' 	=> $mobile_no,
                'account_status'                                => 1,
                // 'account_email_status'                          => 1
                // 'account_number !=' => $account->account_number
            ),
            array(),
            array(
                array(
                    'table_name'    => 'countries',
                    'condition'     => 'countries.country_id = client_accounts.country_id',
                    'position'      => 'left'
                )
            )
        )->row();

        $row = $row_email != "" ? $row_email : $row_mobile;

		if ($row == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid mobile or email address."
                )
            );
			die();
        }

        if ($row->account_number == $account->account_number) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Cannot send to your self."
                )
            );
            die();
        }
        
        $admin_oauth_bridge_id		= $account->oauth_bridge_parent_id;
        $debit_oauth_bridge_id    	= $account->account_oauth_bridge_id;
        $credit_oauth_bridge_id	    = $row->oauth_bridge_id;
        $balance                    = $this->decrypt_wallet_balance($account->wallet_balance);
		
        $fee = 0;
        $total_amount = $amount + $fee;

        if ($balance < $total_amount) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "insufficient balance."
                )
            );
            die();
        }

		$tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $debit_oauth_bridge_id, 
            $credit_oauth_bridge_id,
            null,
            60,
            $message
        );

        // if no OTP -- for testing only

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];

        // create ledger
        $debit_amount	= $amount;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        $debit_total_amount 	= 0 - $debit_amount; // make it negative
        $credit_total_amount	= $credit_amount;

        $debit_wallet_address		= $this->get_wallet_address($debit_oauth_bridge_id);
        $credit_wallet_address	    = $this->get_wallet_address($credit_oauth_bridge_id);
        
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

        // send notification to receiver client
        $receiver_oauth_bridge_id = $credit_oauth_bridge_id;

        $client_row = $this->client_accounts->get_datum(
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

            $sender_mobile_no   = $account->account_mobile_no;

            $amount         = number_format($amount, 2, '.', '');
            $client_balance = number_format($client_balance, 2, '.', '');

            // Send email & sms to Receiver //
            $title      = "BambuPAY - Send Money";
            $message    = "You have received PHP {$amount} from {$sender_mobile_no} on {$this->_today}. New balance is PHP {$client_balance} Ref No. {$sender_ref_id}";

            $this->_send_sms($client_mobile_no, $message);
            $this->_send_email($client_email, $title, $message);

            // Send email notif to Sender //
            $data['post'] = array(
                'amount'        => $amount,
                'timestamp'     => $this->_today,
                'receiver'      => $sender_mobile_no,
                'fee'           => $fee,
                'balance'       => $balance,
                'sender_ref_id' => $sender_ref_id,
                'total_amount'  => $total_amount

            );
            $sender_email_notif = $this->load->view('templates/sender_email_notif', $data,true);
            $this->_send_email($account->account_email_address, $title, $sender_email_notif);
        }

        $this->transactions->update(
            $transaction_id,
            array(
                'transaction_status' 		=> 1,
                'transaction_date_approved' => $this->_today
            )
        );

        // $email_address = $account->account_email_address;

        // $this->send_otp_pin(
        //     "BambuPAY Transfer OTP PIN",
        //     $email_address, 
        //     $pin
        // );
        
        echo json_encode(
            array(
                'message' => "Successfully created transfered!",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'timestamp'     => $this->_today
                )
            )
        );
	}
}

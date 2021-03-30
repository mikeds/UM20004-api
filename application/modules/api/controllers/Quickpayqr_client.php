<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quickpayqr_client extends Client_Controller {

	public function after_init() {
		$this->load->model("api/transactions_model", "transactions");
		$this->load->model("api/merchant_accounts_model", "merchant_accounts");
		$this->load->model("api/client_accounts_model", "client_accounts");
	}

	public function scan($qr_code) {
		if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
		}

		$row = $this->merchant_accounts->get_datum(
            '',
            array(
                'MD5(merchant_accounts.oauth_bridge_id)'  => $qr_code
			),
			array(),
			array(
				array(
					'table_name'	=> 'merchants',
					'condition'		=> 'merchants.merchant_number = merchant_accounts.merchant_number'
				)
			)
        )->row();

        if ($row == "") {
            // unauthorized access
		    $this->output->set_status_header(401);
            return;
		}
		
        echo json_encode(
            array(
                'message' => "Successfully scanned merchant QR Code.",
                'response' => array(
					'account_number'		=> $row->account_number,
					'account_fname'			=> $row->merchant_fname,
					'account_mname'			=> $row->merchant_mname,
					'account_lname'			=> $row->merchant_lname,
					'account_email_address' => $row->merchant_email_address
                )
            )
        );
	}

	public function accept() {
		if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}

		$legder_desc            = "quickpayqr";
		$account                = $this->_account;
        $transaction_type_id    = "txtype_quickpayqr1"; // scanpayqr
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     	= $account->oauth_bridge_parent_id;
        $client_oauth_bridge_id    	= $account->oauth_bridge_id;
		$client_balance           	= $this->decrypt_wallet_balance($account->wallet_balance);
		
		$merchant_account_number	= isset($post['account_number']) ? $post['account_number'] : "";
		
		$amount = 0;
        $fee = 0;

		if ($merchant_account_number == "") {
			echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Merchant account no. is required!"
                )
            );
			die();
		}

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

		$row = $this->merchant_accounts->get_datum(
			'',
			array(
				'account_number' => $merchant_account_number
			),
			array(),
			array(
				array(
					'table_name'	=> 'merchants',
					'condition'		=> 'merchants.merchant_number = merchant_accounts.merchant_number'
				)
			),
			array(
				'*',
				'merchants.oauth_bridge_id as merchant_oauth_bridge_id',
				'merchant_accounts.oauth_bridge_id as merchant_account_oauth_bridge_id'
			)
		)->row();

		if ($row == "") {
			echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Cannot find merchant."
                )
            );
			die();
		}

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

		$merchant_oauth_bridge_id			= $row->merchant_oauth_bridge_id;
		$merchant_account_oauth_bridge_id 	= $row->merchant_account_oauth_bridge_id;
		
		$tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
			$client_oauth_bridge_id,
            $merchant_account_oauth_bridge_id
		);
		
		$transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];

		// create ledger
		$debit_from     = $client_oauth_bridge_id; // client oauth_bridge_id
        $credit_to      = $merchant_oauth_bridge_id; 

        $debit_oauth_bridge_id 	= $debit_from;
        $credit_oauth_bridge_id = $credit_to;

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
				'transaction_status'        => 1,
				'transaction_approved_by'  	=> $client_oauth_bridge_id,
				'transaction_date_approved' => $this->_today
			)
		);

		// send notify
		$c_mobile_no        = $account->account_mobile_no;
		$c_email_address    = $account->account_email_address;

		$debit_amount       = number_format($debit_amount, 2, '.', '');
		$credit_amount      = number_format($credit_amount, 2, '.', '');

		$m_mobile_no        = $row->merchant_mobile_no;
		$m_email_address    = $row->merchant_email_address;
		
		$balance            = isset($balances['credit_new_balance']['new_balance']) ? $balances['credit_new_balance']['new_balance'] : "";

		// message to client
		$title      = "BambuPAY - QuickQR";
		$message    = "Your payment of PHP {$debit_amount} to {$m_mobile_no} has been successfully processed on {$this->_today}. Ref No. {$sender_ref_id}";

		$this->_send_sms($c_mobile_no, $message);
		$this->_send_email($c_email_address, $title, $message);

		// message to merchant
		$message    = "You have received PHP {$credit_amount} on {$this->_today}. New balance is PHP {$balance} Ref No. {$sender_ref_id}";

		$this->_send_sms($m_mobile_no, $message);
		$this->_send_email($m_email_address, $title, $message);

		echo json_encode(
            array(
                'message' => "Successfully transfer to merchant via QuickPayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
					'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
					'timestamp'     => $this->_today
                )
            )
        );
	}
}

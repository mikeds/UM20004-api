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

		$account                = $this->_account;
        $transaction_type_id    = "txtype_quickpayqr1"; // scanpayqr
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     	= $account->oauth_bridge_parent_id;
        $client_oauth_bridge_id    	= $account->oauth_bridge_id;
		$client_balanace           	= $this->decrypt_wallet_balance($account->wallet_balance);
		
		$merchant_account_number	= isset($post['account_number']) ? $post['account_number'] : "";
		
		$amount = 0;
        $fee = 0;

		if ($merchant_account_number == "") {
			die();
		}

        if (!isset($post["amount"])) {
            die();
        }

        if (is_decimal($amount)) {
            die();
        }

        if (!is_numeric($amount)) {
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

		$amount			= $post["amount"];
        $total_amount   = $amount + $fee;

        if ($client_balanace < $total_amount) {
            echo json_encode(
                array(
                    'error'             => true,
					'error_description' => "Insufficient balance.",
					'current_balance'	=> $client_balanace
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
				"quickpayqr_debit", 
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
				"quickpayqr_credit", 
				$transaction_id, 
				$debit_wallet_address, // debit from wallet address
				$credit_wallet_address, // credit to wallet address
				$credit_new_balances
			);
		}

		$this->transactions->update(
			$transaction_id,
			array(
				'transaction_status'        => 1,
				'transaction_approved_by'  	=> $client_oauth_bridge_id,
				'transaction_date_approved' => $this->_today
			)
		);

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

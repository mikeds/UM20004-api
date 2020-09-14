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

        $transaction_type_id    = "txtype_1004011"; // C2C - Client to Client
		$post                   = $this->get_post();
		$username				= $post['email_address'];

        if (!isset($post["amount"])) {
            die();
        }

        $amount = $post["amount"];

        if (is_decimal($amount)) {
            die();
        }

        if (!is_numeric($amount)) {
            die();
		}

		$row = $this->client_accounts->get_datum(
			'',
			array(
                'account_username' 	=> $username,
                // 'account_number !=' => $account->account_number
			)
		)->row();

		if ($row == "") {
			die();
        }

        if ($row->account_number == $account->account_number) {
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
            $credit_oauth_bridge_id
        );

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];

        $email_address = $account->account_email_address;

        $this->send_otp_pin(
            "BambuPAY Transfer OTP PIN",
            $email_address, 
            $pin
        );
        
        echo json_encode(
            array(
                'message' => "Successfully created transfer, OTP Pin sent to your email.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
	}
}

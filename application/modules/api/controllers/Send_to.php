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
        $transaction_type_id    = "TXTYPE_1004011"; // C2C - Client to Client
		$post                   = $this->get_post();
		$username				= $post['email_address'];

        if (!isset($post["amount"])) {
            die();
        }

        if (is_decimal($amount)) {
            die();
        }

        if (!is_numeric($amount)) {
            die();
		}

		$row = $this->client_accounts->get_datum(
			'',
			array(
				'account_username' 	=> $username
			)
		)->row();

		if ($row == "") {
			generate_error_message("E004-2");
		}

		$admin_oauth_bridge_id		= $account->oauth_bridge_parent_id;
        $sender_oauth_bridge_id    	= $account->account_oauth_bridge_id;
		$receiver_oauth_bridge_id	= $row->oauth_bridge_id;
		
        $amount = $post["amount"];
        $fee = 0;

		$tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $sender_oauth_bridge_id, 
            $receiver_oauth_bridge_id
        );

        $pin            = $tx_row['pin'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $email_address = $account->account_email_address;

        $this->send_otp_pin(
            "Transfer OTP PIN",
            $email_address, 
            $pin
        );
        
        echo json_encode(
            array(
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
	}
}

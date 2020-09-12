<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Top_up extends Merchant_Controller {

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

	public function otc() {
        $account                = $this->_account;
        $transaction_type_id    = "TXTYPE_1002011";
        $post                   = $this->get_post();

        $account_oauth_bridge_id    = $account->account_oauth_bridge_id;
        $admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;

        $amount = 0;
        $fee = 0;

        if (!isset($post["amount"])) {
            die();
        }

        if (is_decimal($amount)) {
            die();
        }

        if (!is_numeric($amount)) {
            die();
        }
        
        $amount = $post["amount"];

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $account_oauth_bridge_id, 
            $admin_oauth_bridge_id
        );

        $pin            = $tx_row['pin'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $email_address = $account->merchant_email_address;

        $this->send_otp_pin(
            "BambuPAY TOP-UP OTP PIN",
            $email_address, 
            $pin
        );
        
        echo json_encode(
            array(
                'message' => "Successfully created top-up, OTP Pin sent to your email.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
    }
}

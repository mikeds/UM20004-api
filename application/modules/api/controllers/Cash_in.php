<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cash_in extends Client_Controller {

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
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin1"; // cash-in
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $account_oauth_bridge_id   = $account->account_oauth_bridge_id;

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

        $fee = 0;
        $total_amount = $amount + $fee;

        $fee = $this->get_fee(
            $amount,
            $transaction_type_id,
            $admin_oauth_bridge_id
        );

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $account_oauth_bridge_id, 
            ""
        );

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];
        $pin            = $tx_row['pin'];

        $email_address = $account->account_email_address;

        $this->send_otp_pin(
            "BambuPAY CASH-IN OTP PIN",
            $email_address, 
            $pin
        );
        
        echo json_encode(
            array(
                'message' =>  "Successfully created cash-in, OTP Pin sent to your email.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
    }
}

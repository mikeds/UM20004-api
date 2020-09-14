<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Otp_top_up extends Merchant_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
    }

	public function activation() {
        $this->load->model("api/transactions_model", "transactions");
        
        $transaction_type_id    = "txtype_1002011";

        $account    = $this->_account;

        $post           = $this->get_post();
        
        if (!isset($post["sender_ref_id"])) {
            die();
        }

        if (!isset($post["pin"])) {
            die();
        }

        $sender_ref_id = $post["sender_ref_id"];

        if (trim($sender_ref_id) == "") {
            die();
        }

        $pin = $post["pin"];

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id,
                'transaction_otp_pin'       => $pin,
                'transaction_otp_status'    => 0,
                'transaction_type_id'       => $transaction_type_id,
                'merchants.oauth_bridge_id' => $account->merchant_oauth_bridge_id
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
            )
        )->row();

        if ($row == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Refference ID or PIN."
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

        $this->transactions->update(
            $row->transaction_id,
            array(
                'transaction_otp_status' => 1
            )
        );

        echo json_encode(
            array(
                'message' => "Successfully activated OTP PIN.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
    }
    
    public function resend() {
        $this->load->model("api/transactions_model", "transactions");

        $account    = $this->_account;
        
        $post       = $this->get_post();
        
        if (!isset($post["sender_ref_id"])) {
            die();
        }

        $sender_ref_id = $post["sender_ref_id"];

        if (trim($sender_ref_id) == "") {
            die();
        }

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id,
                'transaction_otp_status'    => 0,
                'merchants.oauth_bridge_id' => $account->merchant_oauth_bridge_id
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
            )
        )->row();

        if ($row == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Refference ID."
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

        $pin = generate_code(4, 2);

        $this->transactions->update(
            $row->transaction_id,
            array(
                'transaction_otp_pin' => $pin
            )
        );

        $email_address = $account->merchant_email_address;

        $this->send_otp_pin(
            "BambuPAY Resend TOP-UP OTP PIN",
            $email_address, 
            $pin
        );

        echo json_encode(
            array(
                'message' => "Successfully resend TOP-UP OTP PIN.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
    }
}

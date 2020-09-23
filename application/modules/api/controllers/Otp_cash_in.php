<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Otp_cash_in extends Client_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
    }

	public function activation() {
        $this->load->model("api/transactions_model", "transactions");
        
        $transaction_type_id = "txtype_cashin1"; // cash-in

        $account    = $this->_account;

        $post       = $this->get_post();
        
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
                'transaction_requested_by'  => $account->oauth_bridge_id
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
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}"
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
                'transaction_requested_by'  => $account->oauth_bridge_id
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

        $email_address = $account->account_email_address;

        $this->send_otp_pin(
            "BambuPAY Resend CASH-IN OTP PIN",
            $email_address, 
            $pin
        );

        echo json_encode(
            array(
                'message' => "Successfully resend OTP PIN.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id
                )
            )
        );
    }
}

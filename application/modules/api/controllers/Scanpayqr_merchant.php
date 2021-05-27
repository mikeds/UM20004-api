<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Scanpayqr_merchant extends Merchant_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function create() {
		$account                = $this->_account;
        $transaction_type_id    = "txtype_scanpayqr1";
		$post                   = $this->get_post();
		
		$account_oauth_bridge_id    = $account->account_oauth_bridge_id;
		$merchant_oauth_bridge_id	= $account->merchant_oauth_bridge_id;
		$admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;

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
		
        // get fee
        $fee = $this->get_fee(
            $amount,
            $transaction_type_id,
            $merchant_oauth_bridge_id
        );

        $total_amount = $amount + $fee;

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

        // Send email notification to merchant
        $data['post'] = array(
            'sender_ref_id' => $sender_ref_id,
            'amount'        => $amount,
            'fee'           => $fee,
            'total_amount'  => $total_amount,
            'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
            'timestamp'     => $this->_today
        );
        $title      = "BambuPAY - ScanPayQR";
        $merchant_email_notif = $this->load->view('templates/scanpayqr_merchant_email_notif', $data,true);
        $this->_send_email($account->merchant_email_address, $title, $merchant_email_notif);
        
        echo json_encode(
            array(
                'message' => "Successfully created ScanPayQR.",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'amount'        => $amount,
                    'fee'           => $fee,
                    'total_amount'  => $total_amount,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
	}
}

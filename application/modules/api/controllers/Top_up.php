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
        $this->load->model("api/transactions_model", "transactions");

        $account                = $this->_account;
        $transaction_type_id    = "TXTYPE_1002011";
        $post                   = $this->get_post();

        $admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;
        $account_oauth_bridge_id    = $account->account_oauth_bridge_id;

        $amount = 0;
        $fee = 0;

        if (!isset($post["amount"])) {
            echo "test";
            die();
        }

        if (is_decimal($amount)) {
            die();
        }

        if (!is_numeric($amount)) {
            die();
        }
        
        // expiration timestamp
        $minutes_to_add = 60;
        $time = new DateTime($this->_today);
        $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));
        $stamp = $time->format('Y-m-d H:i:s');


        $amount = $post["amount"];

        $total_amount = $amount + $fee;

        $data_insert = array(
            'transaction_amount' 		    => $amount,
            'transaction_fee'		        => $fee,
            'transaction_total_amount'      => $total_amount,
            'transaction_type_id'           => $transaction_type_id,
            'transaction_requested_by'      => $account_oauth_bridge_id,
            'transaction_requested_to'	    => $admin_oauth_bridge_id,
            'transaction_created_by'        => $account_oauth_bridge_id,
            'transaction_date_created'      => $this->_today,
            'transaction_date_expiration'   => $stamp
        );

        // generate sender ref id
        $sender_ref_id = $this->generate_code(
            $data_insert,
            "crc32"
        );

        $data_insert = array_merge(
            $data_insert,
            array(
                'transaction_sender_ref_id' => $sender_ref_id
            )
        );

        // generate transaction id
        $transaction_id = $this->generate_code(
            $data_insert,
            "crc32"
        );

        $data_insert = array_merge(
            $data_insert,
            array(
                'transaction_id' => $transaction_id
            )
        );

        $this->transactions->insert(
            $data_insert
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

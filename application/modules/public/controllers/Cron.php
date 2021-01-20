<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron extends Api_Controller {
		
	public function after_init() {}

    public function paynamics() {
        $this->load->model("api/transactions_model", "transactions");

        $results = $this->transactions->get_data_where_in(
            array('*'), 
            array(
                'transaction_status' => '0'
            ), 
            array(
                'field' => 'transaction_type_id',
                'data'  => array(
                    'txtype_cashin3',
                    'txtype_cashin4',
                    'txtype_cashin5',
                    'txtype_cashin6',
                    'txtype_cashin7',
                    'txtype_cashin8'
                )
            )
        );

        foreach($results as $row) {
            $sender_ref_id = $row['transaction_sender_ref_id'];
            $this->paynamics_query_request($sender_ref_id);
        }
    }

    private function paynamics_query_request($sender_ref_id) {
        $merchantid = PAYNAMICSMID;
        $mkey       = PAYNAMICSMKEY;

        $request_id = $sender_ref_id . "x" . date('YmdHis', strtotime($this->_today));
        $raw        = $merchantid . $request_id . $sender_ref_id . $mkey;

        $signature  = hash("sha512", $raw);

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://payin.payserv.net/paygate/transactions/query',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "request_id": "'. $request_id .'",
            "org_trxid2": "'. $sender_ref_id .'",
            "signature": "'. $signature .'"
        }',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Basic ' . PAYNAMICSBASICAUTH
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $response_arr = json_decode($response);

        if (isset($response_arr->response_code)) {
            $response_code = $response_arr->response_code;

            if ($response_code == 'GR003') {
                // pending
            } else if ($response_code == 'GR001' || $response_code == 'GR002') {
                $this->bp_to_client_paynamics_credit($sender_ref_id);
            } else {
                // do cancel
                $this->bp_to_client_paynamics_cancel($sender_ref_id);
            }
        }
    }

    private function bp_to_client_paynamics_cancel($sender_ref_id) {
        $this->load->model("api/transactions_model", "transactions");

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id
            )
        )->row();

        if ($row == "") {
            return;
        }

        $transaction_id = $row->transaction_id;

        $this->transactions->update(
            $transaction_id,
            array(
                'transaction_status'        => 2
            )
        );
    }

    private function bp_to_client_paynamics_credit($sender_ref_id) {
        $this->load->model("api/transactions_model", "transactions");

        $row = $this->transactions->get_datum(
            '',
            array(
                'transaction_sender_ref_id' => $sender_ref_id
            )
        )->row();

        if ($row == "") {
            return;
        }

        $transaction_id = $row->transaction_id;

        $amount = $row->transaction_amount;
        $fee    = $row->transaction_fee;

        $client_oauth_bridge_id = $row->transaction_requested_by;
        $admin_oauth_bridge_id  = $row->transaction_requested_to;

        $debit_amount	= $amount + $fee;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        $total_amount   = $amount + $fee;

        $debit_total_amount 	= 0 - $debit_amount; // make it negative
        $credit_total_amount	= $credit_amount;

        $debit_wallet_address		= $this->get_wallet_address($admin_oauth_bridge_id);
        $credit_wallet_address	    = $this->get_wallet_address($client_oauth_bridge_id);

        if ($debit_wallet_address == "" || $credit_wallet_address == "") {
            return;
        }

        $debit_new_balances = $this->update_wallet($debit_wallet_address, $debit_total_amount);
        if ($debit_new_balances) {
            // record to ledger
            $this->new_ledger_datum(
                "cash_in_debit", 
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
                "cash_in_credit", 
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
                // 'transaction_approved_by'   => $admin_oauth_bridge_id
            )
        );
    }
}





















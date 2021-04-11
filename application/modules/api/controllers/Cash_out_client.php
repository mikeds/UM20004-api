<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cash_out_client extends Client_Controller {

	public function after_init() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			$this->output->set_status_header(401);
			die();
		}
    }

    public function index() {
        $post = $this->get_post();

        $type = isset($post["type"]) ? $post["type"] : "";

        if ($type == 'ubp') {
            $this->ubp();
            return;
        }

        $this->output->set_status_header(401);
    }

    private function ubp() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $legder_desc            = "cash_out_ubp";
        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashout2"; // cash-out
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $account_oauth_bridge_id   = $account->account_oauth_bridge_id;

        $account_number = isset($post["account_no"]) ? $post["account_no"] : "";
        $fname		    = isset($post["first_name"]) ? $post["first_name"] : "";
        $mname		    = isset($post["middle_name"]) ? $post["middle_name"] : "";
        $lname	        = isset($post["last_name"]) ? $post["last_name"] : "";
        $mobile_no      = isset($post["mobile_no"]) ? $post["mobile_no"] : "";

        $amount         = isset($post["amount"]) ? $post["amount"] : "";

        $bank_code      = isset($post["bank_code"]) ? $post["bank_code"] : "";
        $message        = isset($post["message"]) ? $post["message"] : "";

        if ($bank_code == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Bank code is required."
                )
            );
            die();
        }

        if ($fname == "" || $lname == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "First Name and Last Name are required."
                )
            );
            die();
        }
        
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

        $fee = 0;
        // GET NEW FEE
        $fee = $this->get_fee(
            $amount,
            $transaction_type_id
        );

        $total_amount 	= $amount + $fee;
        
        // check current balanace
        $wallet_balance_encrypted   = $account->wallet_balance;
        $wallet_balance             = $this->decrypt_wallet_balance($wallet_balance_encrypted);

        if ($wallet_balance < $total_amount) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Insufficient balance."
                )
            );
            die();
        }

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $account_oauth_bridge_id, // client
            $admin_oauth_bridge_id
        );

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $account_name = "{$fname} {$mname} {$lname}";
        $has_error_transfer = $this->ubp_request($sender_ref_id, $account_number, $account_name, $message, $debit_amount, $bank_code);

        if ($has_error_transfer) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => $has_error_transfer
                )
            );

            $this->transaction_id->update(
                $transaction_id,
                array(
                    'transaction_status' => 2 // cancel
                )
            );

            die();
        }

        $debit_oauth_bridge_id  = $account_oauth_bridge_id;
        $credit_oauth_bridge_id = $admin_oauth_bridge_id;

        $balances = $this->create_ledger(
            $legder_desc, 
            $transaction_id, 
            $amount, 
            $fee,
            $debit_oauth_bridge_id, 
            $credit_oauth_bridge_id
        );

        // do income sharing
        $this->distribute_income_shares(
			$transaction_id
        );
        
        $this->transactions->update(
            $transaction_id,
            array(
                'transaction_status' 		=> 1,
                'transaction_date_approved'	=> $this->_today,
                'transaction_requested_to'  => $admin_oauth_bridge_id,
                'transaction_approved_by'   => $account_oauth_bridge_id,
                'transaction_date_approved'	=> $this->_today
            )
        );

        echo json_encode(
            array(
                'message' =>  "Successfully cash-out!",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
    }

    private function ubp_request($sender_ref_id, $account_number, $account_name, $message, $amount, $bank_code) {
        $acces_token        = $this->input->get_request_header('ubp-access-token', TRUE);
        $tx_request_date    = date('Y-m-d\TH:i:s.', strtotime($this->_today)).gettimeofday()["usec"];
        $tx_request_date    = substr($tx_request_date, 0, 23);

        $amount = number_format($amount, 2, '.', '');

        if ($bank_code == "010419995") {
            // $this->ubp_to_ubp($acces_token, $sender_ref_id, $account_number, $account_name, $message, $amount, $tx_request_date);
            return false;
        } else {
           return $this->ubp_to_non_ubp($acces_token, $sender_ref_id, $account_number, $account_name, $message, $amount, $bank_code, $tx_request_date);
        }
    }

    private function ubp_to_ubp($acces_token, $sender_ref_id, $account_number, $account_name, $message, $amount, $tx_request_date) {
       $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => UBP_BASE_URL . 'partners/sb/partners/v3/transfers/single',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
        "senderRefId": "'. $sender_ref_id .'",
        "tranRequestDate": "'. $tx_request_date .'",
        "accountNo": "'. $account_number .'",
        "amount": {
            "currency": "PHP",
            "value": "'. $amount .'"
        },
        "remarks": "Transfer remarks",
        "particulars": "Transfer particulars",
        "info": [
                {
                    "index": 1,
                    "name": "Recipient",
                    "value": "'. $account_name .'"
                },
                {
                    "index": 2,
                    "name": "Message",
                    "value": "'. $message .'"
                }
            ]
        }',
        CURLOPT_HTTPHEADER => array(
                'x-ibm-client-id: ' . UBP_CLIENT_ID,
                'x-ibm-client-secret: ' . UBP_SECRET_ID,
                'authorization: Bearer ' . $acces_token,
                'x-partner-id: ' . UBP_PARTNER_ID,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;
    }

    private function ubp_to_non_ubp($acces_token, $sender_ref_id, $account_number, $account_name, $message, $amount, $bank_code, $tx_request_date) {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL =>  UBP_BASE_URL . 'partners/sb/partners/v3/instapay/transfers/single',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'{
          "senderRefId": "'. $sender_ref_id .'",
          "tranRequestDate": "'. $tx_request_date .'",
          "sender": {
            "name": "BambuPAY",
            "address": {
              "line1": "Commonwealth",
              "line2": "Quezon City",
              "city": "NCR",
              "province": 142,
              "zipCode": 1900,
              "country": 204
            }
          },
          "beneficiary": {
            "accountNumber": "'. $account_number .'",
            "name": "'. $account_name .'",
            "address": {
              "line1": "",
              "line2": "",
              "city": "",
              "province": "",
              "zipCode": "",
              "country": 204
            }
          },
          "remittance": {
            "amount": "'. $amount .'",
            "currency": "PHP",
            "receivingBank": '. $bank_code .',
            "purpose": 1001,
            "instructions": ""
          }
        }',
          CURLOPT_HTTPHEADER => array(
            'x-ibm-client-id: ' . UBP_CLIENT_ID,
            'x-ibm-client-secret: ' . UBP_SECRET_ID,
            'authorization: Bearer ' . $acces_token,
            'x-partner-id: ' . UBP_PARTNER_ID,
            'Content-Type: application/json'
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);

        $response_arr = json_decode($response);

        if (!isset($response_arr->senderRefId)) {
            if (isset($response_arr->error[0]->message)) {
                return $response_arr->error[0]->message;
            } else if (isset($response_arr->moreInformation)) {
                return $response_arr->moreInformation;
            } else if (isset($response_arr->errors[0]->description)) {
                return $response_arr->errors[0]->description;
            } else {
                return "Bank API ERROR.";
            }
        }

        return false;
    }
}

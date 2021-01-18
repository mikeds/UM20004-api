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

        if ($type == "otc") {
            $this->otc();
            return;
        } else if ($type == "cc") {
            $this->cc();
            return;
        } else if ($type == "gcash") {
            
        } else if ($type == "grab") {
            
        } else if ($type == "paymaya") {
            
        }

        // else if ($type == 'paynamics') {
        //     $this->paynamics();
        //     return;
        // }

        $this->output->set_status_header(401);
    }

    private function cc() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin4"; // cash-in
        $transaction_desc       = "BambuPAY cash-in via CC";
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $account_oauth_bridge_id   = $account->account_oauth_bridge_id;

        if (!isset($post["amount"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Amount."
                )
            );
            die();
        }

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

        $fee = 0;
        $total_amount = $amount + $fee;

        // $fee = $this->get_fee(
        //     $amount,
        //     $transaction_type_id,
        //     $admin_oauth_bridge_id
        // );

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $account_oauth_bridge_id, 
            ""
        );

        $fee            = number_format($fee, 2, '.', '');
        $amount         = number_format($amount, 2, '.', '');
        $total_amount   = number_format($total_amount, 2, '.', '');

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $request_id         = $sender_ref_id;
        $notification_url   = base_url() . "callback/paynamics/notification";
        $response_url       = base_url() . "callback/paynamics/response";
        $cancel_url         = base_url() . "callback/paynamics/cancel";
        $pmethod            = "creditcard";
        $pchannel           = "creditcard";
        $payment_action     = "direct_otc";
        $collection_method  = "single_pay";
        $amount             = $total_amount;
        $currency           = "PHP";
        $descriptor_note    = "HOME";
        $payment_notification_status    = "1";
        $payment_notification_channel   = "";

        $fname  = "Leo";
        $lname  = "Trinidad";
        $mname  = "Ff";
        $email  = "marknel.pineda23@gmail.com";
        $phone  = "";
        $mobile = "";
        $dob    = "";

        $merchantid = PAYNAMICSMID;
        $mkey       = PAYNAMICSMKEY;

        $raw_trx = 
        $merchantid . 
        $request_id . 
        $notification_url .
        $response_url . 
        $cancel_url . 
        $pmethod . 
        $payment_action . 
        $collection_method .
        $amount . 
        $currency . 
        $descriptor_note . 
        $payment_notification_status . 
        $payment_notification_channel . 
        $mkey;

        $raw_customer_info =
        $fname . 
        $lname . 
        $mname . 
        $email . 
        $phone . 
        $mobile . 
        $dob . 
        $mkey;

        $signature_trx          = hash("sha512", $raw_trx);
        $signature_customer     = hash("sha512", $raw_customer_info);

        $transaction = array(
            "request_id"        => $request_id,
            "notification_url"  => $notification_url,
            "response_url"      => $response_url,
            "cancel_url"        => $cancel_url,
            "pmethod"           => $pmethod,
            "pchannel"          => $pchannel,
            "payment_action"    => $payment_action,
            "schedule"          => "",
            "collection_method" => $collection_method,
            "deferred_period"   => "",
            "deferred_time"     => "",
            "dp_balance_info"   => "",
            "amount"            => $amount,
            "currency"          => $currency,
            "descriptor_note"   => $descriptor_note,
            "mlogo_url"         => "",
            "pay_reference"     => "",
            "payment_notification_status"   => $payment_notification_status,
            "payment_notification_channel"  => $payment_notification_channel,
            "expiry_limit"      => "10/23/2021 15:51:42",
            "secure3d"          => "try3d",
            "trxtype"           => "sale",
            "signature"         => $signature_trx
        );

        $customer_info = array(
            "fname"     => $fname,
            "lname"     => $lname,
            "mname"     => $mname,
            "email"     => $email,
            "phone"     => "",
            "mobile"    => "",
            "dob"       => "",
            "signature" => $signature_customer
        );

        $billing_info = array(
            "billing_address1"  => "asdasf, Hulo",
            "billing_address2"  => "Hulo",
            "billing_city"      => "Malabon",
            "billing_state"     => "Abra",
            "billing_country"   => "PH",
            "billing_zip"       => "1470"
        );

        $order_details = array(
            "orders" => array(
                array(
                    "itemname"      => $transaction_desc,
                    "quantity"      => "1",
                    "unitprice"     => $amount,
                    "totalprice"    => $amount
                ),
                array(
                    "itemname"      => "fee",
                    "quantity"      => "1",
                    "unitprice"     => $fee,
                    "totalprice"    => $fee
                )
            ),
            "subtotalprice"     => $amount,
            "shippingprice"     => "0.00",
            "discountamount"    => "0.00",
            "totalorderamount"  => $total_amount
        );

        $parameters_raw = array(
            "transaction"   => $transaction,
            "customer_info" => $customer_info,
            "billing_info"  => $billing_info,
            "order_details" => $order_details
        );
        
        $response_raw = $this->paynamics_request($parameters_raw);

        if (!isset($response_raw->cc_info)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => isset($response_raw->response_advise) ? $response_raw->response_advise : "Something is error on payment gateway."
                )
            );
            die();
        }

        echo json_encode(
            array(
                'message' =>  "Successfully request cash-in via cc!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->cc_info
                )
            )
        );
    }

	private function otc() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin1"; // cash-in
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $account_oauth_bridge_id   = $account->account_oauth_bridge_id;

        if (!isset($post["amount"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid Amount."
                )
            );
            die();
        }

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

        echo json_encode(
            array(
                'message' =>  "Successfully created cash-in via OTC!",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );

        die();
    }

    private function paynamics_request($parameters_raw) {
        $parameters_json = json_encode($parameters_raw);

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => PAYNAMICSENDPOINT,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $parameters_json,
          CURLOPT_HTTPHEADER => array(
            ': ',
            'Content-Type: application/json',
            'Authorization: Basic ' . PAYNAMICSBASICAUTH
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);

        return json_decode($response);
    }

    private function check_cc($cc, $extra_check = false){
        $cards = array(
            "visa" => "(4\d{12}(?:\d{3})?)",
            "amex" => "(3[47]\d{13})",
            "jcb" => "(35[2-8][89]\d\d\d{10})",
            "maestro" => "((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)",
            "solo" => "((?:6334|6767)\d{12}(?:\d\d)?\d?)",
            "mastercard" => "(5[1-5]\d{14})",
            "switch" => "(?:(?:(?:4903|4905|4911|4936|6333|6759)\d{12})|(?:(?:564182|633110)\d{10})(\d\d)?\d?)",
        );
        $names = array("Visa", "American Express", "JCB", "Maestro", "Solo", "Mastercard", "Switch");
        $matches = array();
        $pattern = "#^(?:".implode("|", $cards).")$#";
        $result = preg_match($pattern, str_replace(" ", "", $cc), $matches);
        if($extra_check && $result > 0){
            $result = (validatecard($cc))?1:0;
        }
        return ($result>0)?$names[sizeof($matches)-2]:false;
    }
}

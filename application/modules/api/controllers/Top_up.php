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
        } else if ($type == "cc") {
            $this->cc();
            return;
        } else if ($type == "grabpay") {
            $this->grabpay();
            return;
        } else if ($type == "gcash") {
            $this->gcash();
            return;
        } else if ($type == "paymaya") {
            $this->paymaya();
            return;
        } else if ($type == "bancnet") {
            $this->bancnet();
            return;
        }

        $this->output->set_status_header(401);
    }

    # PAYNAMICS CC
    private function cc() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_topup2"; // top-up
        $transaction_desc       = "BambuPAY top-up via CC";
        $post                   = $this->get_post();

        $account_oauth_bridge_id    = $account->account_oauth_bridge_id;
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
            $admin_oauth_bridge_id
        );

        $fee            = number_format($fee, 2, '.', '');
        $amount         = number_format($amount, 2, '.', '');
        $total_amount   = number_format($total_amount, 2, '.', '');

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $request_id         = $sender_ref_id;
        $pmethod            = "creditcard";
        $pchannel           = "creditcard";
        $payment_action     = "direct_otc";
        $collection_method  = "single_pay";
        $amount             = $total_amount;
        $currency           = "PHP";
        $descriptor_note    = "HOME";
        $payment_notification_status    = "1";
        $payment_notification_channel   = "";

        $fname  = $account->account_fname;
        $mname  = $account->account_mname;
        $lname  = $account->account_lname;
        $email  = $account->merchant_email_address;
        $phone  = "";
        $mobile = "";
        $dob    = "";

        $transaction = $this->set_paynamics_transaction(
            $request_id, 
            $pmethod, 
            $pchannel,
            $payment_action, 
            $collection_method, 
            $amount, 
            $currency, 
            $payment_notification_status,
            $payment_notification_channel,
            $descriptor_note,
            "cc"
        );

        $customer_info = $this->set_paynamics_customer_info(
            $fname, 
            $lname, 
            $mname, 
            $email
        );

        $billing_info = array(
            "billing_address1"  => "QC",
            "billing_address2"  => "Commonwealth",
            "billing_city"      => "Quezon City",
            "billing_state"     => "MME",
            "billing_country"   => "PH",
            "billing_zip"       => "1211"
        );

        // order details
        $order_details =  $this->set_paynamics_order_details(
            array(
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
            $amount, 
            $total_amount
        );

        $parameters_raw = array(
            "transaction"   => $transaction,
            "customer_info" => $customer_info,
            "billing_info"  => $billing_info,
            "order_details" => $order_details
        );
        
        $response_raw = $this->paynamics_request($parameters_raw);

        if (!isset($response_raw->cc_info)) {
            $response_code = isset($response_raw->response_code) ? $response_raw->response_code . ", " : "No response code from payment gateway, ";

            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => isset($response_raw->response_advise) ? $response_code . $response_raw->response_advise : "{$response_code}Something is error on payment gateway."
                )
            );
            die();
        }

        echo json_encode(
            array(
                'message' =>  "Successfully request top-up via CC!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'amount'            => $amount,
                    'fee'               => $fee,
                    'total_amount'      => $total_amount,
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->cc_info
                )
            )
        );
    }

    # PAYNAMICS GRABPAY
    private function grabpay() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_topup3"; // top-up
        $transaction_desc       = "BambuPAY top-up via GRABPAY";
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
            $admin_oauth_bridge_id
        );

        $fee            = number_format($fee, 2, '.', '');
        $amount         = number_format($amount, 2, '.', '');
        $total_amount   = number_format($total_amount, 2, '.', '');

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $request_id         = $sender_ref_id;
        $pmethod            = "wallet";
        $pchannel           = "grabpay_ph";
        $payment_action     = "url_link";
        $collection_method  = "single_pay";
        $amount             = $total_amount;
        $currency           = "PHP";
        $payment_notification_status    = "1";
        $payment_notification_channel   = "1";

        $fname  = $account->account_fname;
        $mname  = $account->account_mname;
        $lname  = $account->account_lname;
        $email  = $account->merchant_email_address;
        $phone  = "";
        $mobile = "";
        $dob    = "";

        $transaction = $this->set_paynamics_transaction(
            $request_id, 
            $pmethod, 
            $pchannel,
            $payment_action, 
            $collection_method, 
            $amount, 
            $currency, 
            $payment_notification_status,
            $payment_notification_channel
        );

        $customer_info = $this->set_paynamics_customer_info(
            $fname, 
            $lname, 
            $mname, 
            $email
        );

        // order details
        $order_details =  $this->set_paynamics_order_details(
            array(
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
            $amount, 
            $total_amount
        );

        $parameters_raw = array(
            "transaction"   => $transaction,
            "customer_info" => $customer_info,
            "order_details" => $order_details
        );
        
        $response_raw = $this->paynamics_request($parameters_raw);

        if (!isset($response_raw->payment_action_info)) {
            $response_code = isset($response_raw->response_code) ? $response_raw->response_code . ", " : "No response code from payment gateway, ";

            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => isset($response_raw->response_advise) ? $response_code . $response_raw->response_advise : "{$response_code}Something is error on payment gateway."
                )
            );
            die();
        }

        echo json_encode(
            array(
                'message' =>  "Successfully request top-up via GRABPAY!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'amount'            => $amount,
                    'fee'               => $fee,
                    'total_amount'      => $total_amount,
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->payment_action_info
                )
            )
        );
    }

    # PAYNAMICS GCASH
    private function gcash() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_topup4"; // top-up
        $transaction_desc       = "BambuPAY top-up via GCASH";
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
            $admin_oauth_bridge_id
        );

        $fee            = number_format($fee, 2, '.', '');
        $amount         = number_format($amount, 2, '.', '');
        $total_amount   = number_format($total_amount, 2, '.', '');

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $request_id         = $sender_ref_id;
        $pmethod            = "wallet";
        $pchannel           = "gc";
        $payment_action     = "url_link";
        $collection_method  = "single_pay";
        $amount             = $total_amount;
        $currency           = "PHP";
        $payment_notification_status    = "1";
        $payment_notification_channel   = "1";

        $fname  = $account->account_fname;
        $mname  = $account->account_mname;
        $lname  = $account->account_lname;
        $email  = $account->merchant_email_address;
        $phone  = "";
        $mobile = "";
        $dob    = "";

        $transaction = $this->set_paynamics_transaction(
            $request_id, 
            $pmethod, 
            $pchannel,
            $payment_action, 
            $collection_method, 
            $amount, 
            $currency, 
            $payment_notification_status,
            $payment_notification_channel
        );

        $customer_info = $this->set_paynamics_customer_info(
            $fname, 
            $lname, 
            $mname, 
            $email
        );

        // order details
        $order_details =  $this->set_paynamics_order_details(
            array(
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
            $amount, 
            $total_amount
        );

        $parameters_raw = array(
            "transaction"   => $transaction,
            "customer_info" => $customer_info,
            "order_details" => $order_details
        );
        
        $response_raw = $this->paynamics_request($parameters_raw);

        if (!isset($response_raw->payment_action_info)) {
            $response_code = isset($response_raw->response_code) ? $response_raw->response_code . ", " : "No response code from payment gateway, ";

            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => isset($response_raw->response_advise) ? $response_code . $response_raw->response_advise : "{$response_code}Something is error on payment gateway."
                )
            );
            die();
        }

        echo json_encode(
            array(
                'message' =>  "Successfully request top-up via GCASH!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'amount'            => $amount,
                    'fee'               => $fee,
                    'total_amount'      => $total_amount,
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->payment_action_info
                )
            )
        );
    }

    # PAYNAMICS PAYMAYA
    private function paymaya() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_topup5"; // top-up
        $transaction_desc       = "BambuPAY top-up via PAYMAYA";
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
            $admin_oauth_bridge_id
        );

        $fee            = number_format($fee, 2, '.', '');
        $amount         = number_format($amount, 2, '.', '');
        $total_amount   = number_format($total_amount, 2, '.', '');

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $request_id         = $sender_ref_id;
        $pmethod            = "wallet";
        $pchannel           = "paymaya_ph";
        $payment_action     = "url_link";
        $collection_method  = "single_pay";
        $amount             = $total_amount;
        $currency           = "PHP";
        $payment_notification_status    = "1";
        $payment_notification_channel   = "1";

        $fname  = $account->account_fname;
        $mname  = $account->account_mname;
        $lname  = $account->account_lname;
        $email  = $account->merchant_email_address;
        $phone  = "";
        $mobile = "";
        $dob    = "";

        $transaction = $this->set_paynamics_transaction(
            $request_id, 
            $pmethod, 
            $pchannel,
            $payment_action, 
            $collection_method, 
            $amount, 
            $currency, 
            $payment_notification_status,
            $payment_notification_channel
        );

        $customer_info = $this->set_paynamics_customer_info(
            $fname, 
            $lname, 
            $mname, 
            $email
        );

        // order details
        $order_details =  $this->set_paynamics_order_details(
            array(
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
            $amount, 
            $total_amount
        );

        $parameters_raw = array(
            "transaction"   => $transaction,
            "customer_info" => $customer_info,
            "order_details" => $order_details
        );
        
        $response_raw = $this->paynamics_request($parameters_raw);

        if (!isset($response_raw->payment_action_info)) {
            $response_code = isset($response_raw->response_code) ? $response_raw->response_code . ", " : "No response code from payment gateway, ";

            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => isset($response_raw->response_advise) ? $response_code . $response_raw->response_advise : "{$response_code}Something is error on payment gateway."
                )
            );
            die();
        }

        echo json_encode(
            array(
                'message' =>  "Successfully request top-up via PAYMAYA!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'amount'            => $amount,
                    'fee'               => $fee,
                    'total_amount'      => $total_amount,
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->payment_action_info
                )
            )
        );
    }

    # PAYNAMICS BANCNET
    private function bancnet() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_topup6"; // top-up
        $transaction_desc       = "BambuPAY top-up via BANCNET";
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
            $admin_oauth_bridge_id
        );

        $fee            = number_format($fee, 2, '.', '');
        $amount         = number_format($amount, 2, '.', '');
        $total_amount   = number_format($total_amount, 2, '.', '');

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $request_id         = $sender_ref_id;
        $pmethod            = "creditcard";
        $pchannel           = "bancnet_cc";
        $payment_action     = "direct_otc";
        $collection_method  = "single_pay";
        $amount             = $total_amount;
        $currency           = "PHP";
        $descriptor_note    = "";
        $payment_notification_status    = "1";
        $payment_notification_channel   = "";

        $fname  = $account->account_fname;
        $mname  = $account->account_mname;
        $lname  = $account->account_lname;
        $email  = $account->merchant_email_address;
        $phone  = "";
        $mobile = "";
        $dob    = "";

        $transaction = $this->set_paynamics_transaction(
            $request_id, 
            $pmethod, 
            $pchannel,
            $payment_action, 
            $collection_method, 
            $amount, 
            $currency, 
            $payment_notification_status,
            $payment_notification_channel,
            $descriptor_note,
            "bancnet"
        );

        $customer_info = $this->set_paynamics_customer_info(
            $fname, 
            $lname, 
            $mname, 
            $email
        );

        $billing_info = array(
            "billing_address1"  => "asdasf, Hulo",
            "billing_address2"  => "Hulo",
            "billing_city"      => "Malabon",
            "billing_state"     => "Abra",
            "billing_country"   => "PH",
            "billing_zip"       => "1470"
        );

        // order details
        $order_details =  $this->set_paynamics_order_details(
            array(
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
            $amount, 
            $total_amount
        );

        $parameters_raw = array(
            "transaction"   => $transaction,
            "customer_info" => $customer_info,
            "billing_info"  => $billing_info,
            "order_details" => $order_details
        );
        
        $response_raw = $this->paynamics_request($parameters_raw);

        if (!isset($response_raw->cc_info)) {
            $response_code = isset($response_raw->response_code) ? $response_raw->response_code . ", " : "No response code from payment gateway, ";

            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => isset($response_raw->response_advise) ? $response_code . $response_raw->response_advise : "{$response_code}Something is error on payment gateway."
                )
            );
            die();
        }

        echo json_encode(
            array(
                'message' =>  "Successfully request top-up via BANCNET!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'amount'            => $amount,
                    'fee'               => $fee,
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->cc_info
                )
            )
        );
    }

	public function otc() {
        $account                = $this->_account;
        $transaction_type_id    = "txtype_topup1";
        $post                   = $this->get_post();

        $account_oauth_bridge_id    = $account->account_oauth_bridge_id;
        $admin_oauth_bridge_id      = $account->oauth_bridge_parent_id;

        $amount = 0;
        $fee = 0;

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

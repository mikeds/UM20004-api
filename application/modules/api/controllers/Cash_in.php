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

        // else if ($type == 'paynamics') {
        //     $this->paynamics();
        //     return;
        // }

        $this->output->set_status_header(401);
    }

    # PAYNAMICS BANCNET
    private function bancnet() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin8"; // cash-in
        $transaction_desc       = "BambuPAY cash-in via BANCNET";
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

        $fname  = "Marknel";
        $lname  = "Pineda";
        $mname  = "Villamor";
        $email  = "marknel.pineda23@gmail.com";
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
                'message' =>  "Successfully request cash-in via CC!",
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

    # PAYNAMICS PAYMAYA
    private function paymaya() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin7"; // cash-in
        $transaction_desc       = "BambuPAY cash-in via PAYMAYA";
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

        $fname  = "Marknel";
        $lname  = "Pineda";
        $mname  = "Villamor";
        $email  = "marknel.pineda23@gmail.com";
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
                'message' =>  "Successfully request cash-in via PAYMAYA!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->payment_action_info
                )
            )
        );
    }

    # PAYNAMICS GCASH
    private function gcash() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin6"; // cash-in
        $transaction_desc       = "BambuPAY cash-in via GCASH";
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

        $fname  = "Marknel";
        $lname  = "Pineda";
        $mname  = "Villamor";
        $email  = "marknel.pineda23@gmail.com";
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
                'message' =>  "Successfully request cash-in via GCASH!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->payment_action_info
                )
            )
        );
    }

    # PAYNAMICS GRABPAY
    private function grabpay() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin5"; // cash-in
        $transaction_desc       = "BambuPAY cash-in via GRABPAY";
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

        $fname  = "Marknel";
        $lname  = "Pineda";
        $mname  = "Villamor";
        $email  = "marknel.pineda23@gmail.com";
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
                'message' =>  "Successfully request cash-in via GRABPAY!",
                'response' => array(
                    'sender_ref_id'     => $sender_ref_id,
                    'qr_code'           => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'         => $this->_today,
                    'gateway_message'   => isset($response_raw->response_message) ? $response_raw->response_message : "",            
                    'redirect'          => $response_raw->payment_action_info
                )
            )
        );
    }

    # PAYNAMICS CC
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

        $fname  = "Marknel";
        $lname  = "Pineda";
        $mname  = "Villamor";
        $email  = "marknel.pineda23@gmail.com";
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
                'message' =>  "Successfully request cash-in via CC!",
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

    private function set_paynamics_transaction($request_id, $pmethod, $pchannel, $payment_action, $collection_method, $amount, $currency, $payment_notification_status, $payment_notification_channel, $descriptor_note = "", $cc_type = "") {
        $merchantid = PAYNAMICSMID;
        $mkey       = PAYNAMICSMKEY;

        $notification_url   = base_url() . "callback/paynamics/notification";
        $response_url       = base_url() . "callback/paynamics/response?success=true";
        $cancel_url         = base_url() . "callback/paynamics/response?success=false";

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

        $signature_trx          = hash("sha512", $raw_trx);

        $transaction = array();

        if ($cc_type != "") {
            // expiration timestamp
            $minutes_to_add = 30;
            $time = new DateTime($this->_today);
            $time->add(new DateInterval('PT' . 30 . 'M'));
            $expiry_limit = $time->format('m/d/Y H:i:s');

            if ($cc_type == 'cc') {
                $transaction = array(
                    "descriptor_note"   => $descriptor_note,
                    "schedule"          => "",
                    "mlogo_url"         => "",
                    "pay_reference"     => "",
                    "deferred_period"   => "",
                    "deferred_time"     => "",
                    "dp_balance_info"   => "",
                    "expiry_limit"      => $expiry_limit,
                    "secure3d"          => "try3d",
                    "trxtype"           => "sale",
                );
            } else if ($cc_type == 'bancnet') {
                $transaction = array(
                    "descriptor_note"   => $descriptor_note,
                    "schedule"          => "",
                    "mlogo_url"         => "",
                    "pay_reference"     => "",
                    "deferred_period"   => "",
                    "deferred_time"     => "",
                    "dp_balance_info"   => "",
                    "expiry_limit"      => $expiry_limit,
                    "secure3d"          => "try3d",
                    "trxtype"           => "sale",
                );
            }
        }

        $transaction = array_merge(
            $transaction,
            array(
                "request_id"        => $request_id,
                "notification_url"  => $notification_url,
                "response_url"      => $response_url,
                "cancel_url"        => $cancel_url,
                "pmethod"           => $pmethod,
                "pchannel"          => $pchannel,
                "payment_action"    => $payment_action,
                "collection_method" => $collection_method,
                "amount"            => $amount,
                "currency"          => $currency,
                "payment_notification_status"   => $payment_notification_status,
                "payment_notification_channel"  => $payment_notification_channel,
                "signature"         => $signature_trx
            )
        );

        return $transaction;
    }

    private function set_paynamics_customer_info($fname, $lname, $mname, $email, $phone = "", $mobile = "", $dob = "") {
        $mkey       = PAYNAMICSMKEY;

        $raw_customer_info =
        $fname . 
        $lname . 
        $mname . 
        $email . 
        $phone . 
        $mobile . 
        $dob . 
        $mkey;

        $signature_customer     = hash("sha512", $raw_customer_info);

        $customer_info = array(
            "fname"     => $fname,
            "lname"     => $lname,
            "mname"     => $mname,
            "email"     => $email,
            "phone"     => $phone,
            "mobile"    => $mobile,
            "dob"       => $dob,
            "signature" => $signature_customer
        );
        
        return $customer_info;
    }

    private function set_paynamics_order_details($orders, $amount, $total_amount, $shipping_price = "0.00", $discount_amount = "0.00") {
        $order_details = array(
            "orders"            => $orders,
            "subtotalprice"     => $amount,
            "shippingprice"     => "0.00",
            "discountamount"    => "0.00",
            "totalorderamount"  => $total_amount
        );

        return $order_details;
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

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
        } else if ($type == 'paynamics') {
            $this->paynamics();
            return;
        }

        $this->output->set_status_header(401);
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
                'message' =>  "Successfully created cash-in!",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
    }

    private function paynamics() {
        $this->load->model("api/transaction_fees_model", "tx_fees");

        $account                = $this->_account;
        $transaction_type_id    = "txtype_cashin3"; // cash-in
        $post                   = $this->get_post();

        $admin_oauth_bridge_id     = $account->oauth_bridge_parent_id;
        $account_oauth_bridge_id   = $account->account_oauth_bridge_id;

        if (!isset($post["card_holder"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Please fill-up Card Holder."
                )
            );
            die();
        }

        if (!isset($post["card_number"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Please fill-up Card No."
                )
            );
            die();
        }

        if (!isset($post["ccv"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Please fill-up ccv."
                )
            );
            die();
        }

        if (!isset($post["card_month"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Please fill-up card month eg. (05)."
                )
            );
            die();
        }

        if (!isset($post["card_year"])) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Please fill-up card year eg. (21)."
                )
            );
            die();
        }

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

        $card_holder    = $post["card_holder"];
        $card_number    = $post["card_number"];
        $card_month     = $post["card_month"];
        $card_year      = $post["card_month"];
        $ccv            = $post["ccv"];

        if ($card_holder == "" || $card_number == "" || $card_month == "" || $card_year == "" || $ccv == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Card information is incomplete."
                )
            );
            die();
        }

        if (!$this->check_cc($card_number)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Invalid card number"
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

        // get admin first account

        $tx_row = $this->create_transaction(
            $amount, 
            $fee, 
            $transaction_type_id, 
            $account_oauth_bridge_id, 
            $admin_oauth_bridge_id
        );

        $transaction_id = $tx_row['transaction_id'];
        $sender_ref_id  = $tx_row['sender_ref_id'];

        $email_address = $account->account_email_address;
        
        $debit_amount	= $amount + $fee;
        $credit_amount 	= $amount;
        $fee_amount		= $fee;

        $debit_total_amount 	= 0 - $debit_amount; // make it negative
        $credit_total_amount	= $credit_amount;

        // find wallet
        $debit_wallet_address		= $this->get_wallet_address($admin_oauth_bridge_id);
        $credit_wallet_address	    = $this->get_wallet_address($account_oauth_bridge_id);
        
        if ($credit_wallet_address == "" || $debit_wallet_address == "") {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Cannot find wallet, Please contact system administrator."
                )
            );
            die();
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

        // // do income sharing
        // $this->distribute_income_shares(
		// 	$transaction_id,
		// 	$merchant_no,
		// 	$fee_amount
        // );
        
        $this->transactions->update(
            $transaction_id,
            array(
                'transaction_status' 		=> 1,
                'transaction_date_approved'	=> $this->_today,
                'transaction_requested_to'  => $account_oauth_bridge_id
            )
        );

        echo json_encode(
            array(
                'message' =>  "Successfully cash-in!",
                'response' => array(
                    'sender_ref_id' => $sender_ref_id,
                    'qr_code'       => base_url() . "qr-code/transactions/{$sender_ref_id}",
                    'timestamp'     => $this->_today
                )
            )
        );
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

    public function paynamics_test() {
        $_mid = "000000281020C4BCFC0B"; //<-- your merchant id
        $_requestid = substr(uniqid(), 0, 13);
        $_ipaddress = "192.168.10.1";
        $_noturl = ""; // url where response is posted
        $_resurl = ""; //url of merchant landing page
        $_cancelurl = ""; //url of merchant landing page
        $_fname = "Juan";
        $_mname = "dela";
        $_lname = "Cruz";
        $_addr1 = "Dela Costa St.";
        $_addr2 = "Salecedo Village";
        $_city = "makati";
        $_state = "MM";
        $_country = "PH";
        $_zip = "1200";
        $_sec3d = "-";//enabled
        $_email = "dummyemail.uno@gmail.com";
        $_phone = "3308772";
        $_mobile = "09171111111";
        $_clientip = $_SERVER['REMOTE_ADDR'];
        $_amount = "1.00";
        $_currency = "PHP";
        $forSign = $_mid . $_requestid . $_ipaddress . $_noturl . $_resurl .  $_fname . $_lname . $_mname . $_addr1 . $_addr2 . $_city . $_state . $_country . $_zip . $_email . $_phone . $_clientip . $_amount . $_currency . $_sec3d;
        $cert = "DA0C390693F0D23D03B3CF233277919C"; //<-- your merchant key
  
        $_sign = hash("sha512", $forSign.$cert);
 
        try {
            $endpoint_1 = "https://testpti.payserv.net/Paygate/ccservice.asmx?WSDL";
            $endpoint_2 = "https://testpti.payserv.net/webpayment/Default.aspx";

            $ini = ini_set("soap.wsdl_cache_enable", "0");
            $client = new SoapClient($endpoint_1);

            $params = array(
                "mid"        => $_mid,
                "request_id"        => $_requestid,
                "ip_address"        => $_ipaddress,
                "notification_url"  => $_noturl,
                "response_url"      => $_resurl,
                "fname"             => $_fname,
                "lname"             => $_lname,
                "mname"             => $_mname,
                "address1"          => $_addr1,
                "address2"          => $_addr2,
                "city"              => $_city,
                "state"             => $_state,
                "country"           => $_country,
                "postal"            => $_zip,
                "email"             => $_email,
                "phone"             => $_phone,
                "client_ip"         => $client_ip,
                "card_type"         => $card_type,
                "card_holder"       => $card_holder,
                "card_number"       => $card_number,
                "cvv"               => $cvv,
                "exp_month"         => $expiration_month,
                "exp_year"          => $expiration_year,
                "amount"            => $_amount,
                "currency"          => $_currency,
                "bin"               => $bin,
                "csn"               => $csn,
                "mobile"            => $_mobile,
                "secure3d"          => $_sec3d,
                "signature"         => $signature
            );

            $result = $client->sale($params);
            $client->__getLastResponseHeaders();
            $client->__getLastResponse();

            print_r($result);

        } catch(Exception $ex) {
            echo $ex->getMessage();
        }
    }
}

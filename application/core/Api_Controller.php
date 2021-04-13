<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Api_Controller extends MX_Controller {
	protected
		$_limit = 10,
		$_today = "",
		$_base_controller = "api",
		$_base_session = "session";

	protected
		$_upload_path = FCPATH . UPLOAD_PATH,
		$_ssl_method = "AES-128-ECB";

	protected
		$_account = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();
		date_default_timezone_set("Asia/Manila");
		$this->_today = date("Y-m-d H:i:s");

		header('Content-Type: application/json');

		$this->after_init();
	}

	// PAYNAMICS
	public function set_paynamics_transaction($request_id, $pmethod, $pchannel, $payment_action, $collection_method, $amount, $currency, $payment_notification_status, $payment_notification_channel, $descriptor_note = "", $cc_type = "") {
        $merchantid = PAYNAMICSMID;
        $mkey       = PAYNAMICSMKEY;

        $notification_url   = base_url() . "callback/paynamics/notification";
        $response_url       = base_url() . "callback/paynamics/response?success=true&message=Successfully done transaction on our payment gateway&timestamp={$this->_today}";
        $cancel_url         = base_url() . "callback/paynamics/response?success=false&message=Payment gateway is cancelled, Invalid transaction&timestamp={$this->_today}";

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

    public function set_paynamics_customer_info($fname, $lname, $mname, $email, $phone = "", $mobile = "", $dob = "") {
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

    public function set_paynamics_order_details($orders, $amount, $total_amount, $shipping_price = "0.00", $discount_amount = "0.00") {
        $order_details = array(
            "orders"            => $orders,
            "subtotalprice"     => $amount,
            "shippingprice"     => "0.00",
            "discountamount"    => "0.00",
            "totalorderamount"  => $total_amount
        );

        return $order_details;
	}
	
    public function paynamics_request($parameters_raw) {
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

	public function send_sms_otp($mobile_no, $module = "grant_access") {
		$expiration_time = 3;

		if (isset($_GET['expiration_time'])) {
			if (is_numeric($_GET['expiration_time'])) {
				$expiration_time = $_GET['expiration_time'];
			}
		}

		$expiration_date 	= create_expiration_datetime($this->_today, $expiration_time);

		if ($module == "reg") {
			$this->otp_registration($mobile_no, $expiration_date);
		} else if ($module == "login") {
			$this->otp_login($mobile_no, $expiration_date);
		} else {
			$this->otp_grant_access($mobile_no, $expiration_date);
		}

		echo json_encode(
			array(
				'message' => "Successfully sent SMS OTP.",
				'response'	=> array(
					'expiration_date' => $expiration_date
				),
				'timestamp'	=> $this->_today
			)
		);
		die();
	}

	private function otp_grant_access($mobile_no, $expiration_date) {
		$this->load->model("api/client_accounts_model", "client_accounts");
		$this->load->model("api/otp_model", "otp");
		$this->load->model("api/globe_access_tokens", "globe_access_token");

		$client_row = $this->client_accounts->get_datum(
			'',
			array(
				'account_mobile_no' => $mobile_no
			)
		)->row();

		if ($client_row == "") {
			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Mobile no. not register on database."
				)
			);
			die();
		}

		$row_access_token = $this->globe_access_token->get_datum(
			'',
			array(
				'token_mobile_no' => $mobile_no
			)
		)->row();

		if ($row_access_token == "") {
			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Unable to access OTP SMS.",
					'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
				)
			);
			die();
		}

		$access_token	= $row_access_token->token_code;

		$code = generate_code(4);
		$code = strtolower($code);

		$otp_number = $this->generate_code(
			array(
				"otp",
				$code,
				$this->_today
			),
			"crc32"
		);

		$message			= "OTP: {$code}. Expiration Date: {$expiration_date}";

		$this->send_sms($mobile_no, $message, $access_token);

		$this->otp->insert(
			array(
				'otp_number'			=> $otp_number,
				'otp_code'				=> $code,
				'otp_mobile_no'			=> $mobile_no,
				'otp_date_expiration'	=> $expiration_date,
				'otp_date_created'		=> $this->_today
			)
		);

		$row_client = $this->client_accounts->get_datum(
			'',
			array(
				'account_mobile_no' => $mobile_no
			)
		)->row();

		if ($row_client != "") {
			if ($row_client->account_email_address != "") {
				$send_to_email = $row_client->account_email_address;

				$this->send_email_activation(
					$send_to_email, 
					$code, 
					$expiration_date, 
					"BambuPay OTP Code", 
					$message
				);
			}
		}
	}

	private function otp_registration($mobile_no, $expiration_date) {
		$this->load->model("api/client_pre_registration_model", "client_pre_registration");
		$this->load->model("api/client_accounts_model", "client_accounts");
		$this->load->model("api/globe_access_tokens", "globe_access_token");
		$this->load->model("api/otp_model", "otp");

		$row_otp = 	$this->otp->get_datum(
						'',
						array(
							'account_mobile_no' => $mobile_no
						),
						array(),
						array(
							array(
								'table_name'	=> 'client_pre_registration',
								'condition'		=> 'client_pre_registration.account_otp_number = otp_number'
							)
						)
					)->row();

		if ($row_otp == "") {
			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Failed OTP, Mobile no. not found on pre-registration."
				)
			);
			die();
		}

		$code 		= generate_code(4);
		$code 		= strtolower($code);

		$this->otp->update(
			$row_otp->otp_number,
			array(
				'otp_code'				=> $code,
				// 'otp_status'			=> 0,
				'otp_date_expiration'	=> $expiration_date
			)
		);

		$message	= "OTP: {$code}. Expiration Date: {$expiration_date}";
		
		$access_token = "";

		$row = $this->globe_access_token->get_datum(
			'',
			array(
				'token_mobile_no' => $mobile_no
			)
		)->row();

		if ($row != "") {
			$access_token = $row->token_code;
		}

		$this->send_sms($mobile_no, $message, $access_token);
	}

	public function _send_sms($mobile_no, $message) {
		$this->load->model("api/globe_access_tokens", "globe_access_token");

		$access_token = "";

		$row = $this->globe_access_token->get_datum(
			'',
			array(
				'token_mobile_no' => $mobile_no
			)
		)->row();

		if ($row != "") {
			$access_token = $row->token_code;
		}

		$this->send_sms($mobile_no, $message, $access_token, true);
	}

	public function _send_email($send_to, $title, $message) {
		send_email(
			SMTP_USER,
			$send_to,
			$title,
			$message
		);
	}

	private function otp_login($mobile_no, $expiration_date) {
		$this->load->model("api/client_accounts_model", "client_accounts");
		$this->load->model("api/globe_access_tokens", "globe_access_token");
		$this->load->model("api/otp_model", "otp");

		$row_otp = 	$this->otp->get_datum(
			'',
			array(
				'account_mobile_no' => $mobile_no
			),
			array(),
			array(
				array(
					'table_name'	=> 'client_accounts',
					'condition'		=> 'client_accounts.account_otp_number = otp_number'
				)
			)
		)->row();

		if ($row_otp == "") {
			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Failed OTP, Mobile no. not found on client accounts."
				)
			);
			die();
		}

		$code 		= generate_code(4);
		$code 		= strtolower($code);

		$this->otp->update(
			$row_otp->otp_number,
			array(
				'otp_code'				=> $code,
				// 'otp_status'			=> 0,
				'otp_date_expiration'	=> $expiration_date
			)
		);

		$message	= "Your BambuPay verification code is {$code}. For your account SECURITY, do not share this code.";
		
		$access_token = "";

		$row = $this->globe_access_token->get_datum(
			'',
			array(
				'token_mobile_no' => $mobile_no
			)
		)->row();

		if ($row != "") {
			$access_token = $row->token_code;
		}

		$this->send_sms($mobile_no, $message, $access_token); // bypass error return if sent sms failed

		$row_client = $this->client_accounts->get_datum(
			'',
			array(
				'account_mobile_no' => $mobile_no
			)
		)->row();
		
		if ($row_client != "") {
			if ($row_client->account_email_address != "") {
				$send_to_email = $row_client->account_email_address;

				send_email(
					SMTP_USER,
					$send_to_email,
					$email_title,
					$email_message
				);
			}
		}
	}

	public function send_sms($mobile_no, $message, $access_token, $is_bypass = false) {

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://devapi.globelabs.com.ph/smsmessaging/v1/outbound/".GLOBESHORTCODE."/requests?access_token=".$access_token ,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => "{\"outboundSMSMessageRequest\": { \"clientCorrelator\": \"".GLOBECLIENTCORRELATOR."\", \"senderAddress\": \"".GLOBESHORTCODE."\", \"outboundSMSTextMessage\": {\"message\": \"".$message."\"}, \"address\": \"".$mobile_no."\" } }",
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json"
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			if ($is_bypass) {
				return;
			}

			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Unable to send OTP. Curl Error #: {$err}",
					'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
				)
			);
			die();
		} else {
			if ($is_bypass) {
				return;
			}

			$decoded = json_decode($response);
			
			if (isset($decoded->error)) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => $decoded->error,
						'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
					)
				);
				die();
			}
		}

	}

	public function get_income_groups_members($transaction_type_id, $merchant_oauth_bridge_id = "") {
		$this->load->model("api/income_groups_members_model", "igm");

		$where = array(
			'income_groups.transaction_type_id'	=> $transaction_type_id,
		);

		if ($merchant_oauth_bridge_id != "") {
			$where = array_merge(
				$where,
				array(
					'oauth_bridge_id' => $merchant_oauth_bridge_id
				)
			);
		}

		$row = $this->igm->_datum(
			array(
				'*'
			),
			array(
				array(
					'table_name'	=> "income_groups",
					'condition'		=> "income_groups.ig_id = income_groups_members.ig_id"
				),
				array(
					'table_name'	=> "transaction_types",
					'condition'		=> "income_groups.transaction_type_id = transaction_types.transaction_type_id"
				)
			),
			$where,
			array(),
			array(),
			array(
				'filter_by'	=> "igm_id",
				'sort_by'	=> "ASC"
			)
		)->row();

		if ($row != "") {
			$ig_id = $row->ig_id;

			$data = $this->igm->_data(
				array(
					'*',
					'income_groups_members.oauth_bridge_id as oauth_bridge_id'
				),
				array(
					array(
						'table_name'	=> "merchants",
						'condition'		=> "merchants.oauth_bridge_id = income_groups_members.oauth_bridge_id",
						'type'			=> "left"
					)
				),
				array(
					'ig_id' => $ig_id
				),
				array(),
				array(),
				array(
					'filter_by' => 'igm_id',
					'sort_by'	=> 'ASC'
				)
			);

			return $data;
		}

		return false;
	}

	public function get_fee($amount, $transaction_type_id, $merchant_oauth_bridge_id = "") {
		$this->load->model("api/income_groups_members_model", "igm");

		$fee = 0;

		$data = $this->get_income_groups_members(
			$transaction_type_id,
			$merchant_oauth_bridge_id
		);

		if ($data) {
			foreach($data as $datum) {
				$share_amount = $datum['igm_fees_amount'];
	
				$fee += $share_amount;
			}
		}

		return $fee;
	}

	public function distribute_income_shares($transaction_id) {
		$this->load->model("api/transactions_model", "tx");
		$this->load->model("api/income_groups_members_model", "igm");
		$this->load->model("api/merchants_model", "merchants");

		$row_tx = $this->tx->_datum(
			array(
				'*'
			),
			array(
				array(
					"table_name"	=> "transaction_types",
					"condition"		=> "transaction_types.transaction_type_id = transactions.transaction_type_id"
				)
			),
			array(
				'transaction_id' => $transaction_id
			)
		)->row();

		if ($row_tx == "") {
			return;
		}

		$amount 				= $row_tx->transaction_amount;
		$transaction_type_id	= $row_tx->transaction_type_id;

		$m_account_oauth_bridge_id = "";

		$requested_by 	= $row_tx->transaction_requested_by; // from
		$requested_to 	= $row_tx->transaction_requested_to; // to
		
		$debit_oauth_bridge_id 		= "";
		$merchant_oauth_bridge_id 	= "";

		if (
			$transaction_type_id == "txtype_cashout1" || 
			$transaction_type_id == "txtype_cashout2" ||
			$transaction_type_id == "txtype_createpayqr1" ||
			$transaction_type_id == "txtype_encash1" ||
			$transaction_type_id == "txtype_income_shares" ||
			$transaction_type_id == "txtype_quickpayqr1" ||
			$transaction_type_id == "txtype_transfer1"
		) {
			$debit_oauth_bridge_id = $requested_by;
		} else {
			$debit_oauth_bridge_id = $requested_to;
		}

		if (
			$transaction_type_id == "txtype_cashin1" ||
			$transaction_type_id == "txtype_cashout1" ||
			$transaction_type_id == "txtype_createpayqr1" ||
			$transaction_type_id == "txtype_quickpayqr1" ||
			$transaction_type_id == "txtype_scanpayqr1"
		) {
			if ($transaction_type_id == "txtype_scanpayqr1") {
				$m_account_oauth_bridge_id = $requested_by;
			} else {
				$m_account_oauth_bridge_id = $requested_to;
			}

			// get merchant of merchant accounts
			$row_merchant = $this->merchants->_datum(
				array(
					'merchants.oauth_bridge_id as "merchant_oauth_bridge_id"',
					'oauth_bridge_parent_id'
				),
				array(
					array(
						'table_name'	=> "merchant_accounts",
						'condition'		=> "merchant_accounts.merchant_number = merchants.merchant_number",
						'position'		=> "left"
					),
					array(
						'table_name'	=> "oauth_bridges",
						'condition'		=> "oauth_bridges.oauth_bridge_id = merchants.oauth_bridge_id"
					)
				),
				array(),
				array(),
				array(),
				array(),
				array(
					array(
						'field'	=> 'merchants.oauth_bridge_id',
						'data'	=>  $m_account_oauth_bridge_id
					),
					array(
						'field'	=> 'merchant_accounts.oauth_bridge_id',
						'data'	=>  $m_account_oauth_bridge_id
					)
				)
			)->row();

			if ($row_merchant != "") {
				$merchant_oauth_bridge_id = $row_merchant->merchant_oauth_bridge_id;
			}
		}

		$data = $this->get_income_groups_members(
			$transaction_type_id,
			$merchant_oauth_bridge_id
		);

		if ($data) {
			foreach($data as $datum) {
				$legder_desc = "income_share";
	
				$credit_oauth_bridge_id = $datum['oauth_bridge_id'];
				$amount 				= $datum['igm_fees_amount'];
				
				$tx = $this->create_transaction(
					$amount, 
					0, 
					'txtype_income_shares', 
					$debit_oauth_bridge_id, 
					$credit_oauth_bridge_id
				);
	
				if (isset($tx['transaction_id'])) {
					// update tx status
					$this->transactions->update(
						$tx['transaction_id'],
						array(
							'transaction_parent_id'		=> $transaction_id,
							'transaction_status' 		=> 1,
							'transaction_date_approved'	=> $this->_today,
							'transaction_requested_to'  => $credit_oauth_bridge_id
						)
					);
				}
	
				$balances = $this->create_ledger(
					$legder_desc, 
					$transaction_id, 
					$amount, 
					0,
					$debit_oauth_bridge_id, 
					$credit_oauth_bridge_id
				);
			}
		}
	}

	public function filter_mobile_number($mobile_number, $country_code = "63") {
		if (substr($mobile_number, 0, 1) == "+") {
			$mobile_number = substr($mobile_number, 1);
		}

		if (strlen($mobile_number) > 3) {
			if (substr($mobile_number, 0, 2) == "09") {
				return $country_code . substr($mobile_number, 1);
			} else if (substr($mobile_number, 0, 1) == "9") {
				return $country_code . $mobile_number;
			}
		}
		
		return $mobile_number;
	}

	public function get_oauth_account_info($oauth_bridge_id) {
		$this->load->model("api/accounts_model", "accounts");
		$this->load->model("api/tms_admin_accounts_model", "tms_admin_accounts");
		$this->load->model("api/merchant_accounts_model", "merchant_accounts");
		$this->load->model("api/client_accounts_model", "client_accounts");
			
		$where = array(
			'oauth_bridge_id'	=> $oauth_bridge_id
		);

		$row_account = $this->accounts->get_datum(
			'',
			$where
		)->row();

		if ($row_account != "") {
			return array(
				'account_number'	=> $row_account->account_number,
				'account_fname'		=> $row_account->account_fname,
				'account_mname'		=> $row_account->account_mname,
				'account_lname'		=> $row_account->account_lname, 
			);
		}

		$row_admin = $this->tms_admin_accounts->get_datum(
			'',
			$where
		)->row();

		if ($row_admin != "") {
			return array(
				'account_number'	=> $row_admin->account_number,
				'account_fname'		=> $row_admin->account_fname,
				'account_mname'		=> $row_admin->account_mname,
				'account_lname'		=> $row_admin->account_lname, 
			);
		}

		$row_merchant = $this->merchant_accounts->get_datum(
			'',
			$where
		)->row();

		if ($row_merchant != "") {
			return array(
				'account_number'	=> $row_merchant->account_number,
				'account_fname'		=> $row_merchant->account_fname,
				'account_mname'		=> $row_merchant->account_mname,
				'account_lname'		=> $row_merchant->account_lname, 
			);
		}

		$row_client = $this->client_accounts->get_datum(
			'',
			$where
		)->row();

		if ($row_client != "") {
			return array(
				'account_number'	=> $row_client->account_number,
				'account_fname'		=> $row_client->account_fname,
				'account_mname'		=> $row_client->account_mname,
				'account_lname'		=> $row_client->account_lname, 
			);
		}

		return false;
	}

	public function new_ledger_datum($description = "", $transaction_id, $from_wallet_address, $to_wallet_address, $balances) {
		$this->load->model("api/ledger_data_model", "ledger");
		$this->load->model("api/wallet_addresses_model", "wallet_addresses");

		$to_oauth_bridge_id 	= getenv("SYSADD");
		$from_oauth_bridge_id 	= getenv("SYSADD");


		$from_row = $this->wallet_addresses->get_datum(
			'',
			array(
				'wallet_address' => $from_wallet_address
			)
		)->row();

		if ($from_row != "") {
			$from_oauth_bridge_id 	= $from_row->oauth_bridge_id;
		}

		$to_row = $this->wallet_addresses->get_datum(
			'',
			array(
				'wallet_address' => $to_wallet_address
			)
		)->row();

		if ($to_row != "") {
			$to_oauth_bridge_id 	= $to_row->oauth_bridge_id;
		}

		$old_balance = $balances['old_balance'];
		$new_balance = $balances['new_balance'];
		$amount		 = $balances['amount'];

		$ledger_type = 0; // unknown

		if ($amount < 0) {
			$ledger_type = 1; // debit
		} else if ($amount >= 0) {
			$ledger_type = 2; // credit
		}

		// add new ledger data
		$ledger_data = array(
			'tx_id'                         => $transaction_id,
			'ledger_datum_type'				=> $ledger_type,
			'ledger_datum_bridge_id'		=> $to_oauth_bridge_id,
			'ledger_datum_desc'             => $description,
			'ledger_from_wallet_address'    => $from_wallet_address,
			'ledger_to_wallet_address'      => $to_wallet_address,
			'ledger_from_oauth_bridge_id'   => $from_oauth_bridge_id,
			'ledger_to_oauth_bridge_id'     => $to_oauth_bridge_id,
			'ledger_datum_old_balance'      => $old_balance,
			'ledger_datum_new_balance'      => $new_balance,
			'ledger_datum_amount'           => $amount,
			'ledger_datum_date_added'       => $this->_today,
			'ledger_date_micro'				=> isset(gettimeofday()['usec']) ? strtotime($this->_today).gettimeofday()['usec'] : strtotime($this->_today)
		);

		$ledger_datum_id = $this->generate_code(
			$ledger_data,
			"crc32"
		);

		$ledger_data = array_merge(
			$ledger_data,
			array(
				'ledger_datum_id'   => $ledger_datum_id,
			)
		);

		$ledger_datum_checking_data = $this->generate_code($ledger_data);

		$this->ledger->insert(
			array_merge(
				$ledger_data,
				array(
					'ledger_datum_checking_data' => $ledger_datum_checking_data
				)
			)
		);
	}

	public function update_wallet($wallet_address, $amount) {
		$this->load->model("api/wallet_addresses_model", "wallet_addresses");

		$row = $this->wallet_addresses->get_datum(
			'',
			array(
				'wallet_address'	=> $wallet_address
			)
		)->row();

		if ($row == "") {
			return false;
		}

		$wallet_balance         = $this->decrypt_wallet_balance($row->wallet_balance);

		$old_balance            = $wallet_balance;
		$encryted_old_balance   = $this->encrypt_wallet_balance($old_balance);

		$new_balance            = $old_balance + $amount;
		$encryted_new_balance   = $this->encrypt_wallet_balance($new_balance);

		$wallet_data = array(
			'wallet_balance'                => $encryted_new_balance,
			'wallet_address_date_updated'   => $this->_today
		);

		// update wallet balances
		$this->wallet_addresses->update(
			$wallet_address,
			$wallet_data
		);

		return array(
			'old_balance'	=> $old_balance,
			'new_balance'	=> $new_balance,
			'amount'		=> $amount
		);
	}

	public function encrypt_wallet_balance($balance) {
		return openssl_encrypt($balance, $this->_ssl_method, getenv("BPKEY"));
	}

	public function decrypt_wallet_balance($encrypted_balance) {
		return openssl_decrypt($encrypted_balance, $this->_ssl_method, getenv("BPKEY"));
	}

	public function get_wallet_address($bridge_id) {
		$this->load->model('api/wallet_addresses_model', 'wallet_addresses');

		$row = $this->wallet_addresses->_datum(
			array('*'),
			array(),
			array(
				'oauth_bridge_id' => $bridge_id
			)
		)->row();

		if ($row == "") {
			$row_merchant = $this->wallet_addresses->_datum(
				array('*'),
				array(
					array(
						'table_name'	=> "merchants",
						'condition'		=> "merchants.oauth_bridge_id = wallet_addresses.oauth_bridge_id"
					),
					array(
						'table_name'	=> "merchant_accounts",
						'condition'		=> "merchant_accounts.merchant_number = merchants.merchant_number"
					)
				),
				array(
					'merchant_accounts.oauth_bridge_id' => $bridge_id
				)
			)->row();

			if ($row_merchant == "") {
				return "";
			}

			return $row_merchant->wallet_address;
		}

		return $row->wallet_address;
	}

	public function send_email_activation($send_to_email, $pin, $expiration_date = "", $email_title = "", $email_message = "") {
		// if ($expiration_date != "") {
		// 	if ($expiration_date > $this->_today) {
				
		// 	}
		// }

		$date	= strtotime($this->_today);
		$date 	= date("F j, Y, g:i a", $date);

		// send email activation
		// $email_message = $this->load->view("templates/email_activation", array(
		// 	"activation_pin" => $pin,
		// 	"date"	=> $date
		// ), true);

		send_email(
			SMTP_USER,
			$send_to_email,
			$email_title,
			$email_message
		);
	}

	public function send_otp_pin($title = "OTP PIN", $send_to_email, $pin, $expiration_date = "") {
		if ($expiration_date != "") {
			if ($expiration_date > $this->_today) {
				generate_error_message("E010-2");
			}
		}

		$date	= strtotime($this->_today);
		$date 	= date("F j, Y, g:i a", $date);

		// send email activation
		$email_message = $this->load->view("templates/otp_pin", array(
			"title"	=> $title,
			"activation_pin" => $pin,
			"date"	=> $date
		), true);

		send_email(
			getenv("SMTPUSER"),
			$send_to_email,
			$title,
			$email_message
		);
	}

	public function generate_code($data, $hash = "sha256") {
		$json = json_encode($data);
		return hash_hmac($hash, $json, getenv("SYSKEY"));
	}

	public function create_transaction(
		$amount, 
		$fee, 
		$transaction_type_id, 
		$requested_by_oauth_bridge_id, 
		$requested_to_oauth_bridge_id, 
		$created_by_oauth_bridge_id = null, 
		$expiration_minutes = 15, 
		$message = "",
		$tx_parent_id = "",
		$date = ""
	) {
		$date = $this->_today;

		$this->load->model("api/transactions_model", "transactions");
		
		if (is_null($created_by_oauth_bridge_id) || $created_by_oauth_bridge_id == "") {
			$created_by_oauth_bridge_id = $requested_by_oauth_bridge_id;
		}

        // expiration timestamp
        $minutes_to_add = $expiration_minutes;
        $time = new DateTime($this->_today);
        $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));
        $stamp = $time->format('Y-m-d H:i:s');

        $total_amount = $amount + $fee;

        $data_insert = array(
			'transaction_message'			=> $message,
            'transaction_amount' 		    => $amount,
            'transaction_fee'		        => $fee,
            'transaction_total_amount'      => $total_amount,
            'transaction_type_id'           => $transaction_type_id,
            'transaction_requested_by'      => $requested_by_oauth_bridge_id,
            'transaction_requested_to'	    => $requested_to_oauth_bridge_id,
            'transaction_created_by'        => $created_by_oauth_bridge_id,
            'transaction_date_created'      => $date,
			'transaction_date_expiration'   => $stamp,
			'transaction_otp_status'		=> 1, // temporary activated
			'transaction_date_micro'		=> isset(gettimeofday()['usec']) ? strtotime($this->_today).gettimeofday()['usec'] : strtotime($this->_today)
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

        // generate OTP Pin
        $pin 	= generate_code(4, 2);

        $data_insert = array_merge(
            $data_insert,
            array(
                'transaction_id'        => $transaction_id,
                'transaction_otp_pin'   => $pin
            )
		);
		
		if ($tx_parent_id != "") {
			$data_insert = array_merge(
				$data_insert,
				array(
					'transaction_parent_id'	=> $tx_parent_id
				)
			);

			if ($transaction_type_id == "txtype_income_shares") {
				$data_insert = array_merge(
					$data_insert,
					array(
						'transaction_status' => 1 // approved
					)
				);
			}
		}

        $this->transactions->insert(
            $data_insert
		);
		
		return array(
			'transaction_id'=> $transaction_id,
			'sender_ref_id'	=> $sender_ref_id,
			'pin'			=> $pin
		);
	}

	public function create_ledger(
		$legder_desc, 
		$transaction_id, 
		$amount, 
		$fee, 
		$debit_oauth_bridge_id, 
		$credit_oauth_bridge_id
	) {
		// create ledger
		$debit_amount	= $amount;
		$credit_amount 	= $amount;

		$debit_total_amount 	= 0 - $debit_amount; // make it negative
		$credit_total_amount	= $credit_amount;

		$debit_wallet_address		= $this->get_wallet_address($debit_oauth_bridge_id);
		$credit_wallet_address	    = $this->get_wallet_address($credit_oauth_bridge_id);
		
		if ($debit_wallet_address == "") {
			echo json_encode(
				array(
					'error'		=> true,
					'message'	=> "Debit Wallet address not found!",
					'timestamp'	=> $this->_today
				)
			);
			die();
		}

		if ($credit_wallet_address == "") {
			echo json_encode(
				array(
					'error'		=> true,
					'message'	=> "Credit Wallet address not found!",
					'timestamp'	=> $this->_today
				)
			);
			die();
		}

		$debit_new_balances = $this->update_wallet($debit_wallet_address, $debit_total_amount);
		if ($debit_new_balances) {
			// record to ledger
			$this->new_ledger_datum(
				"{$legder_desc}_debit", 
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
				"{$legder_desc}_credit",
				$transaction_id, 
				$debit_wallet_address, // debit from wallet address
				$credit_wallet_address, // credit to wallet address
				$credit_new_balances
			);
		}

		return array(
			'debit_new_balance' 	=> $debit_new_balances,
			'credit_new_balance'	=> $credit_new_balances
		);
	}

	public function create_wallet_address($account_number, $bridge_id, $oauth_bridge_parent_id) {
		$this->load->model('api/wallet_addresses_model', 'wallet_addresses');

		// add address
		$wallet_address = $this->generate_code(
			array(
				'account_number' 				=> $account_number,
				'oauth_bridge_id'				=> $bridge_id,
				'wallet_address_date_created'	=> $this->_today,
				'admin_oauth_bridge_id'			=> $oauth_bridge_parent_id
			)
		); 

		// create wallet address
		$this->wallet_addresses->insert(
			array(
				'wallet_address' 				=> $wallet_address,
				'wallet_balance'				=> openssl_encrypt(0, $this->_ssl_method, getenv("BPKEY")),
				'wallet_hold_balance'			=> openssl_encrypt(0, $this->_ssl_method, getenv("BPKEY")),
				'oauth_bridge_id'				=> $bridge_id,
				'wallet_address_date_created'	=> $this->_today
			)
		);
	}

	public function create_token_auth($account_number, $bridge_id) {
		$this->load->model('api/oauth_clients_model', 'oauth_clients');

		$row = $this->oauth_clients->get_datum(
			'',
			array(
				'oauth_bridge_id'	=> $bridge_id
			)
		)->row();

		if ($row != "") {
			return;
		}

		// create api token
		$this->oauth_clients->insert(
			array(
				'client_id' 		=> $bridge_id,
				'client_secret'		=> $this->generate_code(
					array(
						'account_number'	=> $account_number,
						'date_added'		=> $this->_today,
						'oauth_bridge_id'	=> $bridge_id
					)
				),
				'oauth_bridge_id'	=> $bridge_id,
				'client_date_added'	=> $this->_today
			)
		);
	}

	public function get_post() {
		return json_decode($this->input->raw_input_stream, true);
	}

	public function JSON_POST() {
		$content_type = $this->input->get_request_header('Content-Type', TRUE);
		$json = "application/json";
		
		if (preg_match("/\bjson\b/", $content_type)) {
			return true;
		}

		return false;
	}

	public function get_token() {
		$this->load->model('api/tokens_model', 'tokens');

		$token = get_bearer_token();

		// error code E001
		if (is_null($token)) {
			generate_error_message("E001");
		}

		$token_row = $this->tokens->get_datum(
			'',
			array(
				'access_token'	=> $token
			)
		)->row();

		// error code E002
		if ($token_row == "") {
			generate_error_message("E002");
		}

		return $token_row;
	}

	public function validate_username($type, $username, $id = "") {
		$flag = false;

		$this->load->model('api/accounts_model', 'accounts');
		$this->load->model('api/client_accounts_model', 'client_accounts');
		$this->load->model('api/tms_admin_accounts_model', 'admin_accounts');
		$this->load->model('api/merchant_accounts_model', 'merchant_accounts');

		$client_row = $this->client_accounts->get_datum(
			'',
			array(
				'account_username' => $username
			)
		)->row();

		if ($client_row != "") {
			$acc_id = $client_row->account_number;

			if ($type == "client" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		$account_row = $this->accounts->get_datum(
			'',
			array(
				'account_username' => $username
			)
		)->row();

		if ($account_row != "") {
			$acc_id = $account_row->account_number;

			if ($type == "admin" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		$tms_admin_account_row = $this->admin_accounts->get_datum(
			'',
			array(
				'account_username' => $username
			)
		)->row();

		if ($tms_admin_account_row != "") {
			$acc_id = $tms_admin_account_row->account_number;

			if ($type == "tms_admin" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		$merchant_account_row = $this->merchant_accounts->get_datum(
			'',
			array(
				'account_username' => $username
			)
		)->row();

		if ($merchant_account_row != "") {
			$acc_id = $merchant_account_row->account_number;

			if ($type == "merchant" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		end:

		return $flag;
	}

	public function validate_email_address($type, $email_address, $id = "") {
		$flag = false;

		$this->load->model('api/client_accounts_model', 'client_accounts');
		$this->load->model('api/merchants_model', 'merchants');

		$client_row = $this->client_accounts->get_datum(
			'',
			array(
				'account_email_address' => $email_address
			)
		)->row();

		if ($client_row != "") {
			$acc_id = $client_row->account_number;

			if ($type == "client" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		$merchant_row = $this->merchants->get_datum(
			'',
			array(
				'merchant_email_address' => $email_address
			),
			array(),
			array(
				array(
					'table_name' 	=> 'merchant_accounts',
					'condition'		=> 'merchant_accounts.merchant_number = merchants.merchant_number'
				)
			)
		)->row();

		if ($merchant_row != "") {
			$acc_id = $merchant_row->account_number;

			if ($type == "merchant" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		end:

		return $flag;
	}

	public function validate_mobile_no($type, $country_id, $username, $id = "") {
		$flag = false;

		$this->load->model('api/client_accounts_model', 'client_accounts');
		$this->load->model('api/merchant_accounts_model', 'merchant_accounts');

		$client_row = $this->client_accounts->get_datum(
			'',
			array(
				'account_mobile_no' => $username,
				'country_id'		=> $country_id
			)
		)->row();

		if ($client_row != "") {
			$acc_id = $client_row->account_number;

			if ($type == "client" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		$merchant_account_row = $this->merchant_accounts->get_datum(
			'',
			array(
				'merchant_mobile_no' 	=> $username,
				'country_id'			=> $country_id
			),
			array(),
			array(
				array(
					'table_name' 	=> 'merchants',
					'condition'		=> 'merchants.merchant_number = merchant_accounts.merchant_number'
				)
			)
		)->row();

		if ($merchant_account_row != "") {
			$acc_id = $merchant_account_row->account_number;

			if ($type == "merchant" && $id != "") {
				if ($acc_id == $id) {
					$flag = false;
				} else {
					$flag = true;
					goto end;
				}
			} else {
				$flag = true;
				goto end;
			}
		}

		end:

		return $flag;
	}

	public function upload_files($folder_name, $files, $title = "", $is_data = false, $file_size_limit = 20, $allowed_types = "") {
		$upload_path = "{$this->_upload_path}/uploads/{$folder_name}";

		if (!file_exists($upload_path)) {
			mkdir($upload_path, 0755, true);
		}

        $config = array(
            'upload_path'   => $upload_path,
            'overwrite'     => 1,                       
		);
		
		if ($allowed_types != "") {
			$config = array_merge(
				$config,
				array(
					'allowed_types' => $allowed_types
				)
			);
		} else {
			$config = array_merge(
				$config,
				array(
					'allowed_types' => "*"
				)
			);
		}

        $this->load->library('upload', $config);

        $items = array();
		$error_uploads = array();
		$data = array();

		if (!is_array($files['name'])) {
			$tmp_file = $files;
			$files = array();

			$files['name'][]= $tmp_file['name'];
			$files['type'][]= $tmp_file['type'];
			$files['tmp_name'][]= $tmp_file['tmp_name'];
			$files['error'][]= $tmp_file['error'];
			$files['size'][]= $tmp_file['size'];
		}

        foreach ($files['name'] as $key => $file) {
            $_FILES['files[]']['name']= $files['name'][$key];
            $_FILES['files[]']['type']= $files['type'][$key];
            $_FILES['files[]']['tmp_name']= $files['tmp_name'][$key];
            $_FILES['files[]']['error']= $files['error'][$key];
			$_FILES['files[]']['size']= $files['size'][$key];
			
			$file_size = $files['size'][$key];

			if ($file_size > ($file_size_limit * MB)) {
				$error_uploads[] = array(
					'error_image' => $files['name'][$key],
					'error_message' => "The file size is over-limit from {$file_size_limit}MB limit!"
				);

				continue;
			}

			$ext = explode(".", $file);
			$ext = isset($ext[count($ext) - 1]) ? $ext[count($ext) - 1] : ""; 

			$today = strtotime($this->_today);

			if ($title != "") {
				$file_name = "{$title}_{$key}_{$today}";
				$file_name =  "{$file_name}.{$ext}";
			} else {
				$file_name = $file;
			}

            $items[] = $file_name;

            $config['file_name'] = $file_name;

            $this->upload->initialize($config);

            if ($this->upload->do_upload('files[]')) {
				$this->upload->data();

				// get file uploaded
				$full_path 		= "{$upload_path}/{$file_name}";

				if ($is_data) {
					$filecontent 	= file_get_contents($full_path);

					// update image save base64
					$data[] = array(
						'file_name' => $file_name,
						'base64_image' => rtrim(base64_encode($filecontent))
					);

					// delete uploaded image
					if(file_exists($full_path)){
						unlink($full_path);
					}
				} else {
					$data[] = array(
						'file_name' => $file_name,
						'full_path'	=> $full_path
					);
				}
            } else {
				$error_uploads[] = array(
					'error_image' => $files['name'][$key],
					'error_message' => $this->upload->display_errors()
				);
            }
        }

		return array(
			'results' => array(
				'is_data' 	=> $is_data,
				'data'		=> $data
			),
			'errors' => $error_uploads
		);
    }
	
	public function get_offset($page = 1, $limit = 10, $num_rows = 10) {
		$page 	= ($page < 1 ? 1 : $page);
		$offset = ($page - 1) * $limit;
		$offset = ($offset >= $num_rows && $page == 1 ? 0 : $offset);
		return $offset;
	}
}

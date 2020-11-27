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

	public function set_sms_otp($mobile_no) {
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

		$auth_bridge_id = $client_row->oauth_bridge_id;

		$row_access_token = $this->globe_access_token->get_datum(
			'',
			array(
				'token_auth_bridge_id' => $auth_bridge_id
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

		$expiration_time = 3;

		if (isset($_GET['expiration_time'])) {
			if (is_numeric($_GET['expiration_time'])) {
				$expiration_time = $_GET['expiration_time'];
			}
		}

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

		$expiration_date = create_expiration_datetime($this->_today, $expiration_time);

		$message		= "OTP: {$code}. Expiration Date: {$expiration_date}";

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
			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Unable to send OTP. Curl Error #: {$err}"
				)
			);
			die();
		} else {
			$decoded = json_decode($response);
			
			if (isset($decoded->error)) {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => $decoded->error
					)
				);
				die();
			}
		}

		$this->otp->insert(
			array(
				'otp_number'			=> $otp_number,
				'otp_code'				=> $code,
				'otp_date_expiration'	=> $expiration_date,
				'otp_date_created'		=> $this->_today,
				'otp_auth_bridge_id'	=> $auth_bridge_id
			)
		);

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

	public function get_fee($amount, $transaction_type_id, $admin_oauth_bridge_id) {
		$this->load->model("api/transaction_fees_model", "tx_fees");

		$fee = 0;

		// get transaction fee
		$row = $this->tx_fees->get_datum(
			'',
			array(
				'transaction_type_id'       => $transaction_type_id,
				'transaction_fee_from <='   => $amount,
				'transaction_fee_to >='     => $amount,
				'oauth_bridge_parent_id'    => $admin_oauth_bridge_id
			)
		)->row();

		if ($row != "") {
			$fee = $row->transaction_fee_amount;
		}

		return $fee;
	}

	public function distribute_income_shares($tx_parent_id, $merchant_no, $fee) {
		$this->load->model("api/transactions_model", "transactions");
		$this->load->model("api/merchants_model", "merchants");
		$this->load->model("api/merchant_accounts_model", "merchant_accounts");
		$this->load->model("api/income_scheme_merchants_model", "income_scheme_merchants");

		$admin_oauth_bridge_id 	= "";
		$transaction_type_id 	= "txtype_income_shares";

		$accumulated_fee = 0;
		$flag = false;

		/*
			- get merchant scheme group
			- get all merchant in scheme group
			- start from merchant index to lower index
		*/

		$row = $this->income_scheme_merchants->get_datum(
			'',
			array(
				'income_scheme_merchants.merchant_number'	=> $merchant_no
			),
			array(),
			array(
				array(
					'table_name'	=> 'merchants',
					'condition'		=> 'merchants.merchant_number = income_scheme_merchants.merchant_number'
				),
				array(
					'table_name'	=> 'oauth_bridges',
					'condition'		=> 'oauth_bridges.oauth_bridge_id = merchants.oauth_bridge_id'
				)
			)
		)->row();

		if ($row == "") {
			// if merchant not found redirect to admin tx
			// goto admin_income_tx;
			return;
		}

		$scheme_type_id 			= $row->scheme_type_id;
		$merchant_index				= $row->scheme_merchant_index;

		$admin_oauth_bridge_id		= $row->oauth_bridge_parent_id;

		$items = $this->income_scheme_merchants->get_data(
			array(
				'*'
			),
			array(
				'scheme_merchant_index <=' 	=> $merchant_index,
				'scheme_type_id'			=> $scheme_type_id
			),
			array(),
			array(
				array(
					'table_name'	=> 'merchants',
					'condition'		=> 'merchants.merchant_number = income_scheme_merchants.merchant_number'
				)
			),
			array(
				'filter'	=> 'scheme_merchant_index',
				'sort'		=> 'DESC'
			)
		);

		/*
			$amount, 
			$fee, 
			$transaction_type_id, 
			$requested_by_oauth_bridge_id, 
			$requested_to_oauth_bridge_id, 
			$created_by_oauth_bridge_id = null, 
			$expiration_minutes = 60, 
			$message = "",
			$tx_parent_id = ""
		*/

		$duration = 0;

		foreach($items as $item) {
			$amount = 0;
			$type	= $item['scheme_merchant_type'];
			$value	= $item['scheme_merchant_value'];

			$merchant_oauth_bridge_id	= $item['oauth_bridge_id'];

			// get first active account of merchant
			$merchant_account_row = $this->merchant_accounts->get_datum(
				'',
				array(
					'merchants.oauth_bridge_id' => $merchant_oauth_bridge_id,
					'merchant_status'			=> 1 // must be active merchant
				),
				array(),
				array(
					array(
						'table_name'	=> 'merchants',
						'condition'		=> 'merchants.merchant_number = merchant_accounts.merchant_number'
					)
				),
				array(
					'*',
					'merchant_accounts.oauth_bridge_id as account_oauth_bridge_id'
				)
			)->row();

			if ($merchant_account_row == "") {
				continue;
			}

			$account_oauth_bridge_id = $merchant_account_row->account_oauth_bridge_id;

			if ($type == 1) {
				// fixed value
				$amount = $value;
			} else if ($type == 2) {
				// percentage value
				$amount = $fee * ($value / 100);
			}

			$accumulated_fee += $amount;

			if ($fee < $accumulated_fee) {
				if ($flag) {
					continue;
				}
				
				$flag = true;

				// adjust share fee from remaining fee
				$amount = $fee - ($accumulated_fee - $amount);

				if ($amount < 1) {
					continue;
				}
			}

			if ($amount == 0) {
				continue;
			}

			$debit_wallet_address		= $this->get_wallet_address($admin_oauth_bridge_id);
			$credit_wallet_address	    = $this->get_wallet_address($merchant_oauth_bridge_id);

			// wallet cannot found
			if ($credit_wallet_address == "" || $debit_wallet_address == "") {
				continue;
			}
			
			$duration++;
			$new_date = date("Y-m-d H:i:s", strtotime("+$duration sec"));

			$tx_row = $this->create_transaction(
				$amount, 
				0, 
				$transaction_type_id,
				$admin_oauth_bridge_id,
				$account_oauth_bridge_id,
				$admin_oauth_bridge_id,
				60,
				"Income Shares",
				$tx_parent_id,
				$new_date
			);

			$tx_id 					= $tx_row['transaction_id'];
			$credit_total_amount 	= $amount;

			$credit_new_balances = $this->update_wallet($credit_wallet_address, $credit_total_amount);
			if ($credit_new_balances) {
				// record to ledger
				$this->new_ledger_datum(
					"income_shares", 
					$tx_id, 
					$debit_wallet_address, // debit from wallet address
					$credit_wallet_address, // credit to wallet address
					$credit_new_balances
				);
			}
		}


		// ADMIN INCOME TX START HERE
		// admin_income_tx:

		if ($fee >= $accumulated_fee) {
			// if ($tx_parent_id != "" && $admin_oauth_bridge_id == "") {
				
			// }

			$amount = $fee - $accumulated_fee;
			
			$debit_wallet_address		= $this->get_wallet_address($admin_oauth_bridge_id);
			$credit_wallet_address	    = $this->get_wallet_address($admin_oauth_bridge_id);

			// wallet cannot found
			if ($credit_wallet_address == "" || $debit_wallet_address == "") {
				return;
			}

			if ($amount == 0) {
				return;
			}

			$duration++;
			$new_date = date("Y-m-d H:i:s", strtotime("+$duration sec"));

			// remaining fee goes to admin
			$tx_row = $this->create_transaction(
				$amount, 
				0, 
				$transaction_type_id,
				$admin_oauth_bridge_id,
				$admin_oauth_bridge_id,
				$admin_oauth_bridge_id,
				60,
				"Income Shares",
				$tx_parent_id,
				$new_date
			);

			$tx_id 					= $tx_row['transaction_id'];
			$credit_total_amount 	= $amount;

			$credit_new_balances = $this->update_wallet($credit_wallet_address, $credit_total_amount);
			if ($credit_new_balances) {
				// record to ledger
				$this->new_ledger_datum(
					"income_shares", 
					$tx_id, 
					$debit_wallet_address, // debit from wallet address
					$credit_wallet_address, // credit to wallet address
					$credit_new_balances
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
			'ledger_datum_date_added'       => $this->_today
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

		$row = $this->wallet_addresses->get_datum(
			'',
			array(
				'oauth_bridge_id' => $bridge_id
			)
		)->row();

		if ($row == "") {
			return "";
		}

		return $row->wallet_address;
	}

	public function send_email_activation($send_to_email, $pin, $expiration_date = "") {
		if ($expiration_date != "") {
			if ($expiration_date > $this->_today) {
				generate_error_message("E010-2");
			}
		}

		$date	= strtotime($this->_today);
		$date 	= date("F j, Y, g:i a", $date);

		// send email activation
		$email_message = $this->load->view("templates/email_activation", array(
			"activation_pin" => $pin,
			"date"	=> $date
		), true);

		send_email(
			getenv("SMTPUSER"),
			$send_to_email,
			"Email Activation PIN",
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
		$expiration_minutes = 60, 
		$message = "",
		$tx_parent_id = "",
		$date = ""
	) {

		if ($date == "") {
			$date = $this->_today;
		}

		$this->load->model("api/transactions_model", "transactions");
		
		if (is_null($created_by_oauth_bridge_id)) {
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
			'transaction_otp_status'		=> 1 // temporary activated
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
}

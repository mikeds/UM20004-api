<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Api_Controller extends MX_Controller {
	protected
		$_today = "";

	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();
		$this->init();
	}

	private function init() {
		date_default_timezone_set( "Asia/Manila" );
		$this->_today = date("Y-m-d H:i:s");
		$this->after_init();
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

		$token = getBearerToken();

		if (is_null($token)) {
			return false;
		}

		$token_row = $this->tokens->get_datum(
			'',
			array(
				'access_token'	=> $token
			)
		)->row();

		if ($token_row == "") {
			return false;
		}

		return $token_row;
	}

	public function get_user_by_wallet_address($wallet_address) {
		
	}

	public function get_client_access() {
		$token_row = $this->get_token();

		if (!$token_row) {
			// invalid token
			// unauthorized request
			http_response_code(401);
			die();
		}

		$oauth_client_id = $token_row->client_id;

		$oauth_client_row = $this->get_oauth_client_by_id($oauth_client_id);

		if (!$oauth_client_row) {
			// unauthorized request
			http_response_code(401);
			die();
		}

		$bridge_id = $oauth_client_row->oauth_client_bridge_id;

		$row = $this->get_client_by_bridge_id($bridge_id);

		if (!$row) {
			// unauthorized request
			http_response_code(401);
			die();
		}

		$key = $oauth_client_row->client_id;
		$code = $oauth_client_row->client_secret;

		$wallet_address = $this->get_wallet_address($key, $code);

		if ($wallet_address == "") {
			// unauthorized request
			http_response_code(401);
			die();
		}

		return array(
			'client_row' 		=> $row,
			'wallet_address'	=> $wallet_address
		);
	}

	public function get_client($username, $status = 1) {
		$this->load->model('api/clients_model', 'clients');
		
		if (is_null($username)) {
			goto null_username;
		}

		$row_mobile = $this->clients->get_datum(
			'',
			array(
				'CONCAT(client_mobile_country_code, client_mobile_no) ='	=> $username,
				'client_status'												=> $status
			)
		)->row();

		$row_email = $this->clients->get_datum(
			'',
			array(
				'client_email_address'	=> $username,
				'client_status'			=> $status
			)
		)->row();

		if ($row_mobile == "" && $row_email == "") {
			null_username:

			header('Content-type: application/json');

			$message = array(
				'error' => true,
				'error_description' => "Unable to find client!"
			);

			http_response_code(200);
			echo json_encode($message);
			die();
		}

		return $row_mobile != "" ? $row_mobile : $row_email;
	}

	public function get_merchant($username, $status = 1) {
		$this->load->model('api/merchants_model', 'merchants');
		
		if (is_null($username)) {
			goto null_username;
		}

		$row_mobile = $this->merchants->get_datum(
			'',
			array(
				'CONCAT(merchant_mobile_country_code, merchant_mobile_no) ='	=> $username,
				'merchant_status'												=> $status
			)
		)->row();

		$row_email = $this->merchants->get_datum(
			'',
			array(
				'merchant_email_address'	=> $username,
				'merchant_status'			=> $status
			)
		)->row();

		if ($row_mobile == "" && $row_email == "") {
			null_username:

			header('Content-type: application/json');

			$message = array(
				'error' => true,
				'error_description' => "Unable to find merchant!"
			);

			http_response_code(200);
			echo json_encode($message);
			die();
		}

		return $row_mobile != "" ? $row_mobile : $row_email;
	}

	public function generate_transaction_number($amount, $from_wallet_address, $to_wallet_address) {
		$date_expiration = $this->generate_date_expiration();

		$transaction_data = json_encode(
			array(
				'from_wallet_address'	=> $from_wallet_address,
				'to_wallet_address'		=> $to_wallet_address,
				'date_created'			=> $this->_today,
				'date_expiration'		=> "{$date_expiration}",
				'amount'				=> $amount
			)
		);

		return hash("md5", $transaction_data);
	}

	public function get_client_by_bridge_id($bridge_id) {
		$this->load->model('api/clients_model', 'clients');

		$row = $this->clients->get_datum(
			'',
			array(
				'oauth_client_bridge_id'	=> $bridge_id
			)
		)->row();

		if ($row == "") {
			return false;
		}

		return $row;
	}

	public function get_oauth_client_by_id($oauth_client_id) {
		$this->load->model('api/oauth_clients_model', 'oauth_clients');

		$oauth_client_row = $this->oauth_clients->get_datum(
			'',
			array(
				'client_id'	=> $oauth_client_id
			)
		)->row();

		if ($oauth_client_row == "") {
			return false;
		}

		return $oauth_client_row;
	}

	public function set_oauth_bridge() {
		$this->load->model('api/oauth_client_bridges_model', 'oauth_client_bridges');
		
		$today = date("Y-m-d H:i:s");

		// create ouath client bridges
		$bridge_id = $this->oauth_client_bridges->insert(
			array(
				'oauth_client_bridge_date_added'	=> $today
			)
		);

		return $bridge_id;
	}

	public function set_oauth_client($bridge_id) {
		$this->load->model('api/oauth_clients_model', 'oauth_clients');

		$today = date("Y-m-d H:i:s");
		$key = generate_password(19) . strtotime($today);
		$key = hash("sha256", $key);

		$code = generate_password(14) . strtotime($today);
		$code = hash("sha256", $code);

		// create secret key and secret code
		$this->oauth_clients->insert(
			array(
				'client_id'					=> $key,
				'client_secret'				=> $code,
				'oauth_client_bridge_id'	=> $bridge_id
			)
		);

		// create wallet address
		$this->set_wallet_address($bridge_id, $key, $code);
	}

	public function get_oauth_client($bridge_id) {
		$this->load->model('api/oauth_clients_model', 'oauth_clients');

		$key = "";
		$code = "";

		$oauth_client_row = $this->oauth_clients->get_datum(
			'',
			array(
				'oauth_client_bridge_id'	=> $bridge_id,
				'oauth_client_bridge_id !='	=> 0
			)
		)->row();

		if ($oauth_client_row != "") {
			$key = $oauth_client_row->client_id;
			$code = $oauth_client_row->client_secret;
		}

		return array(
			'key'	=> $key,
			'code'	=> $code
		);
	}

	public function set_wallet_address($bridge_id, $key, $code) {
		$syskey = getenv("SYSKEY");

		$this->load->model('api/wallets_model', 'wallets');
		// create client wallet
		// combination of client primary id and client id/key
		// $wallet_address = hash_hmac("sha256", "", $code);
		$json = json_encode(
			array(
				'key'	=> $key,
				'code'	=> $code
			)
		);

		$wallet_address = hash_hmac("sha256", $json, $syskey);
		
		// insert new wallet
		$this->wallets->insert(
			array(
				'wallet_address'			=> $wallet_address,
				'oauth_client_bridge_id'	=> $bridge_id
			)
		);
	}

	public function get_wallet_address($key, $code) {
		$syskey = getenv("SYSKEY");

		$this->load->model('api/wallets_model', 'wallets');
		$wallet_address = "";

		// create client wallet
		// combination of client primary id and client id/key
		// $wallet_address = hash_hmac("sha256", "", $code);
		$json = json_encode(
			array(
				'key'	=> $key,
				'code'	=> $code
			)
		);

		$address = hash_hmac("sha256", $json, $syskey);

		$row = $this->wallets->get_datum(
			'',
			array(
				'wallet_address'	=> $address
			)
		)->row();

		if ($row != "") {
			$wallet_address = $row->wallet_address;
		}

		return $wallet_address;
	}

	public function get_wallet_by_bridge_id($bridge_id) {
		$this->load->model('api/wallets_model', 'wallets');
		$wallet_address = "";

		$row = $this->wallets->get_datum(
			'',
			array(
				'oauth_client_bridge_id' => $bridge_id
			)
		)->row();

		if ($row == "") {
			header('Content-type: application/json');

			$message = array(
				'error' => true,
				'error_description' => "Unable to find wallet address!"
			);

			http_response_code(200);
			echo json_encode($message);
			die();
		}

		return $row;
	}

	public function get_wallet_address_by_bridge_id($bridge_id) {
		$this->load->model('api/wallets_model', 'wallets');
		$wallet_address = "";

		$row = $this->wallets->get_datum(
			'',
			array(
				'oauth_client_bridge_id' => $bridge_id
			)
		)->row();

		if ($row != "") {
			$wallet_address = $row->wallet_address;
		}

		return $wallet_address;
	}

	public function generate_date_expiration($minutes = 30) {
		$newtimestamp = strtotime("{$this->_today} + {$minutes} minute");
		return date('Y-m-d H:i:s', $newtimestamp);
	}

	public function send_verification($email_to, $email_message = "") {
		header('Content-type: application/json');

		$email_from = "no-reply@resolveitthrough.us";
		$email_subject = "BambuPAY - Account confirmation code";

		// do send email
		// send confirmation code
		if (!send_email(
			$email_from,
			$email_to,
			$email_subject,
			$email_message,
			"BambuPAY Service"
		)){
			$message = array(
				'error' => true,
				'error_description' => "Failed to send confirmation code!"
			);
	
			goto end;
		}

		$message = array(
			'error' => false,
			'message'	=> "Successfully resend account verification code!"
		);

		end:
		http_response_code(200);
		echo json_encode($message);
		die();
	}
}
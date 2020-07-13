<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Merchant_Controller extends Api_Controller {
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

	public function after_init() {}

	public function get_merchant_access() {
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

		$row = $this->get_merchant_by_bridge_id($bridge_id);

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
			'merchant_row' 		=> $row,
			'wallet_address'	=> $wallet_address
		);
	}

	public function get_merchant_by_bridge_id($bridge_id) {
		$this->load->model('api/merchants_model', 'merchants');

		$row = $this->merchants->get_datum(
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
}
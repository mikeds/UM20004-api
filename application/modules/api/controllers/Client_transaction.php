<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Client_transaction extends Api_Controller {
	private
		$_client = NULL;

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');
		$this->load->model('api/clients_model', 'clients');
		$this->load->model('api/merchants_model', 'merchants');
		$this->load->model('api/transactions_model', 'transactions');
		$this->load->model('api/wallets_model', 'wallets');

		$this->oauth2->get_resource();
		$this->_client = $this->get_client_access();
	}

	public function cash_in() {
		$this->make_transaction("cash_in");
	}

	public function cash_out() {
		$this->make_transaction("cash_out");
	}

	public function send_to() {
		$this->make_transaction("send_to");
	}

	private function make_transaction($type) {
		header('Content-type: application/json');

		if (empty($this->_client)) {
			// invalid wallet address
			$message = array(
				'error' => true, 
				'error_description' => 'Wallet address is error!'
			);

			goto end;
		}

		$message = "";
		$wallet_address = $this->_client['wallet_address'];
		$to_wallet_address = "";
		$from_wallet_address = "";
		$type_id = "";
		$status = 0;
		$message_data = array();

		if ($_POST) {
			$amount		= $this->input->post("amount");

			if (!is_numeric($amount)) {
				$message = array(
					'error' => true, 
					'error_description' => 'Invalid amount!'
				);

				goto end;
			}

			$amount = floatval($amount);

			if ($type == "cash_in" || $type == "cash_out") {
				$merchant		= $this->input->post("merchant");
				$message_data = array(
					'merchant'	=> $merchant
				);

				$merchant_wallet_address = $this->get_merchant_wallet_address($merchant);

				if (is_array($merchant_wallet_address)) {
					// error return
					$message = $merchant_wallet_address;
					goto end;
				}

				if ($type == "cash_in") {
					$type_id = 1;
	
					// from merchant to client - cash in
					$from_wallet_address = $merchant_wallet_address; // merchant
					$to_wallet_address = $wallet_address; // client
				} else if ($type == "cash_out") {
					$type_id = 2;

					// from client to merchant - cash out
					$from_wallet_address = $wallet_address; // client
					$to_wallet_address = $merchant_wallet_address; // merchant
				}
			} else if ($type == "send_to") {
				$send_to	= $this->input->post("send_to");
				$type_id 	= 3;

				// get to client row
				$to_client_row = $this->get_client($send_to);

				// get to wallet address
				$to_client_bridge_id = $to_client_row->oauth_client_bridge_id;
				$to_wallet_address = $this->get_wallet_address_by_bridge_id($to_client_bridge_id);

				// from client to merchant - cash out
				$from_wallet_address = $wallet_address; // client
				$to_wallet_address = $to_wallet_address; // merchant

				// cannot be send to self
				if ($from_wallet_address == $to_wallet_address) {
					$message = array(
						'error' => true, 
						'error_description' => 'Cannot be send to self!'
					);

					goto end;
				}
			}

			if ($from_wallet_address == "" || $to_wallet_address == "") {
				$message = array(
					'error' => true, 
					'error_description' => 'Invalid transaction!'
				);

				goto end;
			}

			if ($type_id == "") {
				// bad request
				http_response_code(401);
				echo json_encode($message);
				die();
			}

			if ($type_id == 2 || $type_id == 3) {
				// check client balance
				$client_row = $this->_client['client_row'];
				$client_bridge_id = $client_row->oauth_client_bridge_id;

				$client_wallet_row = $this->get_wallet_by_bridge_id($client_bridge_id);
				$client_wallet_balance = $client_wallet_row->wallet_balance;
				$client_wallet_holding_balance = $client_wallet_row->wallet_holding_balance;

				if ($client_wallet_balance < $amount) {
					$message = array(
						'error' => true, 
						'error_description' => 'insufficient balance!'
					);

					goto end;
				}
				
				// update from wallet balance 
				$new_balance = $client_wallet_balance - $amount;

				// cash out filter
				if ($type_id == 2) {
					// update wallet holding balance
					$new_holding_balance = $client_wallet_holding_balance + $amount;

					// update wallet balance and wallet holding balance
					$this->wallets->update(
						$wallet_address,
						array(
							'wallet_balance'			=> $new_balance,
							'wallet_holding_balance'	=> $new_holding_balance
						)
					);

					$message_data = array(
						'merchant'		=> $merchant,
						'balance'		=> $new_balance,
						'hold_balance'	=> $new_holding_balance
					);
				} else if ($type_id == 3) {
					$send_to		= $this->input->post("send_to");

					// update status to approved automatically
					$status = 1;
					
					// get to client row
					$to_client_row = $this->get_client($send_to);

					// recipient data
					$to_client_bridge_id = $to_client_row->oauth_client_bridge_id;
					$to_wallet_row = $this->get_wallet_by_bridge_id($to_client_bridge_id);
					$to_wallet_balance = $to_wallet_row->wallet_balance;
					$to_wallet_address = $to_wallet_row->wallet_address;

					// update from client balance
					$this->wallets->update(
						$wallet_address,
						array(
							'wallet_balance' => $new_balance,
						)
					);

					// update to client balance
					$to_new_balance = $to_wallet_balance + $amount;

					$this->wallets->update(
						$to_wallet_address,
						array(
							'wallet_balance' => $to_new_balance,
						)
					);

					$message_data = array(
						'send_to'		=> $send_to,
						'balance'		=> $new_balance,
					);
				}
			}

			$date_expiration = $this->generate_date_expiration();
			$transaction_number = $this->create_transaction($type_id, $amount, $wallet_address, $from_wallet_address, $to_wallet_address, $date_expiration, $status);

			$message = array(
				'value'	=>
				array_merge(
					array(
						'transaction_number'	=> strtoupper($transaction_number),
					),
					$message_data,
					array(
						'amount'				=> $amount,
						'request_expiration'	=> $date_expiration
					)
				)
			);

			goto end;
		} else {
			// unauthorized request
			http_response_code(401);
			die();
		}

		end:
		// bad request
		http_response_code(200);
		echo json_encode($message);
		die();
	}

	private function create_transaction($type_id, $amount, $wallet_address, $from_wallet_address, $to_wallet_address, $date_expiration, $status = 0) {
		$transaction_number = $this->generate_transaction_number($amount, $from_wallet_address, $to_wallet_address);

		$data = array(
			'transaction_type_id'	=> $type_id, //cash in
			'transaction_number'	=> $transaction_number,
			'transaction_amount'	=> $amount,
			'transaction_requested_by'			=> $wallet_address,
			'transaction_from_wallet_address'	=> $from_wallet_address,
			'transaction_to_wallet_address'		=> $to_wallet_address,
			'transaction_date_expiration'		=> $date_expiration,
			'transaction_status'				=> $status
		);

		$this->transactions->insert($data);

		return $transaction_number;
	}

	private function get_merchant_wallet_address($merchant) {
		if (empty($merchant)) {
			return array(
				'error' => true, 
				'error_description' => 'Unable to find recipient!'
			);
		}

		$merchant_row = $this->get_merchant($merchant);

		if ($merchant_row == "") {
			return array(
				'error' => true, 
				'error_description' => 'Invalid recipient!'
			);
		}

		$mbridge_id = $merchant_row->oauth_client_bridge_id;

		$merchant_wallet_address = $this->get_wallet_address_by_bridge_id($mbridge_id);

		if ($merchant_wallet_address == "") {
			return array(
				'error' => true, 
				'error_description' => 'Cannnot find recipient wallet address!'
			);
		}

		return $merchant_wallet_address;
	}

	private function get_client_access() {
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

		$client_row = $this->get_client_by_bridge_id($bridge_id);

		if (!$client_row) {
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
			'client_row' 		=> $client_row,
			'wallet_address'	=> $wallet_address
		);
	}
}
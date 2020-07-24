<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Merchant_transaction extends Merchant_Controller {
	private
		$_merchant = NULL,
		$_limit = 10;

	public function after_init() {
		$this->load->library('OAuth2', 'oauth2');

		$this->oauth2->get_resource();
		$this->_merchant = $this->get_merchant_access();

		$this->load->model('api/transactions_model', 'transactions');
		$this->load->model('api/wallets_model', 'wallets');
	}

	public function accept_cash_in() {
		// type_id = 1
		$this->make_transaction("cash_in");
	}

	public function accept_cash_out() {
		// type_id = 2
		$this->make_transaction("cash_out");
	}

    private function make_transaction($type) {
		header('Content-type: application/json');
		$post = json_decode($this->input->raw_input_stream, true);

		$message = "";
		$wallet_address = $this->_merchant['wallet_address'];

		$merchant_wallet_row = $this->wallets->get_datum(
			'',
			array(
				'wallet_address' => $wallet_address
			)
		)->row();

		if($merchant_wallet_row == "") {
			$message = array('error'=> true, 'error_message'=> 'Invalid merchant address.');
			
			goto end;
		}		

		if ($this->JSON_POST()) {
			$transaction_number = isset($post["transaction_number"]) ? $post["transaction_number"] : "";
			$transaction_number = strtolower($transaction_number);

			$address_to = $type == "cash_out" ? "transaction_to_wallet_address" : "transaction_from_wallet_address";
			
			$datum = $this->transactions->get_datum(
				'',
				array(
					$address_to => $merchant_wallet_row->wallet_address,
					// 'transaction_date_expiration >=' => $this->_today,
					'transaction_number' 	=> $transaction_number,
					// 'transaction_status' => 0, // pending,
				)
			)->row();
			
			if ($datum == "") {
				$message = array(
					'error' => true,
					'error_description' => "Transaction invalid."
				);

				goto end;
			}

			$date_expiration 	= $datum->transaction_date_expiration;
			$status 			= $datum->transaction_status;
			$total_amount 		= $datum->transaction_total_amount;
			$type_id			= $datum->transaction_type_id;

			if ($status != 0) {
				$message = array(
					'error' => true,
					'error_description' => "Transaction is expired or cancelled."
				);

				goto end;
			}

			// from client to merchant
			if ($type == "cash_out") {
				if ($type_id != 2) {
					$message = array(
						'error' => true,
						'error_description' => "Invalid transaction type!"
					);

					goto end;
				}

				// 1. get client wallet details
				// 2. check client wallet on hold balance
				// 3. do transaction
				// 4. update transaction status
				// 5. record to ledger

				$client_wallet_row = $this->wallets->get_datum(
					'',
					array(
						'wallet_address' => $datum->transaction_from_wallet_address
					)
				)->row();

				if ($client_wallet_row == "") {
					$message = array(
						'error' => true,
						'error_description' => "Invalid client wallet address."
					);

					goto end;
				}

				$client_wallet_balance 	= $client_wallet_row->wallet_balance;
				$wallet_holding_balance = $client_wallet_row->wallet_holding_balance;

				if (strtotime($date_expiration) < strtotime($this->_today)) {
					// update if not updated
					if ($status == 0) {
						// if still on pending but date is expired
						$this->transactions->update(
							$datum->transaction_id,
							array(
								'transaction_status' => 2 // make status cancel
							)
						);

						// hold balance return to client balance
						$new_client_wallet_balance = 0;
						$new_client_wallet_holding_balance = 0;

						if ($wallet_holding_balance < $total_amount) {
							// this might be error but we need to override this
							// new wallet holding balance
							$new_client_wallet_balance 			= $client_wallet_balance + $wallet_holding_balance;
							$new_client_wallet_holding_balance 	= 0;
						} else {
							$new_client_wallet_balance 			= $client_wallet_balance + $total_amount;
							$new_client_wallet_holding_balance 	= $wallet_holding_balance - $total_amount;
						}

						$this->wallets->update(
							$client_wallet_row->wallet_address,
							array(
								'wallet_balance' => $new_client_wallet_balance,
								'wallet_holding_balance' => $new_client_wallet_holding_balance
							)
						);
					}

					$message = array(
						'error' => true,
						'error_description' => "Transaction expired."
					);

					goto end;
				}

				if ($wallet_holding_balance < $total_amount) {
					$message = array(
						'error' => true,
						'error_description' => "Client on hold balance is error."
					);

					goto end;
				}

				$new_wallet_holding_balance = $wallet_holding_balance - $total_amount;

				// update client holding balance
				$this->wallets->update(
					$client_wallet_row->wallet_address,
					array(
						'wallet_holding_balance' => $new_wallet_holding_balance
					)
				);

				$merchant_new_balance = $merchant_wallet_row->wallet_balance + $total_amount;

				// update merchant balance
				$this->wallets->update(
					$merchant_wallet_row->wallet_address,
					array(
						'wallet_balance' => $merchant_new_balance
					)
				);

				// update transaction status
				$this->transactions->update(
					$datum->transaction_id,
					array(
						'transaction_date_approved' => $this->_today,
						'transaction_status' => 1, // approved
					)
				);

				// done success transaction
				$message = array(
					'error' => false, 
					'message' => 'Success, Cash-out transaction done!'
				);
			} else if ($type == "cash_in") {
				if ($datum->transaction_type_id != 1) {
					$message = array(
						'error' => true,
						'error_description' => "Invalid transaction type!"
					);

					goto end;
				}

				if (strtotime($date_expiration) < strtotime($this->_today)) {
					// update if not updated
					if ($status == 0) {
						// if still on pending but date is expired
						$this->transactions->update(
							$datum->transaction_id,
							array(
								'transaction_status' => 2 // make status cancel
							)
						);

						$message = array(
							'error' => true,
							'error_description' => "Transaction expired."
						);
	
						goto end;
					}
				}

				// 1. get client wallet details
				// 2. check merchant wallet balance
				// 3. do transaction
				// 4. update transaction status
				// 5. record to ledger

				$client_wallet_row = $this->wallets->get_datum(
					'',
					array(
						'wallet_address' => $datum->transaction_to_wallet_address
					)
				)->row();

				if ($client_wallet_row == "") {
					$message = array(
						'error' => true,
						'error_description' => "Invalid client wallet address."
					);

					goto end;
				}

				// check merchant wallet balance
				$merchant_balance = $merchant_wallet_row->wallet_balance;
				
				if ($merchant_balance < $total_amount) {
					$message = array(
						'error' => true,
						'error_description' => "insufficient merchant balance."
					);

					goto end;
				}

				// do transaction
				$merchant_new_balance = $merchant_balance - $total_amount;
				$this->wallets->update(
					$merchant_wallet_row->wallet_address,
					array(
						'wallet_balance' => $merchant_new_balance
					)
				);

				$client_balance = $client_wallet_row->wallet_balance;
				$client_new_balance = $client_balance + $total_amount;

				// update client holding balance
				$this->wallets->update(
					$client_wallet_row->wallet_address,
					array(
						'wallet_balance' => $client_new_balance
					)
				);

				// update transaction status
				$this->transactions->update(
					$datum->transaction_id,
					array(
						'transaction_date_approved' => $this->_today,
						'transaction_status' => 1, // approved
					)
				);

				// done success transaction
				$message = array(
					'error' => false, 
					'message' => 'Success, Cash-in transaction done!'
				);
			} else {
				// unauthorized request
				http_response_code(401);
				die();
			}
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
}

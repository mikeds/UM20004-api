<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Client_Controller extends Api_Controller {
	protected
		$_account = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();
		
		$this->validate_token();
		$this->validate_access();
		$this->after_init();
	}

	private function validate_token() {
		$this->load->library("oauth2");
		$this->oauth2->get_resource();
	}

	private function validate_access() {
		$this->load->model("api/oauth_bridges_model", "bridges");

		$token_row = $this->get_token();
		$client_id = $token_row->client_id;

		$inner_joints = array(
			array(
				'table_name' 	=> 'client_accounts',
				'condition'		=> 'client_accounts.oauth_bridge_id = oauth_bridges.oauth_bridge_id'
			),
			array(
				'table_name' 	=> 'wallet_addresses',
				'condition'		=> 'wallet_addresses.oauth_bridge_id = client_accounts.oauth_bridge_id'
			)
		);

		$row = $this->bridges->get_datum(
			'',
			array(
				'client_accounts.oauth_bridge_id' => $client_id
			),
			array(),
			$inner_joints,
			array(
				'*',
				'client_accounts.oauth_bridge_id as account_oauth_bridge_id'
			)
		)->row();
		
		if ($row == "") {
			generate_error_message("E003-1");
		}
		
		$this->_account = $row;
	}

	public function filter_ledger($data) {
		$results = array();

		foreach ($data as $datum) {
			$row = $this->get_oauth_account_info($datum['transaction_requested_by']);

			$tmp_result = $datum;

			if ($row) {
				$tmp_result['transaction_requested_by'] = trim($row['account_fname'] . " ". $row['account_lname'] . " " . $row['account_lname']);
			} else {
				$tmp_result['transaction_requested_by'] = "SYSTEM";
			}

			$results[] = $tmp_result;
		}

		return $results;
	}

	public function filter_client_tx($data) {
		$account = $this->_account;

		$account_oauth_bridge_id = $account->account_oauth_bridge_id;

		$this->load->model("api/transactions_model", "transactions");

		$results = array();
		
		foreach($data as $datum) {
			$balance_type = "";
			$tx_status = "";

			$group_id 	= $datum['transaction_type_group_id'];
			$status		= $datum['transaction_status'];
			$expiration = $datum['transaction_date_expiration'];

			if ($group_id == 3 || $group_id == 5) {
				if ($group_id == 3) {
					$balance_type = "credit";
				}

				if ($group_id == 5) {
					if ($group_id == 5 && $datum['transaction_requested_to'] == $account_oauth_bridge_id) {
						$balance_type = "credit";
					} else if ($group_id == 5 && $datum['transaction_requested_by'] == $account_oauth_bridge_id) {
						$balance_type = "debit";
					} else {
						$balance_type = "credit";
					}
				}
			} else {
				$balance_type = "debit";
			}

			if ($status == 1) {
				$tx_status = "approved";
			} else if ($status == 2) {
				$tx_status = "cancelled";
			} else {
				$tx_status = "pending";
			}

			if ($status == 0) {
				if (strtotime($expiration) < strtotime($this->_today)) {
					$tx_status = "cancelled";

					$this->transactions->update(
						$datum['transaction_id'],
						array(
							'transaction_status' => 2
						)
					);
				}
			}

			$from_account_info 		= $this->get_oauth_account_info($datum['transaction_requested_by']);
			$to_account_info 		= $this->get_oauth_account_info($datum['transaction_requested_to']);
			$created_account_info 	= $this->get_oauth_account_info($datum['transaction_created_by']);

			if ($datum['transaction_type_id'] == 'txtype_scanpayqr1') {
				$from_account_info 		= $this->get_oauth_account_info($datum['transaction_requested_to']);
				$to_account_info 		= $this->get_oauth_account_info($datum['transaction_requested_by']);
			}

			$from 	= "";
			$to 	= "";
			$created_by	= "";

			if ($from_account_info) {
				$from = trim("{$from_account_info['account_fname']} {$from_account_info['account_mname']} {$from_account_info['account_lname']}");
			}

			if ($to_account_info) {
				$to = trim("{$to_account_info['account_fname']} {$to_account_info['account_mname']} {$to_account_info['account_lname']}");
			}

			if ($created_account_info) {
				$created_by = trim("{$created_account_info['account_fname']} {$created_account_info['account_mname']} {$created_account_info['account_lname']}");
			}

			$account_no_by = $created_account_info['account_number'];

			// if ($balance_type == 'credit') {
			// 	$tx_from = $to;
			// 	$tx_to = $from;
			// } else {
			// 	$tx_from = $from;
			// 	$tx_to = $to;
			// }

			$results[] = array(
				'tx_id' 			=> $datum['transaction_id'],
				'sender_ref_id' 	=> $datum['transaction_sender_ref_id'],
				'tx_account_no_by'	=> $account_no_by,
				'tx_created_by'		=> $created_by,
				'tx_from'			=> $from,
				'tx_to'				=> $to,
				'amount' 			=> $datum['transaction_amount'],
				'fee' 				=> $datum['transaction_fee'],
				'tx_message'		=> $datum['transaction_message'],
				'tx_type_code'		=> $datum['transaction_type_code'],
				'tx_type' 			=> $datum['transaction_type_name'],
				'date_created' 		=> $datum['transaction_date_created'],
				'tx_status'			=> $tx_status,
				'balance_type'		=> $balance_type,
				'qr_code'			=> base_url() . "qr-code/transactions/" . $datum['transaction_sender_ref_id']
			);
		}

		return $results;
	}
}

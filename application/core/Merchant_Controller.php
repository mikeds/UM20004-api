<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Merchant_Controller extends Api_Controller {
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
				'table_name' 	=> 'merchant_accounts',
				'condition'		=> 'merchant_accounts.oauth_bridge_id = oauth_bridges.oauth_bridge_id'
			),
			array(
				'table_name' 	=> 'merchants',
				'condition'		=> 'merchants.merchant_number = merchant_accounts.merchant_number'
			),
			array(
				'table_name' 	=> 'wallet_addresses',
				'condition'		=> 'wallet_addresses.oauth_bridge_id = merchants.oauth_bridge_id'
			)
		);

		$row = $this->bridges->get_datum(
			'',
			array(
				'merchant_accounts.oauth_bridge_id' => $client_id
			),
			array(),
			$inner_joints,
			array(
				'*',
				'merchant_accounts.oauth_bridge_id as account_oauth_bridge_id',
				'merchants.oauth_bridge_id as merchant_oauth_bridge_id'
			)
		)->row();
		
		if ($row == "") {
			generate_error_message("E003-1");
		}
		
		$this->_account = $row;
	}

	public function filter_merchant_tx($data) {
		$this->load->model("api/transactions_model", "transactions");

		$results = array();
		
		foreach($data as $datum) {
			$balance_type = "";
			$tx_status = "";

			$group_id 	= $datum['transaction_type_group_id'];
			$status		= $datum['transaction_status'];
			$expiration = $datum['transaction_date_expiration'];

			if ($group_id == 1 || $group_id == 6 || $group_id == 7 || $group_id == 8 || $group_id == 9) {
				$balance_type = "credit";
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

			$from 	= "ADMIN";
			$to 	= "ADMIN";
			$created_by	= "ADMIN";

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
				'tx_type_code'		=> $datum['transaction_type_code'],
				'tx_type' 			=> $datum['transaction_type_name'],
				'date_created' 		=> $datum['transaction_date_created'],
				'tx_status'			=> $tx_status,
				'balance_type'		=> $balance_type,
				'qr_code'			=> $datum['qr_code']
			);
		}

		return $results;
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
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lookup extends Tms_admin_Controller {

	public function after_init() {
		if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function tx_list() {
		$this->load->model("api/transaction_types_model", "tx_types");

		$results = $this->tx_types->get_data(
			array(
				'transaction_type_id as tx_type_id',
				'transaction_type_name'
			)
		);

		echo json_encode(
			array(
				'response'  => $results
			)
		);
	}

	public function tx_fee() {
		$this->load->model("api/transaction_types_model", "tx_types");
		$this->load->model("api/transaction_fees_model", "tx_fees");

		if (isset($_GET['tx_type_id']) && isset($_GET['amount'])) {
			$admin_oauth_bridge_id = $this->_account->oauth_bridge_id;

			$tx_type_id = $_GET['tx_type_id'];
			$amount		= $_GET['amount'];

			if (is_decimal($amount)) {
				goto end;
			}
			
			$fee = 0;

			$row = $this->tx_types->get_datum(
				'',
				array(
					'transaction_type_id'	=> $tx_type_id
				)
			)->row();

			if ($row == "") {
				goto end;
			}

			// get transaction fee
			$tx_row = $this->tx_fees->get_datum(
				'',
				array(
					'transaction_type_id'       => $tx_type_id,
					'transaction_fee_from <='   => $amount,
					'transaction_fee_to >='     => $amount,
					'oauth_bridge_parent_id'    => $admin_oauth_bridge_id
				)
			)->row();

			if ($tx_row != "") {
				$fee = $tx_row->transaction_fee_amount;
			}

			echo json_encode(
				array(
					'message'   => "Successfully retrieved lookup fee.",
					'response'  => array(
						'amount'	=> $amount,
						'fee' 		=> $fee,
						'timestamp'	=> $this->_today
					)
				)
			);
			
			return;
		}

		end:
		// unauthorized access
		$this->output->set_status_header(401);
	}
}

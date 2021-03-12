<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lookup_merchant extends Merchant_Controller {

	public function after_init() {
		if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function tx($ref_id) {
		$this->load->model("api/transactions_model", "transactions");	

		$inner_joints = array(
			array(
				'table_name' 	=> 'transaction_types',
				'condition'		=> 'transaction_types.transaction_type_id = transactions.transaction_type_id'
			)
		);

		$row = $this->transactions->get_datum(
			'',
			array(
				'transaction_sender_ref_id' => $ref_id
			),
			array(),
			$inner_joints
		)->row();

		if ($row == "") {
			echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "Cannot find transaction."
                )
            );
			die();
		}

		$tx_id		= $row->transaction_id;
		$ref_id		= $row->transaction_sender_ref_id;
		$amount 	= $row->transaction_amount;
		$fee		= $row->transaction_fee;
		$tx_name	= $row->transaction_type_name;
		$status		= $row->transaction_status;
		$status		= ($status == 1 ? "Approved" : ($status == 2 ? "Cancelled" : "Pending"));

		echo json_encode(
			array(
				'message'   => "Successfully retrieved tx details",
				'response'  => array(
					'id'		=> $tx_id,
					'ref_id'	=> $ref_id,
					'type'		=> $tx_name,
					'amount'	=> $amount,
					'fee' 		=> $fee,
					'status'	=> $status,
					'timestamp'	=> $this->_today
				)
			)
		);
	}
}

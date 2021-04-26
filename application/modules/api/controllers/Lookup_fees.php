<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lookup_fees extends Api_Controller {
	public function after_init() {
        $this->load->library("oauth2");
		$this->oauth2->get_resource();
    }

	public function fees() {
		if ($_SERVER['REQUEST_METHOD'] != 'POST' || !$this->JSON_POST()) {
			goto end;
		}

		$post       			= $this->get_post();
		$amount					= $post['amount'];
		$transaction_type_id 	= $post['tx_type_id'];

		if ($tx_type_id == "") {
			echo json_encode(
				array(
					'error'				=> true,
					'error_description' => "Tx type id is empty!",
					'timestamp'			=> $this->_today
				)
			);
			die();
		}

        // get fee
        $fees = $this->get_fee(
            $amount,
            $transaction_type_id
        );

		$total_amount = $amount + $fees;

		echo json_encode(
			array(
				'message' 	=> "Succesfully retrieve tx fee! ",
				'response'	=> array(
					'amount'		=> $amount,
					'fees'			=> $fees,
					'total_amount'	=> $total_amount
				),
				'timestamp'	=> $this->_today
			)
		);
		die();

		end:
		// unauthorized access
		$this->output->set_status_header(401);
	}
}

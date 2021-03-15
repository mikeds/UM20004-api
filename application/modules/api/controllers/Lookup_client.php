<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lookup_client extends Client_Controller {

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

	public function instapay_banks() {
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api-uat.unionbankph.com/partners/sb/partners/v3/instapay/banks",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				'x-ibm-client-id: 854b7778-c9d3-4b3e-9fd5-21c828f7df39',
				'x-ibm-client-secret: mJ5bF5kG2mK8bV3wP5oV4qT6iQ0eW8cT4kG5yR3eD1nV4wP7uM',
				'x-partner-id: 5dff2cdf-ef15-48fb-a87b-375ebff415bb'
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			echo json_encode(
				array(
					'error'             => true,
					'error_description' => "Unable to generate token. Curl Error #: {$err}",
					'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
				)
			);
			die();
		}

		$decoded = json_decode($response);

		if (!isset($decoded->records)) {
            echo json_encode(
                array(
                    'error'             => true,
                    'error_description' => "No records found."
                )
            );
            die();
		}

		$records = $decoded->records;
		// $records = array_merge(
		// 	array(
		// 		"code" => "010419995",
		// 		"bank" => "UNION BANK OF THE PHILIPPINES",
		// 		"brstn" => null
		// 	),
		// 	$records
		// );
		
		echo json_encode(
            array(
                'response' => $records,
				'timestamp' => $this->_today
            )
        );
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
			$admin_oauth_bridge_id = $this->_account->oauth_bridge_parent_id;

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

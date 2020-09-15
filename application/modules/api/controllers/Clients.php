<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Clients extends Client_Controller {

	public function after_init() {}

	public function balance() {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }
        
        $account    = $this->_account;

        $balance    = $this->decrypt_wallet_balance($account->wallet_balance);

        echo json_encode(
            array(
                'response' => array(
                    'account_number'=> $account->account_number,
                    'balance'       => $balance
                )
            )
        );
    }


    public function transactions($tx_id = "") {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }

        $this->load->model("api/transactions_model", "transactions");

        $account    = $this->_account;

        $account_oauth_bridge_id = $account->account_oauth_bridge_id;

        $select = array(
            'transaction_id',
            'CONCAT("'. base_url() . "qr-code/transactions/" .'", transaction_sender_ref_id) as qr_code',
            'transaction_sender_ref_id',
            'transaction_amount',
            'transaction_fee',
            'transaction_type_name',
            'transaction_type_group_id',
            'transaction_status',
            'transaction_date_expiration',
            'transaction_date_created',
            'transaction_requested_by',
            'transaction_requested_to'
        );

        $query_1 = "(tx.transaction_requested_by = '{$account_oauth_bridge_id}')";
        $query_2 = "(tx.transaction_requested_to = '{$account_oauth_bridge_id}')";

        if (!empty($tx_id)) {
            $row = $this->transactions->get_datum(
                '',
                array(
                    'transaction_id' => $tx_id
                )
            )->row();

            if ($row != "") {
                $query_1 = "(tx.transaction_requested_by = '{$account_oauth_bridge_id}' and transaction_date_created <= '{$row->transaction_date_created}' and transaction_id != '$row->transaction_id')";
                $query_2 = "(tx.transaction_requested_to = '{$account_oauth_bridge_id}' and transaction_date_created <= '{$row->transaction_date_created}' and transaction_id != '$row->transaction_id')";
            }
        }

        $select = ARRtoSTR($select);

$sql = <<<SQL
SELECT $select FROM `transactions` as tx 
inner join transaction_types
on transaction_types.transaction_type_id = tx.transaction_type_id
where 
$query_1
or 
$query_2
ORDER BY transaction_date_created DESC
LIMIT $this->_limit
SQL;

        $query = $this->db->query($sql);
        $results = $query->result_array();

        $data = $this->filter_client_tx($results);

        $last_id = "";

$sql = <<<SQL
SELECT $select FROM `transactions` as tx 
inner join transaction_types
on transaction_types.transaction_type_id = tx.transaction_type_id
where 
$query_1
or 
$query_2
ORDER BY transaction_date_created ASC
LIMIT 1
SQL;

        $query = $this->db->query($sql);
        $results = $query->result_array();

        if (!empty($results)) {
            if (isset($results[0]['transaction_id'])) {
                $last_id  = $results[0]['transaction_id'];
            }
        }

        echo json_encode(
            array(
                'response' => array(
                    'last_id'   => $last_id,
                    'data'      => $data
                )
            )
        );
    }

    public function ledger($ledger_datum_id = "") {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }

        $this->load->model("api/ledger_data_model", "ledger");

        $account    = $this->_account;

        $account_oauth_bridge_id = $account->account_oauth_bridge_id;

        $where = array(
			'ledger_datum_bridge_id'	=> $account_oauth_bridge_id
		);

        if (!empty($ledger_datum_id)) {
            $row = $this->ledger->get_datum(
                '',
                array(
                    'ledger_datum_id' => $ledger_datum_id
                )
            )->row();

            if ($row != "") {
                $where = array_merge(
                    $where,
                    array(
                        'ledger_datum_date_added <='    => $row->ledger_datum_date_added,
                        'ledger_datum_id !='            => $row->ledger_datum_id
                    )
                );
            }
        }

        $select = array(
            'ledger_datum_id as id',
			'transaction_id',
            'transaction_sender_ref_id as "sender_ref_id"',
            'transaction_type_name as "transaction_type"',
			'transaction_type_code as "transaction_code"',
			'transaction_requested_by',
			'ledger_datum_old_balance as "old_balance"',
			'ledger_datum_amount as "debit_credit_amount"',
			'ledger_datum_new_balance as "new_balance"',
			'ledger_datum_date_added as "date_added"'
		);

		$inner_joints = array(
			array(
				'table_name'	=> 'transactions',
				'condition'		=> 'transactions.transaction_id = ledger_data.tx_id'
			),
			array(
				'table_name'	=> 'transaction_types',
				'condition'		=> 'transaction_types.transaction_type_id = transactions.transaction_type_id'
			)
		);

		$data = $this->ledger->get_data(
			$select,
			$where,
			array(),
			$inner_joints,
			array(
				'filter'	=> 'ledger_datum_date_added',
				'sort'		=> 'DESC'
            ),
            0,
            $this->_limit
        );

        $last_id = "";

        $data_last = $this->ledger->get_data(
			array(
                '*'
            ),
		    array(
                'ledger_datum_bridge_id'	=> $account_oauth_bridge_id
            ),
			array(),
			$inner_joints,
			array(
				'filter'	=> 'ledger_datum_date_added',
				'sort'		=> 'ASC'
            ),
            0, // offset
            1 // limit
        );
        
        if (isset($data_last[0]['ledger_datum_id'])) {
            $last_id = $data_last[0]['ledger_datum_id'];
        }

        $ledger_data = $this->filter_ledger($data);

        echo json_encode(
            array(
                'response' => array(
                    'last_id'   => $last_id,
                    'data'      => $ledger_data
                )
            )
        );
    }
}

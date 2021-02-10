<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Merchants extends Merchant_Controller {

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

    private function get_requested_transactions_merchant($requested = "by", $merchant_oauth_bridge_id, $tx_id = "") {
        $where = array(
            'merchants.oauth_bridge_id'     => $merchant_oauth_bridge_id
        );

        if (!empty($tx_id)) {
            $row = $this->transactions->get_datum(
                '',
                array(
                    'transaction_id' => $tx_id
                )
            )->row();

            if ($row != "") {
                $where = array_merge(
                    $where,
                    array(
                        'transaction_date_created <='   => $row->transaction_date_created,
                        'transaction_id !='             => $row->transaction_id
                    )
                );
            }
        }

        $inner_joints = array(
            array(
                'table_name'    => 'transaction_types',
                'condition'     => 'transaction_types.transaction_type_id = transactions.transaction_type_id'
            ),
            array(
                'table_name'    => 'merchant_accounts',
                'condition'     => 'merchant_accounts.oauth_bridge_id = transactions.transaction_requested_' . $requested,
                'position'      => 'left'
            ),
            array(
                'table_name'    => 'merchants',
                'condition'     => 'merchants.merchant_number = merchant_accounts.merchant_number'
            )
        );

        $select = array(
            'transaction_id',
            'CONCAT("'. base_url() . "qr-code/transactions/" .'", transaction_sender_ref_id) as qr_code',
            'transaction_sender_ref_id as "sender_ref_id"',
            'transaction_type_name as "transaction_type"',
            'transaction_type_code as "transaction_code"',
            'transaction_amount as "amount"',
            'transaction_fee as "fee"',
            'transaction_date_created as "date_added"',
            'IF(transaction_date_expiration > "'. $this->_today .'", "cancelled", IF(transaction_status = 1, "approved", "pending")) as transaction_status',
            'IF(transaction_otp_status = 0, "confirmed", "uncomfirmed") as otp_status'
        );
        
        $data = $this->transactions->get_data(
			$select,
			$where,
			array(),
			$inner_joints,
			array(
				'filter'	=> 'transaction_date_created',
				'sort'		=> 'DESC'
            ),
            0,
            50
        );

        return $data;
    }

    public function transactions($tx_id = "") {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }

        $account = $this->_account;
        $merchant_oauth_bridge_id = $account->merchant_oauth_bridge_id;
 
        $this->load->model("api/transactions_model", "transactions");

        $select = array(
            'transaction_id',
            'CONCAT("'. base_url() . "qr-code/transactions/" .'", transaction_sender_ref_id) as qr_code',
            'transaction_sender_ref_id',
            'transaction_amount',
            'transaction_fee',
            'transaction_type_name',
            'transaction_type_code',
            'transaction_type_group_id',
            'transaction_status',
            'transaction_date_expiration',
            'transaction_date_created',
            'tx.transaction_requested_by',
            'tx.transaction_requested_to',
            'tx.transaction_created_by'
        );

        $query_1 = "(m1.oauth_bridge_id = '{$merchant_oauth_bridge_id}')";
        $query_2 = "(m2.oauth_bridge_id = '{$merchant_oauth_bridge_id}')";

        if (!empty($tx_id)) {
            $row = $this->transactions->get_datum(
                '',
                array(
                    'transaction_id' => $tx_id
                )
            )->row();

            if ($row != "") {
                $query_1 = "(m1.oauth_bridge_id = '{$merchant_oauth_bridge_id}' and transaction_date_created <= '{$row->transaction_date_created}' and transaction_id != '$row->transaction_id')";
                $query_2 = "(m2.oauth_bridge_id = '{$merchant_oauth_bridge_id}' and transaction_date_created <= '{$row->transaction_date_created}' and transaction_id != '$row->transaction_id')";
            }
        }

        $order_by = "ORDER BY transaction_date_created DESC";
        $limit = "LIMIT $this->_limit";

        $select = ARRtoSTR($select);

$sql = <<<SQL
SELECT $select FROM `transactions` as tx 
inner join transaction_types
on transaction_types.transaction_type_id = tx.transaction_type_id
left join merchant_accounts as ma1 
on tx.transaction_requested_by = ma1.oauth_bridge_id
left join merchant_accounts as ma2
on tx.transaction_requested_to = ma2.oauth_bridge_id
left join merchants as m1 
on m1.merchant_number = ma1.merchant_number
left join merchants as m2 
on m2.merchant_number = ma2.merchant_number
where 
$query_1
or 
$query_2
$order_by
$limit
SQL;

        $query = $this->db->query($sql);
        $results = $query->result_array();

        $data = $this->filter_merchant_tx($results);

        $last_id = "";

$sql = <<<SQL
SELECT $select FROM `transactions` as tx 
inner join transaction_types
on transaction_types.transaction_type_id = tx.transaction_type_id
left join merchant_accounts as ma1 
on tx.transaction_requested_by = ma1.oauth_bridge_id
left join merchant_accounts as ma2
on tx.transaction_requested_to = ma2.oauth_bridge_id
left join merchants as m1 
on m1.merchant_number = ma1.merchant_number
left join merchants as m2 
on m2.merchant_number = ma2.merchant_number
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

        $merchant_oauth_bridge_id = $account->merchant_oauth_bridge_id;

        $where = array(
			'ledger_datum_bridge_id'	=> $merchant_oauth_bridge_id
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
                'ledger_datum_bridge_id'	=> $merchant_oauth_bridge_id
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

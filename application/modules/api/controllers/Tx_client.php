<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tx_client extends Client_Controller {

	public function after_init() {}
    
    public function transactions($tx_id = "") {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }

        $account = $this->_account;
        $client_oauth_bridge_id = $account->account_oauth_bridge_id;

        $this->load->model("api/transactions_model", "transactions");

        $where = array(
            'transactions.transaction_type_id !=' => "txtype_income_shares"
        );

        if ($tx_id != "") {
            $row = $this->transactions->_datum(
                array(
                    '*'
                ),
                array(),
                array(
                    'transaction_id' => $tx_id
                )
            )->row();

            if ($row != "") {
                $where = array_merge(
                    $where,
                    array(
                        'transaction_date_micro <' => $row->transaction_date_micro
                    )
                );
            }
        }

        $or_where = array(
            array(
                'field' => 'transaction_requested_by',
                'data'  => $client_oauth_bridge_id
            ),
            array(
                'field' => 'transaction_requested_to',
                'data'  => $client_oauth_bridge_id
            )
        );

        $or_where_in = array();

        $data = $this->transactions->_data(
            array(
                '*'
            ), // select
            array(
                array(
                    'table_name'    => "transaction_types",
                    'condition'     => "transaction_types.transaction_type_id = transactions.transaction_type_id"
                )
            ), // inner_joints
            $where, // where
            array(), //  where_in
            array(), // where_not_in
            $or_where, // or_where
            $or_where_in, // or_where_in
            array(), // or_where_not_in
            array(
                'filter_by' => "transaction_date_created, transaction_date_micro",
                'sort_by'   => "DESC"
            ), // order_by
            0,
            $this->_limit,
            true
        );

        $results = $this->filter_client_tx($data);

        $datum = $this->transactions->_datum(
            array(
                '*'
            ), // select
            array(
                array(
                    'table_name'    => "transaction_types",
                    'condition'     => "transaction_types.transaction_type_id = transactions.transaction_type_id"
                )
            ), // inner_joints
            $where, // where
            array(), //  where_in
            array(), // where_not_in
            $or_where, // or_where
            $or_where_in, // or_where_in
            array(), // or_where_not_in
            array(
                'filter_by' => "transaction_date_created, transaction_date_micro",
                'sort_by'   => "ASC"
            ), // order_by
            1 // limit
        )->row();

        $last_id = "";
        
        if ($datum != "") {
            $last_id = $datum->transaction_id;
        }

        echo json_encode(
            array(
                'response' => array(
                    'data'      => $results,
                    'last_id'   => $last_id
                )
            )
        );
    }

    public function get_tx_list($type = "merchant", $data) {

    }
}

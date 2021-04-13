<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tx_merchant extends Merchant_Controller {

	public function after_init() {}
    
    public function transactions($tx_id = "") {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
        }

        $account = $this->_account;
        $merchant_oauth_bridge_id = $account->merchant_oauth_bridge_id;

        $this->load->model("api/transactions_model", "transactions");
        $this->load->model("api/merchant_accounts_model", "merchant_accounts");

        $where = array();

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
                $where = array(
                    'transaction_date_micro <' => $row->transaction_date_micro
                );
            }
        }

        $m_accounts_data = $this->merchant_accounts->_data(
            array(
                'merchant_accounts.oauth_bridge_id as ma_oauth_bridge_id'
            ),
            array(
                array(
                    'table_name'    => 'merchants',
                    'condition'     => 'merchant_accounts.merchant_number = merchants.merchant_number'
                )
            ),
            array(
                'merchants.oauth_bridge_id' => $merchant_oauth_bridge_id
            )
        );

        $m_accounts = array();

        foreach($m_accounts_data as $datum) {
            $m_accounts[] = $datum['ma_oauth_bridge_id'];
        }

        $or_where = array(
            array(
                'field' => 'transaction_requested_by',
                'data'  => $merchant_oauth_bridge_id
            ),
            array(
                'field' => 'transaction_requested_to',
                'data'  => $merchant_oauth_bridge_id
            )
        );

        $or_where_in = array( 
            array(
                'field' => 'transaction_requested_by',
                'data'  => $m_accounts
            ),
            array(
                'field' => 'transaction_requested_to',
                'data'  => $m_accounts
            )
        );

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
                'filter_by' => "transaction_date_created",
                'sort_by'   => "DESC"
            ), // order_by
            0,
            $this->_limit,
            true
        );

        $results = $this->filter_merchant_tx($data);

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
                'filter_by' => "transaction_date_created",
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

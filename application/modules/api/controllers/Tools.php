<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tools extends Tms_admin_Controller {

	public function after_init() {
		if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function countries() {
		$this->load->model("api/countries_model", "countries");

		$results = $this->countries->get_data(
			array(
				'country_id',
				'country_name',
				'country_code'
			),
			array(
				'country_status' => 1
			),
			array(),
			array(),
			array(
				'filter'	=> "country_name",
				'sort'		=> "ASC",
			)
		);

		echo json_encode(
			array(
				'message'	=> "Successfully fetch countries!",
				'timestamp'	=> $this->_today,
				'response' 	=> $results
			)
		);
	}

	public function provinces($country_id) {
		$this->load->model("api/provinces_model", "provinces");

		$results = $this->provinces->get_data(
			array(
				'province_id',
				'province_name'
			),
			array(
				'country_id'		=> $country_id,
				'province_status' 	=> 1
			),
			array(),
			array(),
			array(
				'filter'	=> "province_name",
				'sort'		=> "ASC",
			)
		);

		echo json_encode(
			array(
				'message'	=> "Successfully fetch provinces!",
				'timestamp'	=> $this->_today,
				'response' 	=> $results
			)
		);
	}

	public function source_of_funds() {
		$this->load->model("api/source_of_funds_model", "sof");
		
		$results = $this->sof->get_data(
			array(
				'sof_id',
				'sof_name'
			),
			array(
				'sof_status' => 1
			),
			array(),
			array(),
			array(
				'filter'	=> "sof_id",
				'sort'		=> "ASC",
			)
		);

		echo json_encode(
			array(
				'message'	=> "Successfully fetch Souce of Funds!",
				'timestamp'	=> $this->_today,
				'response' 	=> $results
			)
		);
	}

	public function nature_of_work() {
		$this->load->model("api/nature_of_work_model", "now");
		
		$results = $this->now->get_data(
			array(
				'now_id',
				'now_name'
			),
			array(
				'now_status' => 1
			),
			array(),
			array(),
			array(
				'filter'	=> "now_id",
				'sort'		=> "ASC",
			)
		);

		echo json_encode(
			array(
				'message'	=> "Successfully fetch Nature of Work!",
				'timestamp'	=> $this->_today,
				'response' 	=> $results
			)
		);
	}

	public function id_types() {
		$this->load->model("api/id_types_model", "id_types");
		
		$results = $this->id_types->get_data(
			array(
				'id_type_id',
				'id_type_name'
			),
			array(
				'id_type_status' => 1
			),
			array(),
			array(),
			array(
				'filter'	=> "id_type_id",
				'sort'		=> "ASC",
			)
		);

		echo json_encode(
			array(
				'message'	=> "Successfully fetch ID types!",
				'timestamp'	=> $this->_today,
				'response' 	=> $results
			)
		);
	}
}

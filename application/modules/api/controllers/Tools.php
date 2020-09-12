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

		$countries = $this->countries->get_data(
			array(
				'country_id',
				'country_name',
				'country_mobile_code'
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
				'response' => $countries
			)
		);
	}

	public function provinces($country_id) {
		$this->load->model("api/provinces_model", "provinces");

		$provinces = $this->provinces->get_data(
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
				'response' => $provinces
			)
		);
	}
}

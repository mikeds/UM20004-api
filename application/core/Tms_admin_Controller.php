<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Tms_admin_Controller extends Api_Controller {
	protected
		$_account = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();

		$this->validate_token();
		$this->validate_access();
		$this->after_init();
	}

	private function validate_token() {
		$this->load->library("oauth2");
		$this->oauth2->get_resource();
	}

	private function validate_access() {
		$this->load->model("api/oauth_bridges_model", "bridges");

		$token_row = $this->get_token();
		$client_id = $token_row->client_id;

		$inner_joints = array(
			array(
				'table_name' 	=> 'tms_admins',
				'condition'		=> 'tms_admins.oauth_bridge_id = oauth_bridges.oauth_bridge_id'
			)
		);

		$row = $this->bridges->get_datum(
			'',
			array(
				'tms_admins.oauth_bridge_id' => $client_id
			),
			array(),
			$inner_joints
		)->row();
		
		if ($row == "") {
			generate_error_message("E003-2");
		}

		$this->_account = $row;
	}
}

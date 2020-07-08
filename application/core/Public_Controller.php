<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Public_Controller extends MX_Controller {
	protected
		$_today = "";

	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();
		$this->init();
	}

	private function init() {
		date_default_timezone_set( "Asia/Manila" );
		$this->_today = date("Y-m-d H:i:s");
		$this->after_init();
	}

	public function after_init() {}
}
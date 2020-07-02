<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Api_Controller extends MX_Controller {
	protected
		$_today = "";

	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();
		$this->init();
	}

	private function init() {
		$this->_today = date("Y-m-d H:i:s");
		$this->after_init();
	}
}
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Qr_code extends Public_Controller {
		
	public function after_init() {}

	public function client_accounts($id) {
        $this->load->model("api/client_accounts_model", "accounts");

        $row = $this->accounts->get_datum(
            '',
            array(
                'MD5(oauth_bridge_id)'  => $id
            )
        )->row();

        if ($row == "") {
            // unauthorized access
		    $this->output->set_status_header(401);
            return;
        }
    
        $this->load->library('ciqrcode');
        header("Content-Type: image/png");
        $params['data'] = $id;
        $params['size'] = 6;
        $config['cacheable']	= false; //boolean, the default is true
        $config['cachedir']		= ''; //string, the default is application/cache/
        $config['errorlog']		= ''; //string, the default is application/logs/
        $this->ciqrcode->generate($params);
    }

	public function merchant_accounts($id) {
        $this->load->model("api/merchant_accounts_model", "accounts");

        $row = $this->accounts->get_datum(
            '',
            array(
                'MD5(oauth_bridge_id)'  => $id
            )
        )->row();

        if ($row == "") {
            // unauthorized access
		    $this->output->set_status_header(401);
            return;
        }
    
        $this->load->library('ciqrcode');
        header("Content-Type: image/png");
        $params['data'] = $id;
        $params['size'] = 6;
        $config['cacheable']	= false; //boolean, the default is true
        $config['cachedir']		= ''; //string, the default is application/cache/
        $config['errorlog']		= ''; //string, the default is application/logs/
        $this->ciqrcode->generate($params);
    }

    public function transactions($id) {
        $this->load->library('ciqrcode');
        header("Content-Type: image/png");
        $params['data'] = $id;
        $params['size'] = 6;
        $config['cacheable']	= false; //boolean, the default is true
        $config['cachedir']		= ''; //string, the default is application/cache/
        $config['errorlog']		= ''; //string, the default is application/logs/
        $this->ciqrcode->generate($params);
    }
}





















<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sendmail extends Public_Controller {

	public function after_init() {}
    
    public function index() {
        $email_address = isset($_GET['email_address']) ? $_GET['email_address'] : "";

        if ($email_address == "") {
            echo "Email Address is required!";
            die();
        }

        $email_from = getenv("SMTPUSER", true);

        $this->load->library('email'); // load the library 
        
        $this->email->clear();
        $this->email->from($email_from);
        $this->email->to($email_address);
        $this->email->subject("Test mail");
        $this->email->message("Test mail message!");
        
        if($this->email->send()) {
            echo "Success";
        } else {
            echo $this->email->print_debugger();
        }
    }
}

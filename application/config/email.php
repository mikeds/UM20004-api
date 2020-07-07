<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config = Array(
    'protocol'  => 'smtp',
    'smtp_crypto'   => 'ssl',
    'smtp_host' => getenv("SMTPHOST"),
    'smtp_port' => 465,
    'smtp_user' => getenv("SMTPUSER"),
    'smtp_pass' => getenv("SMTPPASS"),
    'wordwrap'  => true,
    'mailtype'  => 'html', 
    'charset'   => 'iso-8859-1'
);

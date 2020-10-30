<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Sms extends Public_Controller {
	public function after_init() {}

    private
        $_shortcode          = "21587007",
        $_address            = "9294713423",
        $_clientCorrelator   = "225657007";

    private
        $_access_token       = "1qCeodZDA-RjLz4JUFAY8vOaki2MCk81jeCouS3rvl4",
        $_passphrase         = "",
        $_app_id             = "AMM8H69MMeCb5Tp4nBiMnGC8kM7MHMba",
        $_app_secret         = "53ecfe76327a3b27fb787f92251edf4b23ad667afa8be63516e5127ee7aba3d4";

    public function send() {
        $access_token       = $this->_access_token;

        $shortcode          = $this->_shortcode;
        $address            = $this->_address;
        $clientCorrelator   = $this->_clientCorrelator;

        $message            = "PHP SMS Test - Token";

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://devapi.globelabs.com.ph/smsmessaging/v1/outbound/".$shortcode."/requests?access_token=".$access_token ,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"outboundSMSMessageRequest\": { \"clientCorrelator\": \"".$clientCorrelator."\", \"senderAddress\": \"".$shortcode."\", \"outboundSMSTextMessage\": {\"message\": \"".$message."\"}, \"address\": \"".$address."\" } }",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));
    
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }
    }

    public function send_bypass() {
        $message            = "BambuPAY TEST - Bypass";

        $passphrase         = $this->_passphrase; // unknown source
        $app_id             = $this->_app_id;
        $app_secret         = $this->_app_secret;

        $shortcode          = $this->_shortcode;
        $address            = $this->_address;
        $clientCorrelator   = $this->_clientCorrelator;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://devapi.globelabs.com.ph/smsmessaging/v1/outbound/".$shortcode."/requests?app_id=".$app_id."&app_secret=".$app_secret."&passphrase=".$passphrase ,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }
    }

    public function notification() {
        
    }
}


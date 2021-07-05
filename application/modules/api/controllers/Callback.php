<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Callback extends Api_Controller {

	public function after_init() {
		if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$this->output->set_status_header(401);
			die();
		}
	}

	public function ubp_code() {
		if ($_GET) {
			if (isset($_GET['code'])) {
				$code = $_GET['code'];

				echo json_encode(
					array(
						'response' => array(
							'code' => $code
						)
					)
				);
				return;
			}
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
	public function globelabs() {
		$this->load->model("api/globe_access_tokens", "globe_access_token");
		$this->load->model("api/client_accounts_model", "client_accounts");
		$this->load->model('last_inserted_user_model', 'last_inserted_user');	

		if ($_GET) {
			$last_inserted_user = $this->last_inserted_user->get_datum();
			$count = $last_inserted_user->num_rows();

			if($count > 0){
				$row = $last_inserted_user->row();
				if (!isset($_GET['code'])) {
					echo json_encode(
						array(
							'error'             => true,
							'error_description' => "Please provide code from globeapi callback."
						)
					);
					die();
				}
	
				$base_url 	= GLOBEBASEURL . "oauth/access_token";
	
				$code  		= $_GET['code'];
				$app_id     = GLOBEAPPID;
				$app_secret = GLOBEAPPSECRET;
				
				$auth_url	= $base_url . "?app_id={$app_id}&app_secret={$app_secret}&code={$code}";
	
				$curl = curl_init();
	
				curl_setopt_array($curl, array(
					CURLOPT_URL => $auth_url,
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
					echo json_encode(
						array(
							'error'             => true,
							'error_description' => "Unable to generate token. Curl Error #: {$err}",
							'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
						)
					);
					die();
				}
	
				$decoded = json_decode($response);
	
				if (!isset($decoded->access_token)) {
					echo json_encode(
						array(
							'error'             => true,
							'error_description' => "Invalid code.",
							'redirect_url'		=> GLOBEBASEURL . "dialog/oauth/" . GLOBEAPPID
						)
					);
					die();
				}
	
				$access_token 	= $decoded->access_token;
				$mobile_no		= $decoded->subscriber_number;
	
				$row_token = $this->globe_access_token->get_datum(
					'',
					array(
						'token_mobile_no'	=> $mobile_no,
					)
				)->row();
	
				if ($row_token == "") {
					$this->globe_access_token->insert(
						array(
							'token_code'			=> $access_token,
							'token_mobile_no'		=> $mobile_no,
							'token_date_added'		=> $this->_today
						)
					);
				} else {
					$this->globe_access_token->update(
						$row_token->token_id,
						array(
							'token_code'			=> $access_token,
							'token_date_added'		=> $this->_today
						)
					);
				}

				$user_type 		= $row->user_type;
				$last_id 		= $row->iu_id;
				$user_phone_no 	= $row->user_phone_no; 

				if($row->user_phone_no == $mobile_no){
					if($user_type == 1){ // merchant
						$this->last_inserted_user->delete($last_id);
						redirect('http://bpay-merchant.bambupay.local/otp-validation/'.$mobile_no);
					}
					if($user_type == 2){ // client
						$this->last_inserted_user->delete($last_id);
						redirect('http://bpay-client.bambupay.local/otp-validation/'.$mobile_no);
					}
					if($user_type == 3){ // agent
						$this->last_inserted_user->delete($last_id);
						redirect('http://bpay-agent.bambupay.local/otp-validation/'.$mobile_no);
					}
					
				}else{

					if (isset($_GET['code'])) {
						$code = $_GET['code'];
						
		
						echo json_encode(
							array(
								'response' => array(
									'code' => $code
								)
							)
						);
						return;
					}
		
					die();
					
				}
		
			}



			if (isset($_GET['code'])) {
				$code = $_GET['code'];
				

				echo json_encode(
					array(
						'response' => array(
							'code' => $code
						)
					)
				);
				return;
			}

			die();
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}

	public function paynamics_response() {
		if (!isset($_GET['success'])) {
			echo json_encode(
				array(
					'message' => 'Cannot find status parameter.',
					'response' => array(
						'timestamp' => $this->_today
					)
				)
			);

			die();
		}

		$status = $_GET['success'];

		$message = "Successfully done transaction on our payment gateway.";

		if ($status == 'false') { 
			$message = "Payment gateway is cancelled, Invalid transaction.";
		}

		echo json_encode(
			array(
				'message' => $message,
				'response' => array(
					'timestamp' => $this->_today
				)
			)
		);
	}

	public function paynamics_cancel() {
		echo json_encode(
			array(
				'error'		=> true,
				'message' 	=> 'Payment gateway is cancelled, Invalid transaction.',
				'response' 	=> array(
					'timestamp' => $this->_today
				)
			)
		);
	}

	public function paynamics_notification() {
		echo json_encode(
			array(
				'message' 	=> 'Payment gateway notification callback.',
				'response' 	=> array(
					'timestamp' => $this->_today
				)
			)
		);
	}
}

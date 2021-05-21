<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Agent_registration extends Tms_admin_Controller {

	public function after_init() {}

	public function submit() {
		$admin_oauth_bridge_id = $this->_account->oauth_bridge_id;

		$this->load->model("api/agent_pre_registration_model", "agent_pre_registration");
        $this->load->model("api/client_pre_registration_model", "client_pre_registration");
		$this->load->model("api/merchants_model", "merchants");
        $this->load->model("api/client_accounts_model", "client_accounts");
        $this->load->model("api/otp_model", "otp");

		if ($_POST) {
            // Personal Information
            $fname			= $this->input->post("first_name");
            $fname			= is_null($fname) ? "" : $fname;
            
            $mname			= $this->input->post("middle_name");
            $mname			= is_null($mname) ? "" : $mname;

            $lname			= $this->input->post("last_name");
            $lname			= is_null($lname) ? "" : $lname;

            // Login Information
            $email_address	= $this->input->post("email_address");
            $email_address	= is_null($email_address) ? "" : $email_address;
                
            $password		= $this->input->post("password");
            $password		= is_null($password) ? "" : $password;
            
            // Number Information
            $mobile_no		= $this->input->post("mobile_no");
            $mobile_no		= is_null($mobile_no) ? "" : $mobile_no;

            $dob			= $this->input->post("dob");
            $dob			= is_null($dob) ? "" : $dob;

            $pob			= $this->input->post("pob");
            $pob			= is_null($pob) ? "" : $pob;
            
            $gender			= $this->input->post("gender");
            $gender			= is_null($gender) ? "" : $gender;

            // Address Information
            $house_no		= $this->input->post("house_no");
            $house_no		= is_null($house_no) ? "" : $house_no;
            
            $street			= $this->input->post("street");
            $street			= is_null($street) ? "" : $street;

            $brgy			= $this->input->post("brgy");
            $brgy			= is_null($brgy) ? "" : $brgy;

            $city			= $this->input->post("city");
            $city			= is_null($city) ? "" : $city;

            $province_id	= $this->input->post("province_id");
            $province_id	= is_null($province_id) ? 0 : $province_id;

            $country_id		= $this->input->post("country_id");
            $country_id		= is_null($country_id) ? "" : $country_id;

            $postal_code	= $this->input->post("postal_code");
            $postal_code	= is_null($postal_code) ? 0 : $postal_code;
            
            // Work Information
            $sof            = $this->input->post("source_of_funds");
            $sof	        = is_null($sof) ? 0 : $sof;

            $now            = $this->input->post("nature_of_work");
            $now	        = is_null($now) ? 0 : $now;



            // Indentification
            $id_type        = $this->input->post("id_type");
            $id_type	    = is_null($id_type) ? 0 : $id_type;

            $id_no          = $this->input->post("id_no");
            $id_no	        = is_null($id_no) ? 0 : $id_no;

            $id_exp_date    = $this->input->post("id_expiration_date");
            $id_exp_date	= is_null($id_exp_date) ? 0 : $id_exp_date;

			if ($fname == "" || $lname == "") {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "First Name and Last Name are required."
					)
				);
				die();
			}

            if ($email_address == "") {
				echo json_encode(
					array(
						'error'             => true,
						'error_description' => "Email Address is required."
					)
				);
				die();
			}

                // $m_row_email_validation = $this->merchants->get_datum(
                //     '',
                //     array(
                //         'merchant_email_address' => $email_address
                //     )
                // )->row();

                // if ($m_row_email_validation != "") {
                //     echo json_encode(
                //         array(
                //             'error'             => true,
                //             'error_description' => "Email Address is already used."
                //         )
                //     );
                //     die();
                // }

            if ($password == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Password is required."
                    )
                );
                die();
            }

            if ($mobile_no == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Mobile No. is required."
                    )
                );
                die();
            }

			
                $m_row_mobile_validation = $this->merchants->get_datum(
                    '',
                    array(
                        'merchant_mobile_no' => $mobile_no
                    )
                )->row();

                if ($m_row_mobile_validation != "") {
                    echo json_encode(
                        array(
                            'error'             => true,
                            'error_description' => "Mobile no. is already used."
                        )
                    );
                    die();
                }
			
            if ($dob == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Date of Birth is required."
                    )
                );
                die();
            }

            if ($pob == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Place of Birth is required."
                    )
                );
                die();
            }

            if ($gender == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Gender is required."
                    )
                );
                die();
            }

            if ($house_no == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "House No. is required."
                    )
                );
                die();
            }

            if ($street == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Street is required."
                    )
                );
                die();
            }

            if ($brgy == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Barangay is required."
                    )
                );
                die();
            }

            if ($city == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "City is required."
                    )
                );
                die();
            }

            if ($province_id == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Province is required."
                    )
                );
                die();
            }

            if ($country_id == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Country is required."
                    )
                );
                die();
            }

            if ($postal_code == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Postal Code is required."
                    )
                );
                die();
            }

            if ($sof == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Source of Funds is required."
                    )
                );
                die();
            }

            if ($now == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Nature of Work is required."
                    )
                );
                die();
            }


            if ($id_type == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "ID Type is required."
                    )
                );
                die();
            }

            if ($id_no == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "ID No. is required."
                    )
                );
                die();
            }

            if ($id_exp_date == "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "ID Expiration Date is required."
                    )
                );
                die();
            }

			$account_number = $this->generate_code(
				array(
					"agent_pre_registration",
					$admin_oauth_bridge_id,
					$this->_today
				),
				"crc32"
			);

			$code = generate_code(4);
			$code = strtolower($code);

			$otp_number = $this->generate_code(
				array(
					"otp_client_registration",
					$code,
					$this->_today
				),
				"crc32"
			);

			$expiration_time 	= 3;
			$expiration_date 	= create_expiration_datetime($this->_today, $expiration_time);

			$insert_data = array(
				'account_otp_number'		=> $otp_number,
				'account_number'			=> $account_number,
				'account_fname'				=> $fname,
				'account_mname'				=> $mname,
				'account_lname'				=> $lname,
				'account_email_address'		=> $email_address,
                'account_password'			=> $password,
                'account_mobile_no'			=> $mobile_no,
				'account_dob'				=> $dob,
                'account_pob'				=> $pob,
				'account_gender'			=> $gender,
				'account_house_no'			=> $house_no,
				'account_street'			=> $street, 
				'account_brgy'				=> $brgy,
				'account_city'				=> $city,
				'province_id'				=> $province_id,
				'country_id'				=> $country_id,
				'account_postal_code'	    => $postal_code,
                'sof_id'                    => $sof,       
                'now_id'                    => $now,
                'account_id_type'           => $id_type,
                'account_id_no'             => $id_no,
                'account_id_exp_date'       => $id_exp_date,
				'account_date_added'		=> $this->_today,
			);

            $id_flag = false;

			if ($_FILES) {
                $id_front_base64    = "";
                $id_back_base64     = "";

                if (isset($_FILES['id_front'])) {
					$image = $_FILES['id_front'];

					$upload_avatar_results = $this->upload_files(
						$account_number,
						$image,
						"id_front",
						true,
						5,
						"jpg|jpeg|JPG|JPEG|PNG|png|bmp"
					);

					if (isset($upload_avatar_results['results'])) {
						$upload_results = $upload_avatar_results['results'];

						if (isset($upload_avatar_results['results'])) {
							$upload_results = $upload_avatar_results['results'];
		
							if (isset($upload_results['is_data'])) {
								if ($upload_results['is_data']) {
									// get base64 image
									if (isset($upload_results['data'][0])) {
										$first_data = $upload_results['data'][0];
										$base64_image = $first_data['base64_image'];
		
										$id_front_base64 = $base64_image;
									}
								}
							}
						}
					}
				}

                if (isset($_FILES['id_back'])) {
					$image = $_FILES['id_back'];

					$upload_avatar_results = $this->upload_files(
						$account_number,
						$image,
						"id_back",
						true,
						5,
						"jpg|jpeg|JPG|JPEG|PNG|png|bmp"
					);

					if (isset($upload_avatar_results['results'])) {
						$upload_results = $upload_avatar_results['results'];

						if (isset($upload_avatar_results['results'])) {
							$upload_results = $upload_avatar_results['results'];
		
							if (isset($upload_results['is_data'])) {
								if ($upload_results['is_data']) {
									// get base64 image
									if (isset($upload_results['data'][0])) {
										$first_data = $upload_results['data'][0];
										$base64_image = $first_data['base64_image'];
		
										$id_back_base64 = $base64_image;
									}
								}
							}
						}
					}
				}

                if ($id_front_base64 != "" && $id_back_base64 != "") {
                    $insert_data = array_merge(
                        $insert_data,
                        array(
                            'account_id_front_base64'   => $id_front_base64,
                            'account_id_back_base64'    => $id_back_base64
                        )
                    );

                    $id_flag = true;
                }

                if ($id_flag) {
                    if (isset($_FILES['profile_picture'])) {
                        $avatar_image = $_FILES['profile_picture'];
    
                        $upload_avatar_results = $this->upload_files(
                            $account_number,
                            $avatar_image,
                            "profile_picture",
                            true,
                            5,
                            "jpg|jpeg|JPG|JPEG|PNG|png|bmp"
                        );
    
                        if (isset($upload_avatar_results['results'])) {
                            $upload_results = $upload_avatar_results['results'];
    
                            if (isset($upload_avatar_results['results'])) {
                                $upload_results = $upload_avatar_results['results'];
            
                                if (isset($upload_results['is_data'])) {
                                    if ($upload_results['is_data']) {
                                        // get base64 image
                                        if (isset($upload_results['data'][0])) {
                                            $first_data = $upload_results['data'][0];
                                            $base64_image = $first_data['base64_image'];
            
                                            $insert_data = array_merge(
                                                $insert_data,
                                                array(
                                                    'account_avatar_base64' => $base64_image
                                                )
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
			}

            if (!$id_flag) {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "ID front and back copy are required."
                    )
                );
                die();
            }


            // check if number is exist and mobile is exist from agent_pre_registration then delete
            $row_mobile = $this->agent_pre_registration->get_datum(
                '',
                array(
                    'account_mobile_no' => $mobile_no
                )
            )->row();
            
            
            if ($row_mobile != "") {
                $this->agent_pre_registration->delete($row_mobile->account_number);
            }

            $row_email = $this->agent_pre_registration->get_datum(
                '',
                array(
                    'account_email_address' => $email_address
                )
            )->row();
            
            if ($row_email != "") {
                $this->agent_pre_registration->delete($row_email->account_number);
            }

            // check if number is exist and mobile is exist from client_pre_registration then delete
            $client_row_mobile = $this->client_pre_registration->get_datum(
                '',
                array(
                    'account_mobile_no' => $mobile_no
                )
            )->row();
            
            if ($client_row_mobile != "") {
                $this->client_pre_registration->delete($client_row_mobile->account_number);
            }

            $client_row_email = $this->client_pre_registration->get_datum(
                '',
                array(
                    'account_email_address' => $email_address
                )
            )->row();
            
            if ($client_row_email != "") {
                $this->client_pre_registration->delete($client_row_email->account_number);
            }

            // check if number is exist and mobile is exist from merchant/agent accounts - prevent from data inserting
            $accounts_row_mobile = $this->client_accounts->get_datum(
                '',
                array(
                    'account_mobile_no' => $mobile_no
                )
            )->row();
            
            if ($accounts_row_mobile != "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Mobile phone number already used!"
                    )
                );
                die();
            }

            $accounts_row_email = $this->client_accounts->get_datum(
                '',
                array(
                    'account_email_address' => $email_address
                )
            )->row();

            if ($accounts_row_email != "") {
                echo json_encode(
                    array(
                        'error'             => true,
                        'error_description' => "Email address already used!"
                    )
                );
                die();
            }

       

			$this->otp->insert(
				array(
					'otp_number'			=> $otp_number,
					'otp_code'				=> $code,
					'otp_mobile_no'			=> $mobile_no,
					'otp_status'			=> 0,
					// 'otp_date_expiration'	=> $expiration_date,
					'otp_date_created'		=> $this->_today
				)
			);

            $this->agent_pre_registration->insert(
                $insert_data
            );

			echo json_encode(
				array(
					'message'   => 'Successfully Pre-registration!',
                    'response'  => array(
                        'timestamp' => $this->_today
                    )
				)
			);

			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);
	}
}
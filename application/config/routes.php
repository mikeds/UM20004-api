<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/

# for testing

$route["test/reset-mobile-no"]      = "api/Test/reset_mobile_no";

// $route["sendmail"]                  = "public/Sendmail"; // for testing only

# prod

$route["default_controller"]        = "api/Home";
$route["token"]                     = "api/Token";
$route["token/cancel"]              = "api/Token/cancel_token";

$route["clients/login"]             = "api/Login/client";
$route["merchants/login"]           = "api/Login/merchant";

$route["clients/registration"]      = "api/Client_registration/submit";
// $route["merchants/registration"]    = "api/Registration/merchant";

$route["tools/countries"]           = "api/Tools/countries";
$route["tools/provinces/(:num)"]    = "api/Tools/provinces/$1";

$route["callback/ubp/code"]         = "api/Callback/ubp_code";

$route["activation/merchant-email/resend"]      = "api/Activation/merchant_email_resend";
$route["activation/merchant-email/activate"]    = "api/Activation/merchant_email_activation";

$route["activation/client-email/resend"]      = "api/Activation/client_email_resend";
$route["activation/client-email/activate"]    = "api/Activation/client_email_activation";

$route["avatar/merchant-accounts/(:any)"]   = "public/Avatar/merchant_accounts/$1";
$route["qr-code/merchant-accounts/(:any)"]  = "public/Qr_code/merchant_accounts/$1";

$route["avatar/client-accounts/(:any)"]   = "public/Avatar/client_accounts/$1";
$route["qr-code/client-accounts/(:any)"]  = "public/Qr_code/client_accounts/$1";

$route["qr-code/transactions/(:any)"]     = "public/Qr_code/transactions/$1";

$route["transactions/merchant/top-up"]  = "api/Top_up";
$route["transactions/client/send-to"]   = "api/Send_to/direct";

$route["transactions/client/cash-in"]   = "api/Cash_in";
$route["transactions/client/cash-in-test"]   = "api/Cash_in/paynamics_test";

$route["transactions/merchant/accept-cash-in"]   = "api/Merchant_accept/cash_in";

$route["clients/balance"]       = "api/Clients/balance";
$route["merchants/balance"]     = "api/Merchants/balance";

$route["clients/ledger"]       = "api/Clients/ledger";
$route["merchants/ledger"]     = "api/Merchants/ledger";

$route["clients/ledger/(:any)"]     = "api/Clients/ledger/$1";
$route["merchants/ledger/(:any)"]   = "api/Merchants/ledger/$1";

$route["clients/transactions"]     = "api/Clients/transactions";
$route["merchants/transactions"]   = "api/Merchants/transactions";

$route["clients/transactions/(:any)"]     = "api/Clients/transactions/$1";
$route["merchants/transactions/(:any)"]   = "api/Merchants/transactions/$1";

$route["transactions/merchant/scanpayqr/create"]   = "api/Scanpayqr_merchant/create";
$route["transactions/client/scanpayqr/accept"]     = "api/Scanpayqr_client/accept";

$route["transactions/client/createpayqr/create"]     = "api/Createpayqr_client/create";
$route["transactions/merchant/createpayqr/accept"]   = "api/Createpayqr_merchant/accept";

$route["transactions/client/quickpayqr/scan/(:any)"]    = "api/Quickpayqr_client/scan/$1";
$route["transactions/client/quickpayqr/accept"]         = "api/Quickpayqr_client/accept";

$route["callback/globelabs"]    = "api/Callback/globelabs";

$route["test/income-shares"]    = "api/Home/test_income_shares";

$route["lookup/tx-list"]        = "api/Lookup/tx_list";
$route["lookup/tx-fee"]         = "api/Lookup/tx_fee";

$route["lookup/merchant/tx/(:any)"]     = "api/Lookup_merchant/tx/$1";
$route["lookup/client/tx/(:any)"]       = "api/Lookup_client/tx/$1";

$route["lookup/client/tx-list"]        = "api/Lookup_client/tx_list";
$route["lookup/client/tx-fee"]         = "api/Lookup_client/tx_fee";

$route["lookup/client/ubp/banks"] = "api/Lookup_client/instapay_banks";

$route["paynamics/auth"] = "public/Paynamics/auth";
$route["paynamics/sale"] = "public/Paynamics/sale";

$route["transactions/client/cash-out"] = "api/Cash_out_client";

$route["callback/paynamics/notification"]   = "api/Callback/paynamics_notification";
$route["callback/paynamics/response"]       = "api/Callback/paynamics_response";
$route["callback/paynamics/cancel"]         = "api/Callback/paynamics_cancel";

$route["cron/paynamics"]    = "public/Cron/paynamics";

# OTP SMS
$route["otp/token"]      = "api/Otp_sms/token";
$route["otp/request"]    = "api/Otp_sms/request";
$route["otp/submit"]     = "api/Otp_sms/submit";

# OTP UBP
$route["ubp/token"]      = "api/Token/ubp";

/*
// for testing
$route["token/sms"]             = "api/Token/sms";
$route["otp/client/request"]    = "api/Client_otp/request";
$route["otp/client/submit"]     = "api/Client_otp/submit";

$route["sms/send"]              = "public/Sms/send";
$route["sms/notification"]      = "public/Sms/notification";

$route["uploads"]               = "api/Registration/uploads";
*/

$route['404_override'] = 'api/Error_404';
$route['translate_uri_dashes'] = FALSE;


























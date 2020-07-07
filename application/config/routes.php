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

$route["default_controller"]        = "api/Home";

$route["token"]                     = "api/Token";

$route["device/auth"]               = "api/Device/auth";
$route["device/registration"]       = "api/Device/registration";

$route["merchant/login"]            = "api/Merchant/Login";
$route["merchant/registration"]     = "api/Merchant/registration";

$route["client/login"]              = "api/Client/Login";
$route["client/registration"]       = "api/Client/registration";

$route["client/balance"]            = "api/Client_transaction/balance";

$route["client/cash-in"]            = "api/Client_transaction/cash_in";
$route["client/cash-out"]           = "api/Client_transaction/cash_out";
$route["client/send-to"]            = "api/Client_transaction/send_to";

$route['404_override'] = 'api/Error_404';
$route['translate_uri_dashes'] = FALSE;


























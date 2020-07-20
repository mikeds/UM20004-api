<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

function get_transaction_status($status_id) {
	$status = "";

	if ($status_id == 0) {
		$status = "pending";
	} else if ($status_id == 1) {
		$status = "approved";
	} else if ($status_id == 2) {
		$status = "cancelled";
	}

	return $status;
}

function get_transaction_type($type_id) {
	$type = "";

	if ($type_id == 1) {
		$type = "cash_in";
	} else if ($type_id == 2) {
		$type = "cash_out";
	} else if ($type_id == 3) {
		$type = "transfer";
	}

	return $type;
}

function valid_256_hash($hash) {
	if (preg_match("/^([a-f0-9]{64})$/", $hash) == 1) {
		return true;
	} else {
		return false;
	}
}
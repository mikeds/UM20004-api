<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

function qr_image_data($str) {
	$image_url = base_url() . "transaction/qr-code/". $str;

	// Read image path, convert to base64 encoding
	$imageData = base64_encode(file_get_contents($image_url));

	return $imageData;
}
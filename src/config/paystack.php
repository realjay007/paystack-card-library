<?php

/**
 * paystack.php
 *
 * Config file containing endpoints and authentication for use with paystack calls
 * @author Julius Ijie
 */

$config = array();

// Secret key used for authentication
// $config['secret_key'] = 'sk_test_ae491bbb23c3a63a7c3e22effcac206e5e8eedab';

// Paystack base uri
$config['base_uri'] = 'https://api.paystack.co';

// Endpoint for adding card
$config['add_card'] = '/charge/tokenize';

// Delete card
$config['delete_card'] = '/customer/deactivate_authorization';

// Debit card
$config['debit_card'] = '/charge';

// Submit OTP
$config['submit_otp'] = '/charge/submit_otp';

// Submit Phone
$config['submit_phone'] = '/charge/submit_phone';

// Check transaction status
$config['tranx_status'] = function(string $ref) {
	return '/charge/'.$ref;
};

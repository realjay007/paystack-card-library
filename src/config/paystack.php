<?php

/**
 * paystack.php
 *
 * Config file containing endpoints and authentication for use with paystack calls
 * @author Julius Ijie
 */

$config = array();

// Paystack base uri
$config['base_uri'] = 'https://api.paystack.co';

// Endpoint for adding card
$config['add_card'] = '/charge/tokenize';

// Delete card
$config['delete_card'] = '/customer/deactivate_authorization';

// Debit card
$config['debit_card'] = '/charge';

// Debit reusable cards
$config['debit_reusable_card'] = '/transaction/charge_authorization';

// Submit OTP
$config['submit_otp'] = '/charge/submit_otp';

// Submit Phone
$config['submit_phone'] = '/charge/submit_phone';

// Submit PIN
$config['submit_pin'] = '/charge/submit_pin';

// Check transaction status
$config['tranx_status'] = function(string $ref): string {
	return '/charge/'.$ref;
};

// Verify transaction
$config['verify_tranx'] = function(string $ref): string {
	return '/transaction/verify/'.$ref;
};

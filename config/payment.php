<?php
	
	return [
		'min_deposit' => env('PAYMENT_MIN_DEPOSIT', 0),
		'api_url' => env('PAYMENT_API_URL', 'https://example.com'),
		'partner_id' => env('PAYMENT_PARTNER_ID', null),
		'secret_key' => env('PAYMENT_SECRET_KEY', null),
		'client_id' => env('PAYMENT_CLIENT_ID', null),
		'merchant_no' => env('PAYMENT_MERCHANT_NO', null),
		'api_key' => env('PAYMENT_API_KEY', null),
		'channel_type' => env('PAYMENT_CHANNEL_TYPE', null),
		'deposit_range' => [
			50, 100, 200, 300, 500, 600, 700, 1000
		],
	];

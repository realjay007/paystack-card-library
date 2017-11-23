<?php
namespace PAY;

// use GuzzleHttp\Exception\{ClientException, ServerException, RequestException};
// use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Client;

/**
 * Card_Gate.php
 *
 * Library/Sub-Controller for card operations
 * @author Julius Ijie
 */

class Card_Gate {

	/**
	 * Configuration handler
	 * @var Config
	 */
	protected $config;

	/**
	 * Guzzle client library for making requests
	 * @var Client
	 */
	protected $client;

	/**
	 * Class constructor
	 * Sets up mongo collection and http client
	 * @param \MongoDB\Collection $col Card collection handler
	 * @param string $key Paystack secret key
	 */
	public function __construct(\MongoDB\Collection $col, string $key) {
		// Prepare config
		$this->config = new Config;
		$this->config->card_collection = $col;
		$this->config->loadConfig('paystack');
		// Prepare client
		$this->client = new \GuzzleHttp\Client(array(
			'base_uri' => $this->config->paystack['base_uri'],
			'headers' => array(
				'Authorization' => 'Bearer '.$key
			),
			'http_errors' => false
		));
	}

	/**
	 * Tokenise card and add to db
	 * @param string $email Card owner's email, used as user id
	 * @param string $card_number
	 * @param string $cvv
	 * @param string $exp_month
	 * @param string exp_year
	 * @return Card
	 */
	public function addCard(string $email, string $card_number, string $cvv, string $exp_month, string $exp_year): Card {
		$paystack = $this->config->paystack;

		// Tokenise the card
		$params = array(
			'email' => $email,
			'card' => array(
				'number' => $card_number,
				'cvv' => $cvv,
				'expiry_month' => $exp_month,
				'expiry_year' => $exp_year
			)
		);

		$response = $this->client->post($paystack['add_card'], array('json' => $params));

		$result = json_decode($response->getBody());

		if($result->status === false) return $status;

		// Create a new card object
		$data = $result->data;
		$card = array(
			'email' => $email,
			'authorization_code' => $data->authorization_code,
			'card_type' => $data->card_type,
			'first_six' => $data->bin,
			'last_four' => $data->last4,
			'hashed_card_number' => sha1($card_number),
			'exp_month' => $data->exp_month,
			'exp_year' => $data->exp_year,
			'bank' => $data->bank,
			'signature' => $data->signature,
			'reusable' => $data->reusable,
			'country_code' => $data->country_code
		);
		$card = new Card($card);
		return $card->addCard();
	}

	/**
	 * Get a user's cards
	 * @param string $email
	 * @return array
	 */
	public function getCards(string $email): array {
		return (new Card)->getCards(array('email' => $email));
	}

	/**
	 * Delete a card
	 * @param string|Card $card Card object or card id
	 */
	public function deleteCard($card) {
		$paystack = $this->config->paystack;
		if(is_string($card)) {
			$card = (new Card)->getCard(array(
				'card_id' => $card
			));
		}
		if(!($card instanceof Card)) throw new Exception('Invalid card paramter');

		// Make call if card has been billed
		if($card->hasBeenBilled()) {
			$params = array('authorization_code' => $card->getAuthorizationCode());
			$response = $this->client->post($paystack['delete_card'], array('json' => $params));

			$result = json_decode($response->getBody());
		}

		$card->deleteCard();
	}

	/**
	 * Debit a card
	 * @param string|Card $card Card object or card id
	 * @param float $amount in naira
	 * @param string $card_pin
	 * @return object Paystack response
	 */
	public function debitCard($card, float $amount, string $card_pin) {
		$paystack = $this->config->paystack;
		// Get card from db
		if(is_string($card)) {
			$card = (new Card)->getCard(array(
				'card_id' => $card
			));
		}
		if(!($card instanceof Card)) throw new Exception('Invalid card paramter');

		// Prepare and fire at will
		$params = array(
			'email' => $card->getEmail(),
			'amount' => $amount * 100,
			'authorization_code' => $card->getAuthorizationCode(),
			'pin' => $card_pin
		);

		$response = $this->client->post($paystack['debit_card'], array('json' => $params));
		$result = json_decode($response->getBody());

		if($result->status && $result->data->status == 'success') {
			$auth_code = $result->data->authorization->authorization_code;

			$card = (new Card)->getCard(array('authorization_code' => $auth_code));
			if($card) $card->setAsBilled();
		}

		return $result;
	}

	/**
	 * Submit otp to complete a charge
	 * @param string $otp
	 * @param string $reference
	 * @return object Paystack response
	 */
	public function submitOTP(string $otp, string $reference) {
		$paystack = $this->config->paystack;

		// Prepare and fire at will
		$params = array(
			'otp' => $otp,
			'reference' => $reference
		);

		$response = $this->client->post($paystack['submit_otp'], array('json' => $params));
		$result = json_decode($response->getBody());

		if($result->status && $result->data->status == 'success') {
			$auth_code = $result->data->authorization->authorization_code;

			$card = (new Card)->getCard(array('authorization_code' => $auth_code));
			if($card) $card->setAsBilled();
		}

		return $result;
	}

	/**
	 * Submit phone to complete a charge
	 * @param string $phone
	 * @param string $reference
	 * @return object Paystack response
	 */
	public function submitPhone(string $phone, string $reference) {
		$paystack = $this->config->paystack;

		// Prepare and fire at will
		$params = array(
			'phone' => $phone,
			'reference' => $reference
		);

		$response = $this->client->post($paystack['submit_phone'], array('json' => $params));
		$result = json_decode($response->getBody());

		if($result->status && $result->data->status == 'success') {
			$auth_code = $result->data->authorization->authorization_code;

			$card = (new Card)->getCard(array('authorization_code' => $auth_code));
			if($card) $card->setAsBilled();
		}

		return $result;
	}

	/**
	 * Check transaction status
	 * @param $string $reference
	 * @return object paystack response
	 */
	public function checkStatus(string $reference) {
		$paystack = $this->config->paystack;

		// Fire away
		$response = $this->client->get($paystack['tranx_status']($reference));
		$result = json_decode($response->getBody());

		if($result->status && $result->data->status == 'success') {
			$auth_code = $result->data->authorization->authorization_code;

			$card = (new Card)->getCard(array('authorization_code' => $auth_code));
			if($card) $card->setAsBilled();
		}

		return $result;
	}

}
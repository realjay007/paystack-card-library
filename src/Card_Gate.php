<?php
namespace PAY;

// use GuzzleHttp\Exception\{ClientException, ServerException, RequestException};
// use GuzzleHttp\Promise\Promise;
use GuzzleHttp\{Client, json_decode};

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
	 * Set gate configuration
	 * @param array $config Configuration map
	 */
	public function setConfig(array $config) {
		// Allow reusabilty
		$this->config->allow_reusable = $config['allow_reusable'] ?? true;
	}

	/**
	 * Tokenise card and add to db
	 * @param mixed $phone User's phone number or assoc array of params
	 * @param string $email Card owner's email, used as user id
	 * @param string $card_number
	 * @param string $cvv
	 * @param string $exp_month
	 * @param string exp_year
	 * @param string $card_id Optional card_id to set on card
	 * @return Card on success, stdClass on failure
	 */
	public function addCard($phone, string $email = '', string $card_number = '', string $cvv = '', 
		string $exp_month = '', string $exp_year = '', string $card_id = ''
	) {
		if(is_array($phone)) extract($phone);
		$paystack = $this->config->paystack;

		// Strip whitespace from card number
		$card_number = preg_replace('/\s+/', '', $card_number);

		// Tokenise the card
		$params = array(
			'email' => $email,
			'card' => array(
				'number' => $card_number,
				'cvv' => $card_cvv ?? $cvv,
				'expiry_month' => $exp_month,
				'expiry_year' => $exp_year
			)
		);

		$response = $this->client->post($paystack['add_card'], array('json' => $params));

		$result = json_decode($response->getBody());

		if($result->status === false) return $result;

		// Create a new card object
		$data = $result->data;
		$card = array(
			'email' => $email,
			'phone' => $phone,
			'authorization_code' => $data->authorization_code,
			'card_type' => $data->card_type,
			'first_six' => $data->bin,
			'last_four' => $data->last4,
			'hashed_card' => array(
				'function' => 'sha1',
				'value' => sha1($card_number)
			),
			'exp_month' => $data->exp_month,
			'exp_year' => $data->exp_year,
			'bank' => $data->bank,
			'signature' => $data->signature,
			'reusable' => $data->reusable,
			'country_code' => $data->country_code
		);
		if(!empty($card_id)) $card['card_id'] = $card_id;
		$card = new Card($card);
		$card->addCard();
		return $card;
	}

	/**
	 * Add a card by charging it
	 * @param array $params Assoc array of params
	 * "phone", "email", "card_number", "cvv", "exp_month", "exp_year" - See addCard method above
	 * "amount" - Amount to charge
	 * "subaccount" - Subaccount to recive charge
	 * "metadata" - Additional transaction metadata
	 * @return object paystack response
	 */
	public function addCardWithCharge($params) {
		extract($params);
		$paystack = $this->config->paystack;

		$card_number = preg_replace('/\s+/', '', $card_number);
		$payload = array(
			'email' => $email,
			'card' => array(
				'number' => $card_number,
				'cvv' => $card_cvv ?? $cvv,
				'expiry_month' => $exp_month,
				'expiry_year' => $exp_year
			),
			'amount' => $amount * 100
		);
		if(!empty($subaccount)) $payload['subaccount'] = $subaccount;
		if(empty($metadata)) $metadata = array();
		$payload['metadata'] = $metadata;

		$payload['metadata']['_card_gate_private_'] = array(
			'hashed_card' => array(
				'function' => 'sha1',
				'value' => sha1($card_number)
			),
			'phone' => $phone
		);

		$uri = $paystack['debit_card'];
		$response = $this->client->post($uri, array('json' => $payload));
		return json_decode((string) $response->getBody());
	}

	/**
	 * Complete add card with charge
	 * @param string $ref Payment reference
	 * @param string $card Optional card id
	 * @return \PAY\Card returns null if failed
	 */
	public function completeAddCardWithCharge(string $ref, string $card_id = '') {
		$paystack = $this->config->paystack;

		// Get tranx details
		$uri = $paystack['verify_tranx']($ref);
		$response = $this->client->get($uri);
		$result = json_decode((string) $response->getBody());
		if(empty($result->data) || empty($result->data->authorization)) return null;

		// Create a new card object
		$data = $result->data;
		$auth = $data->authorization;
		if(empty($auth->authorization_code)) return null;
		$email = $data->customer->email;
		$meta = $data->metadata->_card_gate_private_;
		$phone = $meta->phone;
		$hashed_card = $meta->hashed_card;
		$card = array(
			'email' => $email,
			'phone' => $phone,
			'authorization_code' => $auth->authorization_code,
			'card_type' => $auth->card_type,
			'first_six' => $auth->bin,
			'last_four' => $auth->last4,
			'hashed_card' => $hashed_card,
			'exp_month' => $auth->exp_month,
			'exp_year' => $auth->exp_year,
			'bank' => $auth->bank,
			'signature' => $auth->signature,
			'reusable' => $auth->reusable,
			'country_code' => $auth->country_code
		);
		if(!empty($card_id)) $card['card_id'] = $card_id;
		$card = new Card($card);
		$card->addCard();
		return $card;
	}

	/**
	 * Get a card
	 * @param mixed $card Card id or array filter
	 * @return mixed Card object or null
	 */
	public function getCard($card) {
		if(is_string($card)) {
			$card = array('card_id' => $card);
		}
		return (new Card)->getCard($card);
	}

	/**
	 * Check if a card exists
	 * @param mixed $card Card id or array filter
	 * @return bool
	 */
	public function cardExists($card): bool {
		if(is_string($card)) {
			$card = array('card_id' => $card);
		}
		return (bool) (new Card)->countCards($card);
	}

	/**
	 * Check if a card with specified card number exists
	 * @param string $card_number
	 * @return mixed Card object or null
	 */
	public function getCardFromNumber(string $card_number) {
		// Strip whitespace
		$card_number = preg_replace('/\s+/', '', $card_number);

		$first_six = substr($card_number, 0, 6);
		$last_four = substr($card_number, -4);
		$hash = sha1($card_number);

		// Prepare and fire query
		$query = array(
			'hashed_card.value' => $hash,
			'first_six' => $first_six,
			'last_four' => $last_four
		);

		$card = (new Card)->getCard($query);
		return $card;
	}

	/**
	 * Get a user's cards
	 * @param mixed $query If string, treated as phone or email, else as filter
	 * @return array
	 */
	public function getCards($query): array {
		if(is_string($query)) {
			$query = trim($query);
			if(filter_var($query, FILTER_VALIDATE_EMAIL)) {
				$query = array('email' => $query);
			}
			else $query = array('phone' => $query);
		}
		return (new Card)->getCards($query);
	}

	/**
	 * Count the amount of cards owned by user
	 * @param mixed $query If string, treated as phone or email, else as filter
	 * @return int
	 */
	public function countCards($query): int {
		if(is_string($query)) {
			$query = trim($query);
			if(filter_var($query, FILTER_VALIDATE_EMAIL)) {
				$query = array('email' => $query);
			}
			else $query = array('phone' => $query);
		}
		return (new Card)->countCards($query);
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
		if(!($card instanceof Card)) return;

		// Make call if card has been billed
		if($card->hasBeenBilled()) {
			$result = $this->deactivateCard($card);
		}

		$card->deleteCard();
	}

	/**
	 * Deactivate a card's auth code
	 * @param Card $card object
	 * @return object Paystack response
	 */
	public function deactivateCard(Card $card) {
		$params = array('authorization_code' => $card->getAuthorizationCode());
		$response = $this->client->post($paystack['delete_card'], array('json' => $params));

		$result = json_decode($response->getBody());
		return $result;
	}

	/**
	 * Debit a card
	 * @param string|Card $card Card object or card id
	 * @param float $amount in naira
	 * @param string $card_pin Optional card pin
	 * @param array $add_fields Assoc array of additional fields to include in the request
	 * @return object Paystack response
	 */
	public function debitCard($card, float $amount, $card_pin = null, $add_fields = null) {
		$paystack = $this->config->paystack;
		// Get card from db
		if(is_string($card)) {
			$card = (new Card)->getCard(array(
				'card_id' => $card
			));
		}
		if(!($card instanceof Card)) throw new Exception('Invalid card parameter');

		// Prepare and fire at will
		$params = array(
			'email' => $card->getEmail(),
			'amount' => $amount * 100,
			'authorization_code' => $card->getAuthorizationCode(),
		);

		if(!is_string($card_pin)) {
			$add_fields = $card_pin;
			$card_pin = null;
		}
		if($card_pin) $params['pin'] = $card_pin;
		if($add_fields) {
			$add_fields = (array) $add_fields;
			$params = array_merge($params, $add_fields);
		}

		// Call endpoint based on card reusability and gate config
		$allow_reusable = $this->config->allow_reusable ?? true;
		$url = ($card->isReusable() && $card->hasBeenBilled() && $allow_reusable)? 'debit_reusable_card' : 'debit_card';
		$url = $paystack[$url];
		$response = $this->client->post($url, array('json' => $params));
		$result = json_decode($response->getBody());

		if($result->status && $result->data->status == 'success') {
			$auth_code = $result->data->authorization->authorization_code;

			$card->setAsBilled();
		}

		return $result;
	}

	/**
	 * Send info to complete a charge
	 * @param string $action phone|otp|pin
	 * @param string $info Information needed to complete charge
	 * @param string $reference Transaction reference
	 * @return object Paystack response
	 */
	public function completeCharge(string $action, string $info, string $reference) {
		$action = strtolower($action);
		if(!in_array($action, array('phone', 'otp', 'pin'))) throw new \Exception('Unrecognized action parameter');

		$paystack = $this->config->paystack;

		// Prepare and fire at will
		$params = array(
			$action => $info,
			'reference' => $reference
		);

		$response = $this->client->post($paystack['submit_'.$action], array('json' => $params));
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
	 * @param string $reference
	 * @return object paystack response
	 */
	public function checkStatus(string $reference) {
		$paystack = $this->config->paystack;

		// Fire away
		$response = $this->client->get($paystack['tranx_status']($reference));
		$result = json_decode($response->getBody());

		if($result->status && $result->data->status === 'success') {
			$auth_code = $result->data->authorization->authorization_code;

			$card = (new Card)->getCard(array('authorization_code' => $auth_code));
			if($card) $card->setAsBilled();
		}

		return $result;
	}

	/**
	 * Verify transaction
	 * @param string $reference
	 * @return object Paystack response
	 */
	public function verifyTransaction(string $reference) {
		$paystack = $this->config->paystack;

		// Fire away
		$response = $this->client->get($paystack['verify_tranx']($reference));
		$result = json_decode($response->getBody());

		if($result->status && $result->data->status === 'success') {
			$auth_code = $result->data->authorization->authorization_code;

			$card = (new Card)->getCard(array('authorization_code' => $auth_code));
			if($card) $card->setAsBilled();
		}

		return $result;
	}

}
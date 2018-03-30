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
	 * Set gate configuration
	 * @param array $config Configuration map
	 */
	public function setConfig(array $config) {
		// Allow reusabilty
		$this->config->allow_reusable = $config['allow_reusable'] ?? true;
	}

	/**
	 * Tokenise card and add to db
	 * @param string $phone User's phone number
	 * @param string $email Card owner's email, used as user id
	 * @param string $card_number
	 * @param string $cvv
	 * @param string $exp_month
	 * @param string exp_year
	 * @param string $card_id Optional card_id to set on card
	 * @return Card on success, stdClass on failure
	 */
	public function addCard(string $phone, string $email = '', string $card_number = '', string $cvv = '', 
		string $exp_month = '', string $exp_year = '', string $card_id = ''
	) {
		$paystack = $this->config->paystack;

		// Strip whitespace from card number
		$card_number = preg_replace('/\s+/', '', $card_number);

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
	 * @param string $card_pin Optional card pin
	 * @return object Paystack response
	 */
	public function debitCard($card, float $amount, string $card_pin = '') {
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

		if(!empty($card_pin)) $params['pin'] = $card_pin;

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
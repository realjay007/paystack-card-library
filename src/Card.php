<?php
namespace PAY;

use MongoDB\BSON\{ObjectID, UTCDateTime};
use Exception, RuntimeException;

/**
 * Card.php
 *
 * Card model
 *
 * @author Julius Ijie
 */

class Card implements \JsonSerializable {

	/**
	 * Handler to Mongo collection
	 * @var \MongoDB\Collection
	 */
	protected static $col = null;

	/**
	 * Mongo assigned id
	 * @var string
	 */
	protected $_id;

	/**
	 * App assigned id
	 * @var string
	 */
	protected $card_id;

	/**
	 * Card owner's email
	 * @var string
	 */
	protected $email;

	/**
	 * Authorization code from paystack returned after card is initialised
	 * @var string
	 */
	protected $authorization_code;

	/**
	 * Type of card: mastercard, visa, verve, etc
	 * @var string
	 */
	protected $card_type;

	/**
	 * First six digits of card number
	 * @var string
	 */
	protected $first_six;

	/**
	 * Last for digits of card number
	 * @var string
	 */
	protected $last_four;

	/**
	 * Month of card expiry in PHP 'm' format
	 * @var string
	 */
	protected $exp_month;

	/**
	 * Year of card expiry in PHP 'Y' format
	 * @var string
	 */
	protected $exp_year;

	/**
	 * Bank from which card comes from in paystack bank code
	 * @var string
	 */
	protected $bank;

	/**
	 * Card signature as returned by paystack
	 * @var string
	 */
	protected $signature;

	/**
	 * Flag to check if the card is reusable without user verification (OTP, phone)
	 * @var bool
	 */
	protected $reusable;

	/**
	 * Card country of origin
	 * @var string
	 */
	protected $country_code;

	/**
	 * Flag to check if card has been billed
	 * A card that has not been billed can not be charged without user verification
	 * @var bool
	 */
	protected $billed;

	/**
	 * Date card was created
	 * @var UTCDateTime
	 */
	protected $date_created;

	/**
	 * Return mongo id
	 * @return string
	 */
	public function getId(): string {
		return (string) $this->_id;
	}

	/**
	 * Return app assigned card id
	 * @return string
	 */
	public function getCardId(): string {
		return $this->card_id;
	}

	/**
	 * Return email of user that owns card
	 * @return string
	 */
	public function getEmail(): string {
		return $this->email;
	}

	/**
	 * Return card authorization code frompaystack
	 * @return string
	 */
	public function getAuthorizationCode(): string {
		return $this->authorization_code;
	}

	/**
	 * Return card type
	 * @return string
	 */
	public function getCardType(): string {
		return $this->card_type;
	}

	/**
	 * Get first six digits of card number
	 * @return string
	 */
	public function getFirstSix(): string {
		return $this->first_six;
	}

	/**
	 * Return last four digits of card number
	 * @return string
	 */
	public function getLastFour(): string {
		return $this->last_four;
	}

	/**
	 * Return Month of expiry
	 * @return string
	 */
	public function getExpMonth(): string {
		return $this->exp_month;
	}

	/**
	 * Return year of expiry
	 * @return string
	 */
	public function getExpYear(): string {
		return $this->year;
	}

	/**
	 * Return bank, card belongs to
	 * @return string
	 */
	public function getBank(): string {
		return $this->bank ?? '';
	}

	/**
	 * Return card signature designated by paystack
	 * @return string
	 */
	public function getSignature(): string {
		return $this->signature;
	}

	/**
	 * check if card is reusable
	 * @return bool
	 */
	public function isReusable(): bool {
		return $this->reusable;
	}

	/**
	 * Check if card has been used for successful billing
	 * @return bool
	 */
	public function hasBeenBilled(): bool {
		return $this->billed;
	}

	/**
	 * Return date of creation
	 * @return DateTime
	 */
	public function getDateCreated(): \DateTime {
		return $this->date_created->toDateTime()->setTimeZone(new \DateTimeZone(date_default_timezone_get()));
	}

	/**
	 * Get card filter query
	 * @return map
	 */
	protected function getFilter(): array {
		$filter = array();
		if(!empty($this->_id)) $filter['_id'] = new ObjectID($this->getId());
		else if(!empty($this->card_id)) $filter['card_id'] = $this->card_id;
		else if(!empty($this->authorization_code)) $filter['authorization_code'] = $this->authorization_code;
		else if(!empty($this->signature)) $filter['signature'] = $this->signature;
		else throw new Exception('Unable to get filter query');
		return $filter;
	}

	/**
	 * Initialize class variables Called on its own or from constructor
	 * @param array $info Associative array containing class properties as keys and data as values
	 * @param array $exclude Array of class fields to skip initialisation
	 * @return object $this
	 */
	protected function init(array $info = array(), array $exclude  = array()) {
		foreach ($info as $key => &$value) {
			// Cast MongoDB BSON ObjectID's to strings
			if($key === '_id') settype($value, 'string');
			if(property_exists($this, $key) && !in_array($key, $exclude)) {
				$this->$key = $value;
			}
		}
		return $this;
	}

	/**
	 * Return object data in specified format
	 * @param string $format Format in which data should be returned - json, array, object
	 * @param array $exclude Array of class properties to exclude from data to return
	 * @return mixed Formatted data
	 */
	protected function getValues(string $format = 'array', array $exclude = array()) {
		$values = array();
		foreach ($this as $key => $value) {
			if(!(is_null($value) || in_array($key, $exclude))) {
				$values[$key] = $value;
			}
		}
		switch ($format) {
			case 'json':
				return json_encode($values);
				break;

			case 'object':
				return (object) $values;
				break;
			
			default:
				return $values;
				break;
		}
	}

	/**
	 * Converts object to value that can be easily converted by json
	 * @return array
	 */
	public function jsonSerialize() {
		$values = $this->getValues('array', array('_id'));
		foreach ($values as $key => &$value) {
			if($value instanceof UTCDateTime) $value = $value->toDateTime()->setTimeZone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
		}
		return $values;
	}

	/**
	 * Generate a unique id for a child class
	 * @param string $name Name of the property in the collection on which an id is generated against
	 * @param int $min Optional minimum amount of characters in generated id
	 * @param int $max Optional maximum amount of characters in generated id
	 * @param string $type Type of id to create 'alnum', 'alpha', 'numeric'
	 * @return string Generated id
	 */
	protected function getUniqId(string $name, int $min = 4, int $max = 6, string $type = 'alnum'): string {
		$check_uniq = function(string $id) use ($name): bool {
			return !boolval(self::$col->count(array($name => $id)));
		};

		$get_rand = function() use (&$min, &$max, $type): string {
			static $i = 0;
			++$i;
			if($i > 10**($min/2.0)) {
				$i = 0;
				++$min;
				++$max;
			}
			$length = rand($min, $max);
			$str = Util::randomString($type, $length);
			return $str;
		};
		while(!$check_uniq($uniq_id = $get_rand())) {

		}
		return $uniq_id;
	}

	/**
	 * Class constructor
	 * @param array $info Associative array containing class properties as keys and data as values
	 */
	public function __construct(array $info = array()) {
		$this->init($info);

		if(empty(self::$col)) {
			self::$col = (new Config)->card_collection;
		}
	}

	/**
	 * Add card to db
	 * @throws RuntimeException
	 * @return self
	 */
	public function addCard(): self {
		// Set up defaults
		$this->date_created = new UTCDateTime(microtime(true)*1000);
		$this->billed = $this->billed ?? false;
		$this->card_id = $this->getUniqId('card_id', 8, 8);

		// Add to db
		$result = self::$col->insertOne($this->getValues());
		if($result->isAcknowledged()) {
			$this->_id = (string) $result->getInsertedId();
			return $this;
		}
		else throw new RuntimeException('Unable to add card');
	}

	/**
	 * Set card as billed
	 * @return self
	 */
	public function setAsBilled(): self {
		$this->billed = true;
		$result = self::$col->updateOne($this->getFilter(), array(
			'$set' => array('billed' => $this->billed)
		));
		if(!$result->isAcknowledged()) throw new RuntimeException('Unable to change card bill status');
		else return $this;
	}

	/**
	 * Delete card
	 * @return self
	 */
	public function deleteCard(): self {
		$result = self::$col->deleteOne($this->getFilter());
		if(!$result->isAcknowledged())
			throw new RuntimeException('Unable to delete card');
		else return $this;
	}

	/**
	 * Get details of a particular card
	 * @param array $info Search query
	 * @return mixed
	 */
	public function getCard(array $info = array()) {
		if(empty($info)) {
			$info = $this->getFilter();
		}
		if(array_key_exists('_id', $info) && !is_a($info['_id'], 'ObjectID')) $info['_id'] = new ObjectID($info['_id']);

		$doc = static::$col->findOne($info);
		if(empty($doc)) return null;
		settype($doc, 'array');
		return $this->init($doc);
	}

	/**
	 * Get a list of cards that match a certain criteria
	 * @param array $query Assoc array used as search arguments
	 * @param int $limit Maximum amount of documents to return
	 * @param int $skip Number of documents to skip
	 * @param array Sorting rules
	 * @return array
	 */
	public function getCards(array $query = array(), int $limit = 0, int $skip = 0, array $sort = array('date_created' => 1)): array {
		$cursor = self::$col->find($query, array(
			'limit' => $limit,
			'skip' => $skip,
			'sort' => $sort
		));
		$result = array();
		foreach ($cursor as $doc) {
			settype($doc, 'array');
			$result[] = new static($doc);
		}
		return $result;
	}

	/**
	 * Count the amount of cards that match certain search conditions
	 * @param array $query Assoc array containing search arguments
	 * @return int
	 */
	public function countCards(array $query = array()): int {
		// If key '_id' exists change to ObjectId
		if(array_key_exists('_id', $query)) {
			if(!is_a($query['_id'], 'ObjectID')) $query['_id'] = new ObjectID($query['_id']);
		}
		// Run the query
		return self::$col->count($query);	
	}

}
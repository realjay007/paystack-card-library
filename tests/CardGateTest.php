<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PAY\{Card_Gate, Card, Config};
use MongoDB\{Collection, Client};

/**
 * @covers Card_Gate
 */
class CardGateTest extends TestCase {

	public function __construct(...$args) {
		$this->db_col = (new Client)->{'demo'}->{'card_test'};
		$this->db_col->deleteMany(array());
		$this->key = 'sk_test_ae491bbb23c3a63a7c3e22effcac206e5e8eedab';
		$this->gate = new Card_Gate($this->db_col, $this->key);

		$this->email = 'juliusijie@gmail.com';
		$this->card_number = '5078 5078 5078 5078 0';
		$this->card_cvv = '884';
		$this->exp_month = '11';
		$this->exp_year = '2018';
		$this->pin = '0000';
		$this->otp = '123456';
		$this->phone = '09058283022';
		parent::__construct(...$args);
	}

	public function testAddCard(): Card {
		$card = $this->gate->addCard($this->phone, $this->email, $this->card_number, $this->card_cvv, $this->exp_month, $this->exp_year);
		
		$this->assertInstanceOf(Card::class, $card);

		return $card;
	}

	/**
	 * @depends testAddCard
	 */
	public function testGetCards($card) {

		$this->assertCount(1, $this->gate->getCards($this->email));

		$this->assertCount(1, $cards = $this->gate->getCards($this->phone));

		return $cards[0];
	}

	/**
	 * @depends testGetCards
	 */
	public function testDebitCard($card) {
		$amount = 5000.00;

		$result = $this->gate->debitCard($card, $amount, $this->pin);

		$this->assertObjectHasAttribute('data', $result);

		// file_put_contents(__DIR__.'/log.txt', json_encode($result));

		$complete_trans = function($result) {
			if($result->data->status === 'send_otp') {
				$result = $this->gate->submitOTP($this->otp, $result->data->reference);
			}
			return $result;
		};

		while(strpos($result->data->status, 'send_') !== false) {
			$result = $complete_trans($result);
		}

		$this->assertContains($result->data->status, array('success', 'failed', 'pending'));

		return array($result, $card);
	}

	/**
	 * @depends testDebitCard
	 */
	public function testCheckStatus($params) {
		list($result, $card) = $params;

		$result = $this->gate->checkStatus($result->data->reference);

		$this->assertObjectHasAttribute('data', $result);

		return $params;
	}

	/**
	 * @depends testCheckStatus
	 */
	public function testDeleteCard($params) {
		list($result, $card) = $params;

		$this->gate->deleteCard($card);

		$this->assertEmpty($this->gate->getCards($this->email));
	}

}
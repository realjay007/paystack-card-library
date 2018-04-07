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
		$this->card_number = '5078 5078 5078 5078 4';
		$this->card_cvv = '884';
		$this->exp_month = '11';
		$this->exp_year = '2030';
		$this->pin = '0000';
		$this->otp = '123456';
		$this->phone = '09058283022';
		parent::__construct(...$args);
	}

	public function testAddCard(): Card {
		$card = $this->gate->addCard($this->phone, $this->email, $this->card_number, $this->card_cvv, $this->exp_month, $this->exp_year);

		$card->setMetaData(array(
			'gt_card' => (bool) rand(0, 1)
		));
		
		$this->assertInstanceOf(Card::class, $card);

		return $card;
	}

	/**
	 * @depends testAddCard
	 */
	public function testAddCardWithCharge(Card $card): string {
		$result = $this->gate->addCardWithCharge(array(
			'phone' => $this->phone,
			'email' => $this->email,
			'card_number' => $this->card_number,
			'card_cvv' => $this->card_cvv,
			'exp_month' => $this->exp_month,
			'exp_year' => $this->exp_year,
			'amount' => 25
		));

		$this->assertObjectHasAttribute('data', $result);

		$complete_trans = function($result) use ($card) {
			static $runs = 0;
			// file_put_contents('log'.$runs.'.txt', var_export($result, true));
			if($runs > 4) throw new \Exception('Too many api calls');
			$result = $this->gate->completeCharge($info = substr($result->data->status, 5), $this->$info, $result->data->reference);
			++$runs;
			return $result;
		};

		while(strpos($result->data->status, 'send_') !== false) {
			$result = $complete_trans($result);
		}

		$this->assertContains($result->data->status, array('success', 'failed'));
		$this->assertObjectHasAttribute('reference', $result->data);
		return $result->data->reference;	
	}

	/**
	 * @depends testAddCardWithCharge
	 */
	public function testCompleteAddCardWithCharge(string $ref) {
		$card = $this->gate->completeAddCardWithCharge($ref);
		$this->assertInstanceOf(Card::class, $card);

		$card->setMetaData(array(
			'gt_card' => (bool) rand(0, 1)
		));

		return $card;
	}

	/**
	 * @depends testAddCard
	 */
	public function testGetCardFromNumber() {
		$card = $this->gate->getCardFromNumber($this->card_number);

		$this->assertInstanceOf(Card::class, $card);
	}

	/**
	 * @depends testAddCard
	 */
	public function testGetCards($card) {

		$this->assertEquals(2, $this->gate->countCards($this->email));

		$this->assertCount(2, $this->gate->getCards($this->email));

		$this->assertCount(2, $cards = $this->gate->getCards($this->phone));

		$this->assertObjectHasAttribute('gt_card', $cards[0]->getMetaData());

		return $cards[0];
	}

	/**
	 * @depends testGetCards
	 */
	public function testDebitCard($card) {
		$amount = 5000.00;

		$result = $this->gate->debitCard($card, $amount);

		// file_put_contents(__DIR__.'/log.txt', json_encode($result));

		$this->assertObjectHasAttribute('data', $result);

		$complete_trans = function($result) use ($card) {
			static $runs = 0;
			// file_put_contents('log'.$runs.'.txt', var_export($result, true));
			if($runs > 4) throw new \Exception('Too many api calls');
			$result = $this->gate->completeCharge($info = substr($result->data->status, 5), $this->$info, $result->data->reference);
			++$runs;
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
	public function testDebitCardAgain($params) {
		list($result, $card) = $params;

		return $this->testDebitCard($card);
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

		$card_count = $this->gate->countCards($this->phone);

		$this->gate->deleteCard($card);

		$this->assertCount($card_count - 1, $this->gate->getCards($this->email));
	}

}
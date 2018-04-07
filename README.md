# Paystack PHP Card library

PHP Composer library for managing payment cards using paystack as payment processor. Uses mongoDB for storage.

To use first include composer autoloader or [install](https://getcomposer.org/) if not installed. Then add autoload file:
```php
require_once 'vendor/autoload.php';
```

Two main classes are used within the library, though you only ever need to work directly with one, the "Card_Gate":
```php
use PAY\Card_Gate;
use PAY\Card;
```

To initialise the gate, you need to pass an instance of the mongoDB collection (\MongoDB\Collection)  where cards are being/will be stored, and your paystack secret key.
```php
$gate = new Card_Gate($db->cards, 'sk_...');
```

To add a card, you need to pass the phone number, email, card number, card cvv, expiry month and year (in that order) to the card gate instance. It returns a Card object on success or paystack response on failure:
```php
$phone = '08123456789';
$email = 'johndoe@gmail.com';
$card_number = '5078 5078 5078 5078 12';
$card_cvv = '081';
$exp_month = '08';
$exp_year = '2019';
// In case you want to set card_id yourself
$card_id = '...';

$card = $gate->addCard($phone, $email, $card_number, $card_cvv, $exp_month, $exp_year, $card_id);
// You can also pass the params using an assoc array
$card = $gate->addCard(array(
	'phone' => $phone, 'email' => $email, 'card_number' => $card_number,
	'card_cvv' => $card_cvv, 'exp_month' => $exp_month,
	'exp_year' => $exp_year, 'card_id' => $card_id
));

if($card instance of Card) {
    // Successful
}
else {
    // Failed
    echo $card->message;
}
```

To add a card with charge, pass the params of the addCard method above in an assoc array with:
* amount `<float>` - Amount to charge user
* subaccount `<string>` - Optional, subaccount where funds should be remitted to
* metadata `<string>` - Optional, additional transaction metadata
Returns paystack response object
```php
$phone = '08123456789';
$email = 'johndoe@gmail.com';
$card_number = '5078 5078 5078 5078 12';
$card_cvv = '081';
$exp_month = '08';
$exp_year = '2019';
$amount = 25;

$result = $gate->addCardWithCharge(array(
	'phone' => $phone, 'email' => $email, 'card_number' => $card_number,
	'card_cvv' => $card_cvv, 'exp_month' => $exp_month,
	'exp_year' => $exp_year, 'amount' => $amount
));

```

After adding card with charge, and making sure the returned transaction is complete,
finish up the card adding process
```php
$reference = $result->data->reference;

$card = $gate->completeCardWithCharge($reference);

if($card) {
    // Successful
}
else {
    // Failed $card is null
}

```

To get cards belonging to a user, pass the email or phone to the getCards function. It returns an array of the user's cards or an empty array if none exists:
```php
$cards = $gate->getCards($phone_or_email);

// To count cards instead, call 'countCards', returns an integer
$no_of_cards = $gate->countCards($phone_or_email);
```

To get details of a card using the id, pass the Id to the getCard method, returns the card object or null
```php
$card = $gate->getCard($card_id);

// To simply check if the card exists, call 'cardExists'
$does_card_exist = $gate->cardExists($card_id);

// To delete a card
$paystack_response = $gate->deleteCard($card_id);
```

To get a card using its card number (useful for validation purposes), call the 'getCardFromNumber' method. Returns null if no matching card.
Note: The library does not store the card number directly, it uses its hash, its first six and last four digits for comparism
```php
$card = $gate->getCardFromNumber($card_number);
```

To use the card for payment, pass the card id, the amount and pin (optional). This returns the paystack response which may require further actions for completion
```php
$response = $gate->debitCard($card_id, $amount, $pin); // Pin is optional

if($response->status) {
    $status = $response->data->status;
    // Transaction reference
    $ref = $response->data->reference;
    if($status === 'success') {
        // Payment is successful, give value
    }
    else if($status === 'send_pin') {
        // Request pin from user
    }
    else if($status === 'send_otp') {
        // Request otp from user
    }
    else if($status === 'send_phone') {
        // Request phone number linked with card
    }
    else if($status === 'pending') {
        // Transaction pending, check status at a later time
    }
    else {
        // Payment failed
        echo $response->message;
    }
}
else {
    // Payment failed, most likely error with card
    echo $response->message;
}
```

To complete a charge when more info has been collected from the user:
``` php
$action = 'otp'; // Could be anyone of 'otp', 'pin', or 'phone';
$info = '1234'; // Info collected from user
$ref = '...'; // Transaction reference from when debit call was made

$response = $gate->completeCharge($action, $info, $ref);

if($response->status) {
    $status = $response->data->status;
    if($status === 'success') {
        // Payment is successful, give value
    }
    else if($status === 'send_pin') {
        // Request pin from user
    }
    else if($status === 'send_otp') {
        // Request otp from user
    }
    else if($status === 'send_phone') {
        // Request phone number linked with card
    }
    else if($status === 'pending') {
        // Transaction pending, check status at a later time
    }
    else {
        // Payment failed
        echo $response->message;
    }
}
else {
    // Payment failed, most likely error with card
    echo $response->message;
}
```

To get the status of a (pending) transaction, pass the reference to the 'checkStatus' method:
```php
$status = $gate->checkStatus($ref);
// Returns similar result to debitCard and completeCharge
// Note: If transaction requires extra info to complete,
// calling this function nullifies the transaction, marking it as failed
```

To get details of a transaction, pass reference to 'verifyTransaction':
```php
$transaction = $gate->verifyTransaction($ref);

```

Card Object:
The card object is jsonSerializable, so you can call json_encode directly on it.
```php
// Get card id
$card->getCardId(): string;

// Get user email
$card->getEmail(): string;

// Get user phone
$card->getPhone(): string;

// Get card type - 'visa', 'mastercard' etc
$card->getCardType(): string;

// Get first six digits of card
$card->getFirstSix(): string;

// Get last four digits
$card->getLastFour(): string;

// Get expiry month
$card->getExpMonth(): string;

// Get expiry year
$card->getExpYear(): string;

// Get bank card belongs to
$card->getBank(): string;

// Get date card was created
$card->getDateCreated(): \DateTime;

// Set metadata (additional info to store with card)
// @param array or object
$card->setMetaData($meta);

// Get metadata info
// Returns an object or null
$card->getMetaData();
```

## Have fun!!!

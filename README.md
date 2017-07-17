# Yii2 Cardcom SDK

This package provides a simple way to integrate with the Cardcom API.


## Installation
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

First add this entry to the `repositories` section of your composer.json:

```
"repositories": [{
    ...
},{
    "type": "git",
    "url": "https://github.com/mipotech/yii2-cardcom.git"
},{
    ...
}],
```

then add this line:

```
"mipotech/yii2-cardcom": "dev-master",
```

to the `require` section of your `composer.json` file and perform a composer update.

## Configuration

Add the following section to the params file (@app/config/params.php):

```php
return [
    ...
    
    'cardcom' => [
        // Basic config
        'terminalNumber' => 'xx',
        'username' => 'xx',
    ],
    ...
];
```

That's it. The package is set up and ready to go.

## Usage

To create an instance of the SDK:

```php
use mipotech\cardcom\CardcomSdk;
use mipotech\cardcom\enums\Currencies;
use mipotech\cardcom\enums\OperationTypes;

$cardcom = new CardcomSdk([
    //'debug' => false, defaults to false
    //'testMode' => true, defaults to false
]);
```

### Initiate a new low profile transaction

Standard:

```php
$sum = 10.00;
$currency = Currencies::ILS;
$operation = OperationTypes::TOKEN;
$productName = 'Test product';
$successUrl = ['/payment/success'];
$errorUrl = ['/payment/error'];
$indicatorUrl = ['/payment/indicator'];
$extraParams = [];
$res = $cardcom->performPaymentRequest($sum, $currency, $operation, $productName, $successUrl, $errorUrl, $indicatorUrl, $extraParams);
```

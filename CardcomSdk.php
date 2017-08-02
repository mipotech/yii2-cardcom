<?php

namespace mipotech\cardcom;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\Url;

use mipotech\cardcom\enums\{
    Currencies, CancelTypes, OperationTypes
};


/**
 * API documentation:
 * @link http://kb.cardcom.co.il/article/AA-00402/0/
 * @author MIPO Technologies Ltd
 */
class CardcomSdk extends Component
{
    const API_LEVEL = 10;       // API version (required)
    const CODEPAGE = '65001';   // UTF-8 encoding

    /**
     * Sandbox credentials
     */
    const TERMINAL_ID_DEV = 1000;
    const TERMINAL_USERNAME_DEV = 'barak9611';

    /**
     * @var bool $debug
     */
    public $debug = false;

    /**
     * @var string $language
     * Valid values: he, en, ru, ar, fr, it, sp, pt
     */
    public $language = 'he';

    /**
     * @var bool $testMode whether to run this SDK in sandbox mode or not
     */
    public $testMode = false;

    /**
     * @var array $lastResponse the contents of the response from the last request
     */
    protected $lastResponse;

    /**
     * @var int $terminalNumber the production terminal number
     */
    protected $terminalNumber;

    /**
     * @var string $username the production username
     */
    protected $username;


    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    function __construct($config = [])
    {
        // Assert production credentials if not in test mode
        if (!(isset($config['testMode']) && $config['testMode'])) {
            $configArr = Yii::$app->params['cardcom'];

            if (empty($configArr)) {
                throw new InvalidConfigException("Missing Cardcom configuration array in config/params.php");
            }

            $this->terminalNumber = $configArr['terminalNumber'];
            $this->username = $configArr['username'];

            if (empty($this->terminalNumber)) {
                throw new InvalidConfigException("Missing Cardcom terminal number in config array");
            } elseif (empty($this->username)) {
                throw new InvalidConfigException("Missing Cardcom username in config array");
            }
        }

        parent::__construct($config);
    }

    /**
     * Perform a payment request
     *
     * @param float $sum the total amount to charge
     * @param int $currency currency code, from enums\Currencies
     * @param int $operation operation code, from enums\OperationTypes
     * @param string $productName name of product, max length 50
     * @param string|array $successUrl
     * @param string|array $errorUrl
     * @param string|array $indicatorUrl
     * @param array $extraParams
     * @throws InvalidConfigException
     * @return array
     */
    public function performPaymentRequest(
        float $sum,
        int $currency,
        int $operation,
        string $productName,
        $successUrl,
        $errorUrl,
        $indicatorUrl,
        array $extraParams = []
    ): array {
        $result = [
            'success' => false,
            'url' => null,
        ];
        $vars = [];

        // Validate data
        if (!Currencies::isValidValue($currency)) {
            throw new InvalidConfigException("\$currency must be a valid value from the Currencies enum");
        }

        // Required paramters
        $vars["Operation"] = $operation;
        $vars['TerminalNumber'] = $this->getTerminalNumber();
        $vars['UserName'] = $this->getUserName();
        $vars["SumToBill"] = ($this->testMode ? 10.5 : $sum);
        $vars["CoinID"] = $currency;
        $vars["Language"] = $this->language;
        $vars['ProductName'] = $extraParams['productName'];
        $vars["APILevel"] = static::API_LEVEL;
        $vars['SuccessRedirectUrl'] = $this->normalizeUrl($successUrl);
        $vars['ErrorRedirectUrl'] = $this->normalizeUrl($errorUrl);
        $vars['IndicatorUrl'] = $this->normalizeUrl($indicatorUrl);
        $vars['codepage'] = static::CODEPAGE;

        // Optional paramters
        if (isset($extraParams['cancelType'])) {
            if (CancelTypes::isValidValue($extraParams['cancelType'])) {
                $vars["CancelType"] = $extraParams['cancelType'];
            } else {
                throw new InvalidConfigException("\$extraParams['cancelType'] must be a valid value from the CancelTypes enum");
            }
        }
        if (isset($extraParams['cancelType'])) {
            $vars["CancelUrl"] = $extraParams['cancelType'];
        }
        if (isset($extraParams['maxPayments'])) {
            $vars["MaxNumOfPayments"] = $extraParams['maxPayments'];
        }
        if (isset($extraParams['returnValue'])) {
            $vars["ReturnValue"] = $extraParams['returnValue'];
        }
        if (isset($extraParams['InvoiceOperationTypes'])) {
            $vars['InvoiceHeadOperation'] = $extraParams['InvoiceOperationTypes'];

            if ($extraParams['InvoiceOperationTypes'] != \mipotech\cardcom\enums\InvoiceOperationTypes::NO_CREATE) {
                $vars['InvoiceHead.CustName'] = $extraParams['InvoiceCustName'];
                $vars['InvoiceHead.SendByEmail'] = $extraParams['SendInvoiceByEmail'];
                $vars['InvoiceHead.Language'] = $this->language;
                $vars['InvoiceHead.CoinID'] = $currency;
                if ($extraParams['SendInvoiceByEmail']) {
                    $vars['InvoiceHead.Email'] = $extraParams['SendInvoiceToEmail'];
                }
                foreach ($extraParams['InvoiceLines'] as $index => $invoiceLine) {
                    $vars['InvoiceLines'.($index + 1).'.Description'] = $invoiceLine['Description'];
                    $vars['InvoiceLines'.($index + 1).'.Price'] = $invoiceLine['Price'];
                    $vars['InvoiceLines'.($index + 1).'.Quantity'] = $invoiceLine['Quantity'];
                    $vars['InvoiceLines'.($index + 1).'.IsPriceIncludeVAT'] = $invoiceLine['IsPriceIncludeVAT'];
                    if ($invoiceLine['ProductID']){
                        $vars['InvoiceLines.'.($index + 1).'ProductID'] = $invoiceLine['ProductID'];
                    }
                    if ($invoiceLine['IsVatFree']){
                        $vars['InvoiceLines.'.($index + 1).'IsVatFree'] = $invoiceLine['IsVatFree'];
                    }
                }
            }
        }

        $res = $this->doRequest('https://secure.cardcom.co.il/Interface/LowProfile.aspx', $vars);
        Yii::info("Response: " . print_r($res, true), __CLASS__);

        if ($res['ResponseCode'] == "0") {
            $result['success'] = true;
            $result['url'] = $res['url'];
        } else {
            $result['errorCode'] = $res['ResponseCode'];
            $result['errorMessage'] = $res['Description'];
        }

        return $result;
    }

    public function getLowProfileIndicator($lowProfileCode)
    {
        $vars = [];
        $vars['terminalnumber'] = $this->getTerminalNumber();
        $vars['username'] = $this->getUserName();
        $vars['lowprofilecode'] = $lowProfileCode;

        $res = $this->doRequest('https://secure.cardcom.co.il/Interface/BillGoldGetLowProfileIndicator.aspx?', $vars,
            'GET');
        return $res;
    }

    /*
     * Request
     */
    protected function doRequest(string $endpoint, array $vars, string $method = 'POST'): array
    {
        $varsEncoded = http_build_query($vars);
        $ch = curl_init();
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $varsEncoded);
        }
        else{
            curl_setopt($ch, CURLOPT_URL, $endpoint.$varsEncoded);
        }
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        /**
         * @link https://stackoverflow.com/questions/3757071/php-debugging-curl
         */
        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }

        $ret = curl_exec($ch);

        if ($ret === false) {
            if ($this->debug) {
                printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
            } else {
                $error = curl_error($ch);
                throw new Exception(print_r($error, true));
            }
        }

        if ($this->debug) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        }

        curl_close($ch);

        parse_str($ret, $this->lastResponse);
        return $this->lastResponse ?: [];
    }

    /**
     * Normalize the URL as a string
     *
     * @param mixed $url
     * @return string
     */
    protected function normalizeUrl($url): string
    {
        if (is_string($url)) {
            return $url;
        } elseif (is_array($url)) {
            return \yii\helpers\Url::to($url, true);
        }
    }

    public function getTerminalNumber()
    {
        return $this->testMode ? static::TERMINAL_ID_DEV : $this->terminalNumber;
    }

    public function getUserName()
    {
        return $this->testMode ? static::TERMINAL_USERNAME_DEV : $this->username;
    }
}

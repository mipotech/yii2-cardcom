<?php

namespace mipotech\cardcom;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\Url;

use mipotech\cardcom\enums\{Currencies, CancelTypes, OperationTypes};


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
     * Extract the low profile code from the response
     * @return string
     */
    public function getLowprofileCode(): string
    {
        $parts = parse_url($this->url);
        parse_str($parts['query'], $query);
        return $lastResponse['LowProfileCode'] ?? '';
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
        $vars =  [];

        // Validate data
        if (!Currencies::isValidValue($currency)) {
            throw new InvalidConfigException("\$currency must be a valid value from the Currencies enum");
        }

        // Required paramters
        $vars["Operation"] = $operation;
        $vars['TerminalNumber'] = ($this->testMode ? static::TERMINAL_ID_DEV : $this->terminalNumber);
        $vars['UserName'] = ($this->testMode ? static::TERMINAL_USERNAME_DEV : $this->username);
        $vars["SumToBill"] = ($this->testMode ? 20 : $sum);
        $vars["CoinID"] = $currency;
        $vars["Language"] =  $this->language;
        $vars['ProductName'] = $extraParams['productName'];
        $vars["APILevel"] = static::API_LEVEL;
        $vars['SuccessRedirectUrl'] = $successUrl;
        $vars['ErrorRedirectUrl'] = $errorUrl;
        $vars['IndicatorUrl']  = $indicatorUrl;
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

        $res = $this->doRequest('https://secure.cardcom.co.il/Interface/LowProfile.aspx', $vars);
        if ($res['ResponseCode'] == "0") {
            $result['success'] = true;
            $result['url'] = $res['url'];
        } else {
            /**
             * @todo Display/log error
             */
        }

        return $result;
    }

    /*
     * Request
     */
    protected function doRequest(string $endpoint, array $vars): array
    {
        $varsEncoded = http_build_query($vars);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $varsEncoded);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FAILONERROR,true);

        $ret = curl_exec($ch);
        $error = curl_error ($ch);

        if( !empty($error)) {
            throw new Exception(print_r($error, true));
        }

        curl_close($ch);
        $this->lastResponse = parse_str($ret);
        return $this->lastResponse;
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
}

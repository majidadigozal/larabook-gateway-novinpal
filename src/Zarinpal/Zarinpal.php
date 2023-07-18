<?php
namespace Larabookir\Gateway\Zarinpal;

use Exception;
use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Larabookir\Gateway\Zarinpal\ZarinpalException;

class Zarinpal extends PortAbstract implements PortInterface
{
    /**
     * Base URL for the ZarinPal API.
     * @var string
     */
    protected const baseUrl = 'https://api.zarinpal.com/pg/v4/payment/';


    /**
     * URL for creating purchase requests.
	 * @var string
     */
    protected const requestUrl = self::baseUrl . 'request.json';

    /**
     * URL for verifying purchase requests.
	 * @var string
     */
    protected const verifyUrl = self::baseUrl . 'verify.json';

    /**
     * URL for submitting payments for a purchase.
	 * @var string
     */
    protected const gateUrl = self::baseUrl . 'https://www.zarinpal.com/pg/StartPay/';


    /**
     * Mapping of api status values.
     */
    const apiStatus = [
        'SUCCESS' => 100,
    ];

    /**
     * {@inheritdoc}
     */
    public function set($amount)
    {
        $this->amount = $amount * 10;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function ready()
    {
        $this->sendPayRequest();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function redirect()
    {
        return redirect()->to(self::gateUrl . $this->refId);
    }

    /**
     * {@inheritdoc}
     */
    public function verify($transaction)
    {
        parent::verify($transaction);

        $status = Request::input('Status');
        $authority = Request::input('Authority');

        $this->userPayment($status, $authority);
        $this->verifyPayment();
        return $this;
    }

    /**
     * Sets callback url
     *
     * @param $url
     */
    function setCallback($url)
    {
        $this->callbackUrl = $url;
        return $this;
    }

    /**
     * Gets callback url
     * @return string
     */
    function getCallback()
    {
        if (!$this->callbackUrl)
            throw new Exception('You have to set callback url first.');
            
        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    /**
     * Send pay request to server
     *
     * @return bool
     *
     * @throws ZarinpalException
     */
    protected function sendPayRequest()
    {
        $this->newTransaction();

        $data = [
            'merchant_id' => $this->config->get('gateway.zarinpal.merchant-id'),
            'amount'   => $this->amount,
            'currency' => 'IRR',
            'description' => 'Transaction #' . $this->transactionId,
            'callback_url' => $this->getCallback(),
        ];

        if(count($this->getValidCardNumbers()))
            $data['metadata'] = [
                'card_pan' => current($this->getValidCardNumbers())
            ];
        
        $response = $this->jsonRequest(self::requestUrl, $data);

        if (array_key_exists('errors', $response)) {
            $errorCode = $response['errors']['code'];
            $this->failed($errorCode);
        }

        $this->refId = $response['data']['authority'];
        $this->transactionSetRefId();
        return true;
    }

    /**
     * Check user payment with GET data
     *
     * @param string $status
     * @param string $authority
     * @return bool
     *
     * @throws ZarinpalException
     */
    protected function userPayment($status, $authority)
    {
        if($status != 'OK' || !$authority) {
            $this->failed('failed');
        }

        return true;
    }

    /**
     * Verify user payment from zarinpal server
     *
     * @return bool
     *
     * @throws ZarinpalException
     */
    protected function verifyPayment()
    {  
        $response = $this->jsonRequest(self::verifyUrl, [
            'merchant_id' => $this->config->get('gateway.zarinpal.merchant-id'),
            'amount'   => $this->amount,
            'authority' => $this->refId
        ]);

        if (array_key_exists('errors', $response)) {
            $this->failed($response['errors']['code']);
        }
      
        $this->trackingCode = $response['ref_id'];
        $this->cardNumber = $response['card_pan'];
        $this->transactionSucceed();
        $this->newLog(Enum::TRANSACTION_SUCCEED, Enum::TRANSACTION_SUCCEED_TEXT);
        return true;
    }


    /**
     * Handle exceptions or errors during a Jibit transaction
     * @param int|string $errorCode The error code of the encountered exception
     * @throws ZarinpalException An instance of the ZarinpalException class with the given error code
     */
    protected function failed($errorCode) {
        $this->transactionFailed();
        $this->newLog($errorCode, ZarinpalException::ERRORS[$errorCode]);
        throw new ZarinpalException($errorCode);
    }

    /**
     * Zarinpal does not support multiple verified card numbers
	 * @param array $validCardNumbers
     * @throws Exception
	 */
	public function setValidCardNumbers($validCardNumbers) {
		throw new Exception('Zarinpal does not support multiple verified card numbers, try to use setValidCardNumber method instead.');
	}

    /**
	 * @param string $validCardNumber
     * @return Zarinpal
	 */
	public function setValidCardNumber($validCardNumber) {
		$this->validCardNumbers = [$validCardNumber];
		return $this;
	}
}

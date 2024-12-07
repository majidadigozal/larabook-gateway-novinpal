<?php
namespace Larabookir\Gateway\Novinpal;

use Exception;
use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\Exceptions\CardValidationNotSupported;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Larabookir\Gateway\Novinpal\NovinpalException;

class Novinpal extends PortAbstract implements PortInterface
{
    /**
     * Base URL for the Novinpal API.
     * @var string
     */
    protected const baseUrl = 'https://api.novinpal.ir/invoice/';


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
    protected const gateUrl = 'https://api.novinpal.ir/invoice/';


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
     * @throws NovinpalException
     */
    protected function sendPayRequest()
    {
        $this->newTransaction();

        $data = [
            'api_key' => $this->config->get('gateway.novinpal.api_key'),
            'amount'   => $this->amount,
            'description' => 'txn #' . $this->transactionId,
            'return_url' => $this->getCallback(),
        ];

        if(count($this->getValidCardNumbers()))
            $data['card_number'] = current($this->getValidCardNumbers());
        
        $response = $this->jsonRequest(self::requestUrl, $data);

        if (array_key_exists('errors', $response) && count($response['errors'])) {
            $errorCode = $response['errors']['code'];
            $this->failed($errorCode);
        }

        $this->refId = $response['data']['ref_id'];
        $this->transactionSetRefId();
        return true;
    }

    /**
     * Check user payment with GET data
     *
     * @param string $status
     * @param string $ref_id
     * @return bool
     *
     * @throws NovinpalException
     */
    protected function userPayment($status, $ref_id)
    {
        if($status != 'OK' || !$ref_id) {
            $this->failed();
        }

        return true;
    }

    /**
     * Verify user payment from novinpal server
     *
     * @return bool
     *
     * @throws NovinpalException
     */
    protected function verifyPayment()
    {  
        $response = $this->jsonRequest(self::verifyUrl, [
            'api_key' => $this->config->get('gateway.novinpal.api_key'),
            'ref_id' => $this->refId
        ]);

        if (array_key_exists('errors', $response) && count($response['errors'])) {
            $this->failed($response['errors']['code']);
        }
      
        $this->trackingCode = $response['data']['ref_id'];
        $this->cardNumber = $response['data']['card_number'];
        $this->transactionSucceed();
        $this->newLog(Enum::TRANSACTION_SUCCEED, Enum::TRANSACTION_SUCCEED_TEXT);
        return true;
    }


    /**
     * Handle exceptions or errors during a transaction
     * @param int|string $errorCode The error code of the encountered exception
     * @throws NovinpalException An instance of the NovinpalException class with the given error code
     */
    protected function failed($errorCode = 0) {
        $this->transactionFailed();
        $this->newLog($errorCode, NovinpalException::ERRORS[$errorCode]);
        throw new NovinpalException($errorCode);
    }

    /**
     * Novinpal does not support multiple verified card numbers
	 * @param array $validCardNumbers
     * @throws Exception
	 */
	public function setValidCardNumbers($validCardNumbers) {
		throw new CardValidationNotSupported('Novinpal does not support multiple verified card numbers, try to use setValidCardNumber method instead.');
	}

    /**
     * Novinpal supports single verified card to force it in payment process
     * This method should be called before payment
	 * @param string $validCardNumber
     * @return Novinpal
	 */
	public function setValidCardNumber($validCardNumber) {
		$this->validCardNumbers = [$validCardNumber];
		return $this;
	}
}

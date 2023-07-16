<?php
namespace Larabookir\Gateway\Jibit;

use Exception;
use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Larabookir\Gateway\Jibit\JibitException;

class Jibit extends PortAbstract implements PortInterface
{
    /**
     * Base URL for the Jibit API.
     * @var string
     */
    protected const baseUrl = 'https://napi.jibit.ir/ppg/v3/';

    /**
     * URL for generating tokens.
     * @var string
     */
    protected const tokenUrl = self::baseUrl . 'tokens';

    /**
     * URL for creating purchase requests.
	 * @var string
     */
    protected const requestUrl = self::baseUrl . 'purchases';

    /**
     * URL for verifying purchase requests.
	 * @var string
     */
    protected const verifyUrl = self::baseUrl . 'purchases/{purchaseId}/verify';

    /**
     * URL for submitting payments for a purchase.
	 * @var string
     */
    protected const gateUrl = self::baseUrl . 'purchases/{purchaseId}/payments';

    /**
     * URL for inquiring a purchase.
	 * @var string
     */
    protected const inquiryUrl = self::baseUrl . 'purchases?purchaseId={purchaseId}';

    /**
     * Mapping of api status values.
     */
    const apiStatus = [
        'SUCCESS' => 'SUCCESSFUL',
        'FAIL' => 'FAILED',
    ];

    /**
     * Mapping of api inquiry status values.
     */
    const apiInquiryStatus = [
        'READY_TO_VERIFY' => 'READY_TO_VERIFY'
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
        return redirect()->to($this->bindPurchaseId($this->refId, self::gateUrl));
    }

    /**
     * {@inheritdoc}
     */
    public function verify($transaction)
    {
        parent::verify($transaction);

        $status = Request::input('status');
        $purchaseId = Request::input('purchaseId');
        $trackingCode = Request::input('pspRRN');
        $maskedCardNumber = Request::input('payerMaskedCardNumber');

        $this->userPayment($status, $purchaseId, $trackingCode, $maskedCardNumber);
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
     * @throws JibitException
     */
    protected function sendPayRequest()
    {
        $this->newTransaction();

        $token = $this->generateToken(
            $this->config->get('gateway.jibit.api_key'),
            $this->config->get('gateway.jibit.secret_key')
        );

        $data = [
            'amount'   => $this->amount,
            'callbackUrl' => $this->getCallback(),
            'clientReferenceNumber' => $this->transactionId,
            'currency' => 'IRR',
        ];

        if(count($this->getValidCardNumbers()))
            $data['payerCardNumbers'] = $this->getValidCardNumbers();
        
        $response = $this->jsonRequest(self::requestUrl, $data, [
            'Authorization: Bearer ' . $token['accessToken']
        ]);

        if (array_key_exists('errors', $response)) {
            $errorCode = $response['errors'][0]['code'];
            $this->failed($errorCode);
        }

        $this->refId = $response['purchaseId'];
        $this->transactionSetRefId();
        return true;
    }

    /**
     * Check user payment with GET data
     *
     * @param string $status
     * @param int|null $purchaseId
     * @param string|null $trackingCode
     * @param string|null $maskedCardNumber
     * @return bool
     *
     * @throws JibitException
     */
    protected function userPayment($status, $purchaseId, $trackingCode, $maskedCardNumber)
    {
        if(!$purchaseId || !$trackingCode || !$maskedCardNumber) {
            $this->failed('failed');
        }

        if ($status != self::apiStatus['SUCCESS']) {
            if(!empty($this->validCardNumbers) && !$this->validateCardNumber($maskedCardNumber))
                $this->failed('purchase.forbidden_card_number');
            else
                $this->failed('failed');
        }
        
        return true;
    }

    /**
     * Verify user payment from jibit server
     *
     * @return bool
     *
     * @throws JibitException
     */
    protected function verifyPayment()
    {
        $token = $this->generateToken(
            $this->config->get('gateway.jibit.api_key'),
            $this->config->get('gateway.jibit.secret_key')
        );

        $inquiryResponse = $this->jsonRequest($this->bindPurchaseId($this->refId, self::inquiryUrl), [], [
            'Authorization: Bearer ' . $token['accessToken']
        ]);

        $inquiryResponse = $inquiryResponse['elements'][0];

        if ($inquiryResponse['state'] != self::apiInquiryStatus['READY_TO_VERIFY'])
            $this->failed('verification_failed');

        if(!$this->validateCardNumber($inquiryResponse['pspMaskedCardNumber']))
            $this->failed('purchase.forbidden_card_number');
        
        $response = $this->jsonRequest($this->bindPurchaseId($this->refId, self::verifyUrl), [], [
            'Authorization: Bearer ' . $token['accessToken']
        ]);

        if (array_key_exists('errors', $response)) {
            $this->failed($response['errors'][0]['code']);
        }
      
        if ($response['status'] != self::apiStatus['SUCCESS']) {
            $this->failed('verification_failed');
        }

        $this->trackingCode = $inquiryResponse['pspRrn'];
        $this->cardNumber = $inquiryResponse['pspMaskedCardNumber'];
        $this->transactionSucceed();
        $this->newLog(Enum::TRANSACTION_SUCCEED, Enum::TRANSACTION_SUCCEED_TEXT);
        return true;
    }

    /**
     * Binds the purchase ID to the given URL.
     * This function can be used to dynamically insert the purchase ID into a URL string. It is helpful when generating
     * unique URLs for specific purchases or for any scenario where the purchase ID needs to be included in the URL.
     * @param $purchaseId
     * @param $url
     * @return string
    */
    protected function bindPurchaseId($purchaseId, $url) {
        return str_replace('{purchaseId}', $purchaseId, $url);
    }

    /**
     * @param $apiKey
     * @param $secretKey
     * @return string
     * @throws JibitException
     */
    private function generateToken($apiKey, $secretKey)
    {
        $response = $this->jsonRequest(self::tokenUrl, [
            'apiKey' => $apiKey,
            'secretKey' => $secretKey
        ]);

        if (array_key_exists('errors', $response)) {
            $errorCode = $response['errors'][0]['code'];
            $this->failed($errorCode);
        }

        return $response;
    }


    /**
     * Handle exceptions or errors during a Jibit transaction
     * @param int|string $errorCode The error code of the encountered exception
     * @throws JibitException An instance of the JibitException class with the given error code
     */
    protected function failed($errorCode) {
        $this->transactionFailed();
        $this->newLog(JibitException::ERROR_CODES[$errorCode], JibitException::ERROR_MESSAGES[$errorCode]);
        throw new JibitException($errorCode);
    }
}

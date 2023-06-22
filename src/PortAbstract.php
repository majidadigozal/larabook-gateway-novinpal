<?php
namespace Larabookir\Gateway;

use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Carbon\Carbon;
use Exception;

abstract class PortAbstract
{
	/**
	 * Transaction id
	 *
	 * @var null|int
	 */
	protected $transactionId = null;

	/**
	 * Transaction row in database
	 */
	protected $transaction = null;

	/**
	 * Customer card number
	 *
	 * @var string
	 */
	protected $cardNumber = '';

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * Port id
	 *
	 * @var int
	 */
	protected $portName;

	/**
	 * Reference id
	 *
	 * @var string
	 */
	protected $refId;

	/**
	 * Amount in Rial
	 *
	 * @var int
	 */
	protected $amount;

	/**
	 * Description of transaction
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Custom Invoice Number of transaction
	 *
	 * @var string
	 */
	protected $customInvoiceNo;

	/**
	 * callback URL
	 *
	 * @var url
	 */
	protected $callbackUrl;

	/**
	 * Tracking code payment
	 *
	 * @var string
	 */
	protected $trackingCode;

	/**
	 * Initialize of class
	 *
	 * @param Config $config
	 * @param DataBaseManager $db
	 * @param int $port
	 */
	function __construct()
	{
		$this->db = app('db');
	}

	/** bootstraper */
	function boot(){

	}

	function setConfig($config)
	{
		$this->config = $config;
	}

	/**
	 * @return mixed
	 */
	function getTable()
	{
		return $this->db->table($this->config->get('gateway.table'));
	}

	/**
	 * @return mixed
	 */
	function getLogTable()
	{
		return $this->db->table($this->config->get('gateway.table') . '_logs');
	}

	/**
	 * Get port id, $this->port
	 *
	 * @return int
	 */
	function getPortName()
	{
		return $this->portName;
	}

	/**
	 * Get port id, $this->port
	 *
	 * @return int
	 */
	function setPortName($name)
	{
		$this->portName = $name;
	}

	/**
	 * Set custom description on current transaction
	 *
	 * @param string $description
	 *
	 * @return void
	 */
	function setCustomDesc ($description)
	{
		$this->description = $description;
	}

	/**
	 * Get custom description of current transaction
	 *
	 * @return string | null
	 */
	function getCustomDesc ()
	{
		return $this->description;
	}

	/**
	 * Set custom Invoice number on current transaction
	 *
	 * @param string $description
	 *
	 * @return void
	 */
	function setCustomInvoiceNo ($invoiceNo)
	{
		$this->customInvoiceNo = $invoiceNo;
	}

	/**
	 * Get custom Invoice number of current transaction
	 *
	 * @return string | null
	 */
	function getCustomInvoiceNo ()
	{
		return $this->customInvoiceNo;
	}

	/**
	 * Return card number
	 *
	 * @return string
	 */
	function cardNumber()
	{
		return $this->cardNumber;
	}

	/**
	 * Return tracking code
	 */
	function trackingCode()
	{
		return $this->trackingCode;
	}

	/**
	 * Get transaction id
	 *
	 * @return int|null
	 */
	function transactionId()
	{
		return $this->transactionId;
	}

	/**
	 * Return reference id
	 */
	function refId()
	{
		return $this->refId;
	}

	/**
	 * Sets price
	 * @param $price
	 * @return mixed
	 */
	function price($price)
	{
		return $this->set($price);
	}

	/**
	 * get price
	 */
	function getPrice()
	{
		return $this->amount;
	}

	/**
	 * Return result of payment
	 * If result is done, return true, otherwise throws an related exception
	 *
	 * This method must be implements in child class
	 *
	 * @param object $transaction row of transaction in database
	 *
	 * @return $this
	 */
	function verify($transaction)
	{
		$this->transaction = $transaction;
		$this->transactionId = $transaction->id;
		$this->amount = intval($transaction->price);
		$this->refId = $transaction->ref_id;
	}

	function getTimeId()
	{
		$genuid = function(){
			return substr(str_pad(str_replace('.','', microtime(true)),12,0),0,12);
		};
		$uid=$genuid();
		while ($this->getTable()->whereId($uid)->first())
			$uid = $genuid();
		return $uid;
	}

	/**
	 * Insert new transaction to poolport_transactions table
	 *
	 * @return int last inserted id
	 */
	protected function newTransaction()
	{
		$uid = $this->getTimeId();

		$this->transactionId = $this->getTable()->insert([
			'id' 			=> $uid,
			'port' 			=> $this->getPortName(),
			'price' 		=> $this->amount,
			'status' 		=> Enum::TRANSACTION_INIT,
			'ip' 			=> Request::getClientIp(),
			'description'	=> $this->description,
			'created_at' 	=> Carbon::now(),
			'updated_at' 	=> Carbon::now(),
		]) ? $uid : null;

		return $this->transactionId;
	}

    /**
     * Commit transaction
     * Set status field to success status
     *
     * @param array $fields
     * @return mixed
     */
	protected function transactionSucceed(array $fields = [])
	{
	    $updateFields = [
            'status' => Enum::TRANSACTION_SUCCEED,
            'tracking_code' => $this->trackingCode,
            'card_number' => $this->cardNumber,
            'payment_date' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

	    if (!empty($fields)) {
	        $updateFields = array_merge($updateFields, $fields);
        }

		return $this->getTable()->whereId($this->transactionId)->update($updateFields);
	}

	/**
	 * Failed transaction
	 * Set status field to error status
	 *
	 * @return bool
	 */
	protected function transactionFailed()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'status' => Enum::TRANSACTION_FAILED,
			'updated_at' => Carbon::now(),
		]);
	}

	/**
	 * Update transaction refId
	 *
	 * @return void
	 */
	protected function transactionSetRefId()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'ref_id' => $this->refId,
			'updated_at' => Carbon::now(),
		]);

	}

	/**
	 * New log
	 *
	 * @param string|int $statusCode
	 * @param string $statusMessage
	 */
	protected function newLog($statusCode, $statusMessage)
	{
		return $this->getLogTable()->insert([
			'transaction_id' => $this->transactionId,
			'result_code' => $statusCode,
			'result_message' => $statusMessage,
			'log_date' => Carbon::now(),
		]);
	}

	/**
	 * Add query string to a url
	 *
	 * @param string $url
	 * @param array $query
	 * @return string
	 */
	protected function makeCallback($url, array $query)
	{
		return $this->url_modify(array_merge($query, ['_token' => csrf_token()]), url($url));
	}

	/**
	 * manipulate the Current/Given URL with the given parameters
	 * @param $changes
	 * @param  $url
	 * @return string
	 */
	protected function url_modify($changes, $url)
	{
		// Parse the url into pieces
		$url_array = parse_url($url);

		// The original URL had a query string, modify it.
		if (!empty($url_array['query'])) {
			parse_str($url_array['query'], $query_array);
			$query_array = array_merge($query_array, $changes);
		} // The original URL didn't have a query string, add it.
		else {
			$query_array = $changes;
		}

		return (!empty($url_array['scheme']) ? $url_array['scheme'] . '://' : null) .
		(!empty($url_array['host']) ? $url_array['host'] : null) .
		(!empty($url_array['port']) ? ':' . $url_array['port'] : null) .
        (!empty($url_array['path']) ? $url_array['path'] : null) .
        '?' . http_build_query($query_array);
	}

	/**
	 * Perform a cURL request with error handling.
	 *
	 * @param string $url The URL to make the request to.
	 * @param string $method The HTTP method (e.g., GET, POST, PUT, DELETE).
	 * @param array $data The request data (optional).
	 * @param array $headers The request headers (optional).
	 * @param bool $json If the request is json
	 * @return string The response body.
	 * @throws Exception if the request fails or encounters an error.
	 */
	function request($url, $method = 'GET', $data = [], $headers = [], $json = false)
	{
		$ch = curl_init();

		// Set the URL and other options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		
		// Set request data if provided
		if (!empty($data)) {
			if($json) {
				$data = json_encode($data);
				$headers[] = 'Content-Type: application/json';
			} else {
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
			}
		}

		// Set request headers if provided
		if (!empty($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		// Execute the request and capture the response
		$response = curl_exec($ch);

		// Check for cURL errors
		if ($response === false) {
			$errorMessage = curl_error($ch);
			$errorCode = curl_errno($ch);
			curl_close($ch);
			throw new Exception("cURL request failed: [{$errorCode}] {$errorMessage}");
		}

		// Get the HTTP status code
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		// Close the cURL session
		curl_close($ch);

		// Handle the response
		if ($response) {
			return $response;
		} else {
			throw new Exception("Request failed with HTTP status code: {$statusCode}");
		}
	}

	/**
	 * Perform a JSON POST request with error handling.
	 *
	 * @param string $url The URL to make the request to.
	 * @param array $data The request data.
	 * @param array $headers The request headers (optional).
	 * @return array The decoded JSON response.
	 * @throws Exception if the request fails, encounters an error, or if the response is not valid JSON.
	 */
	function jsonRequest($url, $data, $headers = [])
	{
		$response = $this->request($url, 'POST', $data, $headers, true);

		// Decode the JSON response
		$responseData = json_decode($response, true);

		// Check if JSON decoding was successful
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new Exception("Invalid JSON response: " . json_last_error_msg());
		}

		return $responseData;
	}
}

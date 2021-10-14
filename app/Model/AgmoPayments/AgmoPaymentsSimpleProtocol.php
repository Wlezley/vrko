<?php

//declare(strict_types=1);

namespace App\Model\AgmoPayments;

use Nette;
use Tracy\Debugger;


class AgmoPaymentsSimpleProtocol
{
	private $_paymentsUrl;
	private $_paymentsUrl2; // pro opakovane platby
	private $_merchant;
	private $_test;
	private $_secret;
	private $_transactionId = NULL;
	private $_redirectUrl = NULL;
	private $_statusParams = array();

	/**
	 * @param	string		$paymentsUrl	// AGMO payments system URL
	 * @param	string		$merchant		// merchants identifier
	 * @param	boolean		$test			// TRUE = testing system variant
	 *										// FALSE = release (production) system variant
	 * @param	string		$secret			// merchants password (used for background HTTP communication)
	 * @param	string		$paymentsUrl2	// ???
	 */
	public function __construct($paymentsUrl, $merchant, $test, $secret = NULL, $paymentsUrl2 = NULL)
	{
		$this->_paymentsUrl = $paymentsUrl;
		$this->_paymentsUrl2 = $paymentsUrl2;
		$this->_merchant = $merchant;
		$this->_test = $test;
		$this->_secret = $secret;
	}

	/** Encodes parameters array to string (url encoded parameters glued with ampersand character)
	 * @param	array		$params			// parameters array (key - value pairs)
	 *
	 * @return	string
	 */
	private function _encodeParams($params)
	{
		$data = '';
		foreach($params as $key => $val) {
			$data .= ($data === '' ? '' : '&').urlencode(strval($key)).'='.urlencode(strval($val));
		}
		return $data;
	}

	/** Decodes url encoded parameters glued with ampersand character to key - value pairs
	 * @param	string		$data			// url encoded parameters glued with ampersand character
	 *
	 * @return	array
	 */
	private function _decodeParams($data)
	{
		$encodedParams = explode('&', $data);
		$params = array();
		foreach($encodedParams as $encodedParam) {
			$encodedPair = explode('=', $encodedParam);
			$paramName = urlencode($encodedPair[0]);
			$paramValue = (count($encodedPair) == 2 ? urldecode($encodedPair[1]) : '');
			$params[$paramName] = $paramValue;
		}
		return $params;
	}

	/** Check if parameter with a specified name exists in the parameters array
	 * - throws an exception if the parametr doesn't exist
	 * - returns the parameter value
	 * @param	array		$params			// parameters array (key - value pairs)
	 * @param	string		$paramName		// parameter name
	 *
	 * @return	string
	 * @throws	AgmoPaymentsException
	 */
	private function _checkParam($params, $paramName)
	{
		if(!isset($params[$paramName])) {
			throw new AgmoPaymentsException('Missing response parameter: '.$paramName);
		}
		return $params[$paramName];
	}

	/** Creates a HTTP POST request and returns the response body
	 * @param	string		$url			// URL address
	 * @param	string		$data			// request body
	 *
	 * @return	string
	 */
	private function _doHttpPost($url, $data)
	{
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_POSTFIELDS, $data);
		$responseBody = curl_exec($c);
		curl_close($c);
		return $responseBody;
	}

	/** Creates new payment transaction
	 * @param	string		$country		// identifier of country (CZ)
	 * @param	float		$price			// price in the specified currency
	 * @param	string		$currency		// 3-code identifier of currency (CZK)
	 * @param	string		$label			// short product label (max 16 characters)
	 * @param	string		$refId			// merchants payment identifier
	 * @param	string		$payerId		// payer identifier
	 * @param	string		$vatPL			// one of VATs PL from Agmo Payments system
	 *										// parameter is required only for MPAY_PL method
	 * @param	string		$category		// product category identifier
	 *										// parameter is required only for MPAY_CZ and SMS_CZ methods
	 * @param	string		$method			// method identifier or 'ALL' value
	 * @param	string		$account		// Identifier of Merchant’s bank account to which AGMO transfers the money.
	 *										// If the parameter is empty, the default Merchant’s account will be used.
	 *										// List of accounts is on https://data.agmo.eu/
	 * @param	string		$email			// clients email address (optional)
	 * @param	string		$phone			// clients phone number (optional)
	 * @param	string		$productName	// product identifier (optional)
	 * @param	string		$language		// language identifier (optional)
	 * @param	bool		$preauth
	 * @param	bool		$reccurring
	 * @param	null		$reccurringId
	 * @param	bool		$eetReport		// overrides eet reporting settings in shop configuration
	 * @param	null		$eetData		// data for eet reporting
	 *
	 * @throws	AgmoPaymentsException
	 */
	public function createTransaction($country, $price, $currency, $label, $refId, $payerId, $vatPL = '', $category = '',
									  $method = 'ALL', $account = '', $email = '', $phone = '', $productName = '', $language = '',
									  $preauth = false, $reccurring = false, $reccurringId = null, $eetReport = false, $eetData = null)
	{
		// Initialize response variables
		$this->_transactionId = NULL;
		$this->_redirectUrl = NULL;

		// Prepare request body
		$requestParams = [
			'merchant'		=> $this->_merchant,
			'test'			=> ($this->_test ? 'true' : 'false'),
			'country'		=> $country,
			'price'			=> round($price * 100),
			'curr'			=> $currency,
			'label'			=> $label,
			'refId'			=> $refId,
			'payerId'		=> $payerId,
			'vatPL'			=> $vatPL,
			'cat'			=> $category,
			'method'		=> $method,
			'account'		=> $account,
			'email'			=> $email,
			'phone'			=> $phone,
			'name'			=> $productName,
			'lang'			=> $language,
			'prepareOnly'	=> 'true',
			'secret'		=> $this->_secret,
			'preauth'		=> $preauth ? 'true' : 'false',
			'initRecurring'	=> $reccurring ? 'true' : 'false',
			'eetReport'		=> $eetReport,
			'eetData'		=> (!empty($eetData) && is_array($eetData)) ? json_encode($eetData) : $eetData
		];

		if($reccurringId != null) {
			$requestParams['initRecurringId'] = $reccurringId;
		}

		$requestBody = $this->_encodeParams($requestParams);

		if($reccurringId != null) {
			$url = $this->_paymentsUrl2;
		} else {
			$url = $this->_paymentsUrl;
		}

		// Do HTTP request
		$responseBody = $this->_doHttpPost($url, $requestBody);

		// Process HTTP response
		$responseParams = $this->_decodeParams($responseBody);
		$responseCode = $this->_checkParam($responseParams, 'code');
		$responseMessage = $this->_checkParam($responseParams, 'message');
		if($responseCode !== '0' || $responseMessage !== 'OK') {
			throw new AgmoPaymentsException('Transaction creation error '.$responseCode.': '.$responseMessage);
		}
		$this->_transactionId = $this->_checkParam($responseParams, 'transId');
		$this->_redirectUrl = $this->_checkParam($responseParams, 'redirect');
	}

	/** Returns an identifier of the transaction created via createTransaction method
	 * @return	string
	 */
	public function getTransactionId()
	{
		return $this->_transactionId;
	}

	/** Returns an URL address for the transaction created via createTransaction method
	 * @return	string
	 */
	public function getRedirectUrl()
	{
		return $this->_redirectUrl;
	}

	/** Returns an TEST status // WLK
	 * @return	bool
	 */
	public function getTest()
	{
		return $this->_test;
	}

	/** Returns merchant ID // WLK
	 * @return	string
	 */
	public function getMerchant()
	{
		return $this->_merchant;
	}

	/** Gets transaction status parameters and check them in the configuration
	 * @param	array		$params
	 *
	 * @throws	AgmoPaymentsException
	 */
	public function checkTransactionStatus($params)
	{
		$this->_statusParams = array();
		if( !isset($params) || !is_array($params) ||
			!isset($params['merchant'])	|| $params['merchant']	=== '' ||
			!isset($params['test'])		|| $params['test']		=== '' ||
			!isset($params['price'])	|| $params['price']		=== '' ||
			!isset($params['curr'])		|| $params['curr']		=== '' ||
			!isset($params['refId'])	|| $params['refId']		=== '' ||
			!isset($params['transId'])	|| $params['transId']	=== '' ||
			!isset($params['secret'])	|| $params['secret']	=== '' ||
			!isset($params['status'])	|| $params['status']	=== '')
		{
			throw new AgmoPaymentsException('Missing parameters');
		}
		if( $params['merchant'] !== $this->_merchant || $params['test'] !== ($this->_test ? 'true' : 'false') || $params['secret'] !== $this->_secret) {
			throw new AgmoPaymentsException('Invalid merchant identification');
		}
		$this->_statusParams = $params;
	}

	/** Returns transId from the transaction status check
	 * @return	string
	 */
	public function getTransactionStatusTransId()
	{
		return $this->_statusParams['transId'];
	}

	/** Returns refId from the transaction status check
	 * @return	string
	 */
	public function getTransactionStatusRefId()
	{
		return $this->_statusParams['refId'];
	}

	/** Returns price from the transaction status check
	 * @return	float
	 */
	public function getTransactionStatusPrice()
	{
		return $this->_statusParams['price'] / 100;
	}

	/** Returns currency from the transaction status check
	 * @return	string
	 */
	public function getTransactionStatusCurrency()
	{
		return $this->_statusParams['curr'];
	}

	/** Returns status from the transaction status check
	 * @return	string
	 */
	public function getTransactionStatus()
	{
		return $this->_statusParams['status'];
	}

	/** Returns fee from the transaction status check
	 * @return	string
	 */
	public function getTransactionFee()
	{
		return $this->_statusParams['fee'];
	}
}
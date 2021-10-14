<?php

declare(strict_types=1);

namespace App\Model\Ecomail;


class EcomailApi2
{
	const	JSONObject	= 'JSONObject',
			JSONArray	= 'JSONArray',
			PlainText	= 'PlainText';

	/** @var	string	$server		// API Server URL */
	private $server;

	/** @var	string	$key		// API Klíč */
	private $key;

	/** @var	string	$response	// Návratový typ */
	private $response;

	/** Konstruktor
	 * @param	string	$server		// API Server URL
	 * @param	string	$key		// API Klíč
	 * @param	string	$response	// Návratový typ
	 */
	public function __construct($server, $key, $response = self::JSONArray)
	{
		$this->server = $server;
		$this->key = $key;
		$this->response = $response;

		if(empty($this->server)) {
			throw new \Exception('You must specify Ecomail API_SERVER.');
		}

		if(empty($this->key)) {
			throw new \Exception('You must specify Ecomail API_KEY.');
		}
	}

	/* ####################################### LISTS ####################################### */

	/** Ziskani seznamu kontaktu
	 * @return	array|stdClass|string
	 */
	public function getListsCollection()
	{
		return $this->get('lists');
	}

	/** Vytvoreni noveho seznamu kontaktu
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function addListCollection(array $data)
	{
		return $this->post('lists', $data);
	}

	/** Zobrazit Seznam
	 * @param	string	$list_id	// ID listu
	 * @return	array|stdClass|string
	 */
	public function showList($list_id)
	{
		$url = $this->joinString('lists/', $list_id);
		return $this->get($url);
	}

	/** Aktualizovat Seznam
	 * @param	string	$list_id	// ID listu
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function updateList($list_id, array $data)
	{
		$url = $this->joinString('lists/', $list_id);
		return $this->put($url, $data);
	}

	/** Seznam Subscriberu
	 * @param	string	$list_id	// ID listu
	 * @return	array|stdClass|string
	 */
	public function getSubscribers($list_id)
	{
		$url = $this->joinString('lists/', $list_id, '/subscribers');
		return $this->get($url);
	}

	/** Ziskat DATA Subscribera
	 * @param	string	$list_id	// ID listu
	 * @param	string	$email		// Email
	 * @return	array|stdClass|string
	*/
	public function getSubscriber($list_id, $email)
	{
		$url = $this->joinString('lists/', $list_id, '/subscriber/', $email);
		return $this->get($url);
	}

	/** Seznam Subscriberu
	 * @param	string	$email		// Email
	 * @return	array|stdClass|string
	*/
	public function getSubscriberList($email)
	{
		$url = $this->joinString('subscribers/', $email);
		return $this->get($url);
	}

	/** Pridat Subscribera
	 * @param	string	$list_id	// ID listu
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function addSubscriber($list_id, array $data)
	{
		$url = $this->joinString('lists/', $list_id, '/subscribe');
		return $this->post($url, $data);
	}

	/** Odstranit Subscribera
	 * @param	string	$list_id	// ID listu
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function removeSubscriber($list_id, array $data)
	{
		$url = $this->joinString('lists/', $list_id, '/unsubscribe');
		return $this->delete($url, $data);
	}

	/** Aktualizovat Subscribera
	 * @param	string	$list_id	// ID listu
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function updateSubscriber($list_id, array $data)
	{
		$url = $this->joinString('lists/', $list_id, '/update-subscriber');
		return $this->put($url, $data);
	}

	/** Hromadne Pridavani Subscriberu
	 * @param	string	$list_id	// ID listu
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function addSubscriberBulk($list_id, array $data)
	{
		$url = $this->joinString('lists/', $list_id, '/subscribe-bulk');
		return $this->post($url, $data);
	}

	/* #################################### SUBSCRIBERS #################################### */

	/** Odstranit Subscribera z DB (ze VSECH seznamu)
	 * @param	string	$email		// Email
	 * @return	array|stdClass|string
	 */
	public function deleteSubscriber(string $email)
	{
		$url = $this->joinString('subscribers/', $email, '/delete');
		return $this->delete($url);
	}

	/* ##################################### CAMPAIGNS ##################################### */

	/** Seznam Kampani
	 * @param	string	$filters	// Filtr
	 * @return	array|stdClass|string
	 */
	public function listCampaigns($filters = NULL)
	{
		$url = $this->joinString('campaigns');
		if(!is_null($filters)) {
			$url = $this->joinString($url, '?filters=', $filters);
		}
		return $this->get($url);
	}

	/** Pridat Kampan
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function addCampaign(array $data)
	{
		$url = $this->joinString('campaigns');
		return $this->post($url, $data);
	}

	/** Aktualizovat Kampan
	 * @param	int		$campaign_id	// ID kampaně
	 * @param	array	$data			// Data
	 * @return	array|stdClass|string
	 */
	public function updateCampaign($campaign_id, array $data)
	{
		$url = $this->joinString('campaigns/', $campaign_id);
		return $this->put($url, $data);
	}

	/** Toto volani okamzite zaradi danou kampan do fronty k odeslani. Tuto akci jiz nelze vratit zpet.
	 * @param	int		$campaign_id	// ID kampaně
	 * @return	array|stdClass|string
	 */
	public function sendCampaign($campaign_id)
	{
		$url = $this->joinString('campaign/', $campaign_id, '/send');
		return $this->get($url);
	}

	/** Ziska Statistiku Odeslane Kampane
	 * @param	int		$campaign_id	// ID kampaně
	 * @return	array|stdClass|string
	 */
	public function getCampaignStats($campaign_id)
	{
		$url = $this->joinString('campaigns/', $campaign_id, '/stats');
		return $this->get($url);
	}

	/* ###################################### REPORTS ###################################### */

	/* #################################### AUTOMATIONS #################################### */

	/** Seznam Automatizaci
	 * @return	array|stdClass|string
	 */
	public function listAutomations()
	{
		$url = $this->joinString('automation');
		return $this->get($url);
	}

	/* ##################################### TEMPLATES ##################################### */

	/** Vytvorit Template
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function createTemplate(array $data)
	{
		$url = $this->joinString('template');
		return $this->post($url, $data);
	}

	/* ###################################### DOMAINS ###################################### */

	/** Seznam Domen
	 * @return	array|stdClass|string
	 */
	public function listDomains()
	{
		$url = $this->joinString('domains');
		return $this->get($url);
	}

	/** Vytvorit Domenu
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function createDomain(array $data)
	{
		$url = $this->joinString('domains');
		return $this->post($url, $data);
	}

	/** Smazat Domenu
	 * @param	int		$id			// ID domény
	 * @return	array|stdClass|string
	 */
	public function deleteDomain($id)
	{
		$url = $this->joinString('domains/', $id);
		return $this->delete($url);
	}

	/* ################################# TRANSACTION MAILS ################################# */

	/** Odelsat Transakcni Email
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function sendTransactionalEmail(array $data)
	{
		$url = $this->joinString('transactional/send-message');
		return $this->post($url, $data);
	}

	/** Odelsat Transakcni Template
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function sendTransactionalTemplate(array $data)
	{
		$url = $this->joinString('transactional/send-template');
		return $this->post($url, $data);
	}

	/* ###################################### TRACKER ###################################### */

	/** Vytvorit Transakci
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	public function createNewTransaction(array $data)
	{
		$url = $this->joinString('tracker/transaction');
		return $this->post($url, $data);
	}

	/** Aktualizovat Transakci
	 * @param	string	$transaction_id	// ID transakce
	 * @param	array	$data			// Data
	 * @return	array|stdClass|string
	 */
	public function updateTransaction($transaction_id, array $data)
	{
		$url = $this->joinString('tracker/transaction/', $transaction_id);
		return $this->put($url, $data);
	}

	/* #################################### AUTOMATIONS #################################### */

	/** Spustit Automatizaci
	 * @param	string	$automation_id	// ID automatizace
	 * @param	array	$data			// Data
	 * @return	array|stdClass|string
	 */
	public function triggerAutomation($automation_id, array $data)
	{
		$url = $this->joinString('pipelines/', $automation_id, '/trigger');
		return $this->post($url, $data);
	}

	/* ##################################### AUXILIARY ##################################### */

	/** Spojovani Textu
	 * @return	string
	 */
	private function joinString()
	{
		$join = "";
		foreach (func_get_args() as $arg) { $join .= $arg; }
		return $join;
	}

	/* ####################################### CURL  ####################################### */

	/** Pomocna Metoda pro GET
	 * @param	string	$request	// Požadavek
	 * @return	array|stdClass|string
	 */
	private function get($request)
	{
		return $this->send($request);
	}

	/** Pomocna Metoda pro POST
	 * @param	string	$request	// Požadavek
	 * @param	array	$data		// Zaslaná data
	 * @return	array|stdClass|string
	 */
	private function post($request, array $data)
	{
		return $this->send($request, $data);
	}

	/** Pomocna Metoda pro PUT
	 * @param	string	$request	// Požadavek
	 * @param	array	$data		// Zaslaná data
	 * @return	array|stdClass|string
	 */
	private function put($request, array $data = array())
	{
		return $this->send($request, $data, 'put');
	}

	/** Pomocna Metoda pro DELETE
	 * @param	string	$request	// Požadavek
	 * @param	array	$data		// Data
	 * @return	array|stdClass|string
	 */
	private function delete($request, array $data = array())
	{
		return $this->send($request, $data, 'delete');
	}

	/** Odeslani Pozadavku
	 * @param	string		$request	// Požadavek
	 * @param	null|array	$data		// Zaslaná data
	 * @param	null|string	$method		// Metoda (GET, POST, DELETE, PUT)
	 * @return	array|stdClass|string
	 */
	private function send($request, $data = NULL, $method = NULL)
	{
		$urlRequest = $this->server . '/' . $request;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $urlRequest);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if(!is_null($method)) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		}

		if (is_array($data)) {
			$options = 0 | (PHP_VERSION_ID >= 70300 ? JSON_THROW_ON_ERROR : 0);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, $options));
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'key: ' . $this->key,
			'Content-Type: application/json'
		));

		$output = curl_exec($ch);
		
		// Check HTTP status code
		if (!curl_errno($ch)) {
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_code < 200 || $http_code > 299) {
				return array(
					'error' => $http_code,
				);
			}
		}

		curl_close($ch);

		switch ($this->response) {
			case self::JSONArray:
			case self::JSONObject:
				if (is_array(json_decode($output, true))) {
					$output = json_decode($output, $this->response != self::JSONObject);
				}
				break;
		}

		return $output;
	}
}

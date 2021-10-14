<?php

declare(strict_types=1);

namespace App\Model\Ecomail;


class EcomailApi
{
	/** @var string */
	private $server;

	/** @var string */
	private $key;

	public function __construct(string $server, string $key)
	{
		$this->server = $server;
		$this->key = $key;

		if(empty($this->server)) {
			throw new \Exception('You must specify Ecomail API_SERVER.');
		}

		if(empty($this->key)) {
			throw new \Exception('You must specify Ecomail API_KEY.');
		}
	}

	/** Send Request to Ecomail API
	 * @var		string			$url		// URL pozadavku
	 * @var		string			$request	// METODA pozadavku (POST, PUT, GET, ...)
	 * @var		array|string	$data		// DATA pozadavku
	 * 
	 * @return	array
	 */
	private function sendRequest($url, $request = 'POST', $data = '')
	{
		$http_headers = [];
		$http_headers[] = "key: " . $this->key;
		$http_headers[] = "Content-Type: application/json";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);

		if(!empty($data)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

			if($request == 'POST') {
				curl_setopt($ch, CURLOPT_POST, true);
			} else {
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
			}
		}

		$result = curl_exec($ch);
		curl_close($ch);

		return json_decode($result, true);
	}

	/** Ziska vsechny seznamy kontaktu
	 * @return	array
	 */
	public function getLists()
	{
		$url = $this->server . 'lists';
		return $this->sendRequest($url);
	}

	/** Ziska seznam kontaktu podle ID seznamu
	 * @var		integer			$list_id	// ID seznamu kontaktu
	 * 
	 * @return	array
	 */
	public function getList($list_id)
	{
		$url = $this->server . 'lists/' . $list_id;
		return $this->sendRequest($url);
	}

	/** Ziska vsechny odberatele newsletteru podle ID seznamu (20 kontaktu na stranu)
	 * @var		integer			$list_id	// ID seznamu kontaktu
	 * @var		integer			$page		// Cislo stranky
	 * 
	 * @return	array
	 */
	public function getSubscribers($list_id, $page = 1)
	{
		$url = $this->server . 'lists/' . $list_id . '/subscribers' . ($page > 1 ? '?page=' . $page : '');
		return $this->sendRequest($url);
	}

	/** Ziska odberatele newsletteru podle ID seznamu a Emailu
	 * @var		integer			$list_id	// ID seznamu kontaktu
	 * @var		string			$email		// Email odberatele
	 * 
	 * @return	array
	 */
	public function getSubscriber($list_id, $email)
	{
		$url = $this->server . 'lists/' . $list_id . '/subscriber/' . $email;
		return $this->sendRequest($url);
	}

	/** Vytvori noveho odberatele
	 * @var		integer			$list_id				// ID seznamu kontaktu
	 * @var		array			$data					// DATA kontaktu odberatele
	 * @var		bool			$trigger_autoresponders	// Automaticke odpovedi (?)
	 * @var		bool			$update_existing		// Aktualizovat existujici
	 * @var		bool			$resubscribe			// Znovu prihlasit k odberu
	 * 
	 * @return	array
	 */
	public function addSubscriber($list_id, $data = [], $trigger_autoresponders = false, $update_existing = true, $resubscribe = false)
	{
		$url = $this->server . 'lists/' . $list_id . '/subscribe';
		$post = json_encode([
			'subscriber_data' => [
				'name'		=> empty($data['name'])		 ? null : $data['name'],
				'surname'	=> empty($data['surname'])	 ? null : $data['surname'],
				'email'		=>									  $data['email'], // REQUIRED
				'vokativ'	=> empty($data['vokativ'])	 ? null : $data['vokativ'],
				'vokativ_s'	=> empty($data['vokativ_s']) ? null : $data['vokativ_s'],
				'company'	=> empty($data['company'])	 ? null : $data['company'],
				'city'		=> empty($data['city'])		 ? null : $data['city'],
				'street'	=> empty($data['street'])	 ? null : $data['street'],
				'zip'		=> empty($data['zip'])		 ? null : $data['zip'],
				'country'	=> empty($data['country'])	 ? null : $data['country'],
				'phone'		=> empty($data['phone'])	 ? null : $data['phone'],
				'pretitle'	=> empty($data['pretitle'])	 ? null : $data['pretitle'],
				'surtitle'	=> empty($data['surtitle'])	 ? null : $data['surtitle'],
				'birthday'	=> empty($data['birthday'])	 ? null : $data['birthday'],
			//	'custom_fields' => empty($data['custom_fields']) ? null : (array)$data['custom_fields'],
			],
			'trigger_autoresponders' => $trigger_autoresponders,
			'update_existing' => $update_existing,
			'skip_confirmation' => true, // dafault: false
			'resubscribe' => $resubscribe
		]);

		return $this->sendRequest($url, 'POST', $post);
	}

	/** Odhlasi stavajiciho odberatele
	 * @var		integer			$list_id	// ID seznamu kontaktu
	 * @var		string			$email		// Email odberatele
	 * 
	 * @return	array
	 */
	public function deleteSubscriber($list_id, $email)
	{
		$url = $this->server . 'lists/' . $list_id . '/unsubscribe';
		$post = json_encode(['email' => $email]);
		return $this->sendRequest($url, 'DELETE', $post);
	}

	/** Upravi data stavajiciho odberatele
	 * @var		integer			$list_id	// ID seznamu kontaktu
	 * @var		array			$data		// DATA kontaktu odberatele
	 * 
	 * @return	array
	 */
	public function updateSubscriber($list_id, $data = [])
	{
		$url = $this->server . 'lists/' . $list_id . '/update-subscriber';
		$post = json_encode([
			'email' => $data['email'], // REQUIRED
			'subscriber_data' => [
				'name'		=> empty($data['name'])		 ? null : $data['name'],
				'surname'	=> empty($data['surname'])	 ? null : $data['surname'],
			//	'email'		=>									  $data['email'], // REQUIRED
				'vokativ'	=> empty($data['vokativ'])	 ? null : $data['vokativ'],
				'vokativ_s'	=> empty($data['vokativ_s']) ? null : $data['vokativ_s'],
				'company'	=> empty($data['company'])	 ? null : $data['company'],
				'city'		=> empty($data['city'])		 ? null : $data['city'],
				'street'	=> empty($data['street'])	 ? null : $data['street'],
				'zip'		=> empty($data['zip'])		 ? null : $data['zip'],
				'country'	=> empty($data['country'])	 ? null : $data['country'],
				'phone'		=> empty($data['phone'])	 ? null : $data['phone'],
				'pretitle'	=> empty($data['pretitle'])	 ? null : $data['pretitle'],
				'surtitle'	=> empty($data['surtitle'])	 ? null : $data['surtitle'],
				'birthday'	=> empty($data['birthday'])	 ? null : $data['birthday'],
			//	'custom_fields' => empty($data['custom_fields']) ? null : (array)$data['custom_fields'],
			]
		]);

		return $this->sendRequest($url, 'PUT', $post);
	}
}

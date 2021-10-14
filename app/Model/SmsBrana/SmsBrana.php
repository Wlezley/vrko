<?php

declare(strict_types=1);

namespace App\Model\SmsBrana;

use Nette;
use App\Model;
use Nette\Utils\Json;
use Nette\Utils\ArrayHash;
use Nette\Database\Explorer;
use Tracy\Debugger;
use Carbon\Carbon;


class SmsBrana
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var string */
	private $apiURL;

	/** @var string */
	private $login;

	/** @var string */
	private $password;

	/** @var int */
	private $sender_id;


	public function __construct(string $apiURL, string $login, string $password, int $sender_id,
								Explorer $database)
	{
		$this->apiURL = $apiURL;
		$this->login = $login;
		$this->password = $password;
		$this->sender_id = $sender_id;
		$this->database = $database;
	}


	public function sendSMS($number, $message /*, $zakazka = 0, $user = 0*/)
	{
		//return __METHOD__ . "($number, $message): DEBUG BYPASS!";
		//return __FUNCTION__ . "(\"$number\", \"$message\"): DEBUG BYPASS!";

		// QUERY URI
		$query = http_build_query([
			'login'		=> $this->login,
			'password'	=> $this->password,
			'sender_id'	=> $this->sender_id,
			'action'	=> 'send_sms',
			'number'	=> $number, //urlencode($message),
			'message'	=> $message,
		], '', '&', PHP_QUERY_RFC3986); // PHP_QUERY_RFC1738

		// REQUEST URL
		$request = $this->apiURL . "http.php?" . $query; // . "&number=" . $number;

		// For this to work, file_get_contents requires that allow_url_fopen is enabled.
		// This can be done at runtime by including: ini_set("allow_url_fopen", 1);
		$response = file_get_contents($request); // SENT SMS HERE

		// SMS DB LOG
		//$this->logSMS($number, $message, $response, $zakazka, $user);

		/*$xml = simplexml_load_string($response);
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);*/

		$return = [
			'query'		=> $query,
			'request'	=> $request,
			'response'	=> json_decode(json_encode(simplexml_load_string($response)), TRUE),
		];

		//return $response;
		return $return;
	}

	/*public function logSMS($number, $message, $response, $zakazka = 0, $user = 0) // (user 0 = SYSTEM)
	{
		return $this->database->table('log_sms')->insert([
			//'id'			=> //AUTOINCREMENT
			'zakazka'		=> $zakazka,
			'user'			=> $user,
			//'date'		=> Carbon::now()->format('Y-m-d H:i:s'),
			'telefon'		=> $number,
			'message'		=> $message,
			'response'		=> $response
		]);
	}

	public function getSMSLogByOrderID($zakazka = 0)
	{
		$dataIn = $this->database->table('log_sms')->where('zakazka', $zakazka)->order('date DESC')->fetchAll();
		$dataOut = array();

		if(isset($dataIn) && $dataIn)
		{
			foreach ($dataIn as $m_id => $item)
			{
				$userName = "<IS>"; // Default ID: 0 (known as SYSTEM user)
				if($item->user != 0)
				{
					$uname = $this->database->query('SELECT username FROM user_accounts WHERE id = ?', $item->user);
					$userName = (($uname->getRowCount() != 1) ? "ID: " . $item->user : $uname->fetch()->username);
				}

				$dataOut[$m_id] = [
					'id'							=> $item->id,
					'zakazka'						=> $item->zakazka,
					'user'							=> $userName,
					'date'							=> ($item->date == NULL) ? "N/A" : Carbon::createFromTimestamp($item->date->getTimestamp(), 'Europe/Prague')->format('d.m.Y H:i'),
					'telefon'						=> $item->telefon,
					'message'						=> urldecode($item->message),
					'response'						=> $item->response
				];
			}
		}
		return $dataOut;
	}*/
}

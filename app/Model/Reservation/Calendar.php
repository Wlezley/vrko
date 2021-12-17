<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

//declare(strict_types=1);

namespace App\Model\Reservation;

use Latte;
use Nette;
use App\Model;
use App\Model\Reservation;
use App\Model\SmsBrana;
use App\Model\Reviews;

use Nette\Utils\Json;
use Nette\Utils\Random;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;
use Nette\Database\Explorer;
use Tracy\Debugger;

// DATE / TIME
use Carbon\Carbon;

// SEND MAIL
use Nette\Mail;
use Nette\Application\UI\ITemplateFactory;


class Calendar extends Reservation
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Model\SmsBrana\SmsBrana @inject */
	protected $smsbrana;

	/** @var Model\Reviews\Reviews @inject */
	protected $reviews;

	/** @var Nette\Mail\Mailer @inject */
	public $mailer;

	public function __construct(Explorer $database,
								SmsBrana\SmsBrana $smsbrana,
								Reviews\Reviews $reviews,
								Mail\Mailer $mailer)
	{
		$this->database = $database;
		$this->smsbrana = $smsbrana;
		$this->reviews = $reviews;
		$this->mailer = $mailer;
	}

	/* ###################################################################################### */
	/*  DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA DATA  */
	/* ###################################################################################### */

	/** Get RenderData for SELECT-DAY
	 * @param	int				$year
	 * @param	int				$month
	 * 
	 * @return	array|bool		$renderData[...]
	 */
	public function getRenderData_Selectday($year, $month)
	{
		if (!Validators::is($year, 'numericint:' . (Carbon::now()->year + 0) . '..' . (Carbon::now()->year + 1)) ||
			!Validators::is($month, 'numericint:1..12') || strlen((string)$year) != 4 || strlen((string)$month) > 2)
		{
			return false;
		}

		$carbon = new Carbon;
		$carbon->setDateTime($year, $month, 1, 0, 0, 0);
		$calendar = $carbon->subDays($carbon->dayOfWeek == 0 ? 6 : $carbon->dayOfWeek - 1);

		$dateYesterday = Carbon::now()->subDay();

		$pagination = [
			'year'		=> (int)$year,
			'yearPrev'	=> Carbon::now()->setDateTime($year, $month, 1, 0, 0, 0)->subMonth()->year,
			'yearNext'	=> Carbon::now()->setDateTime($year, $month, 1, 0, 0, 0)->addMonth()->year,
			'month'		=> (int)$month,
			'monthPrev'	=> Carbon::now()->setDateTime($year, $month, 1, 0, 0, 0)->subMonth()->month,
			'monthNext'	=> Carbon::now()->setDateTime($year, $month, 1, 0, 0, 0)->addMonth()->month,
		];

		$calMonthPage = [];
		while (($calendar->year  != $pagination['yearNext'] ||
				$calendar->month != $pagination['monthNext']) ||
				$calendar->dayOfWeek != 1)
		{
			$unitsCount = $this->getUnitsCount($calendar->year, $calendar->month, $calendar->day);
			$disabled = ($calendar->year <= $dateYesterday->year && $calendar->month <= $dateYesterday->month && $calendar->day <= $dateYesterday->day);
			$isToday = (Carbon::now()->year == $calendar->year && Carbon::now()->month == $calendar->month && Carbon::now()->day == $calendar->day);
			$bgColor = $this->getColorByPercentil($unitsCount['free'] / $unitsCount['total'], 0, 1);

			if($disabled) { // RESERVATION_FAKE
				$randomFree = (int)rand(0, $unitsCount['total'] - 8);
				$dbDateString = $calendar->year."-".$calendar->month."-".$calendar->day;

				$result = $this->database->query('SELECT * FROM reservation_fake WHERE date = ? LIMIT 1', $dbDateString);
				if($result && $result->getRowCount() == 1) {
					$randomFree = (int)$result->fetch()['randomFree'];
				} else {
					$this->database->query('REPLACE INTO reservation_fake', ['date' => $dbDateString, 'randomFree' => $randomFree]);
				}

				$bgColor = $this->getColorByPercentil($randomFree / $unitsCount['total'], 0, 1);
				//$bgColor = "#EEE";
			}
			else if($isToday) { // TODAY - BLUE MARK
				$bgColor = "#007BFF";
			}

			$calMonthPage[] = [
				'y'			=> $calendar->year,
				'm'			=> $calendar->month,
				'd'			=> $calendar->day,
				'url'		=> $calendar->year."/".$calendar->month."/".$calendar->day,
				'dow'		=> $calendar->dayOfWeek,
				'skip'		=> ($calendar->month == $pagination['monthPrev'] || $calendar->month == $pagination['monthNext']),
				'today'		=> $isToday,
				//'class'	=> ($calendar->dayOfWeek == 0 ? " red" : "").($calendar->month != $month ? " gray" : ""),
				'class'		=> "",
				'disabled'	=> $disabled,
				//'bkg_col'	=> ($disabled ? "#ccc" : $colGenOc),
				'bkg_col'	=> $bgColor,
			];
			$calendar->addDay();
		}

		// COLOR PALETTE (Legend)
		$palette = [];
		$seatsTotal = $this->getUnitsCountTotal();
		for($p = $seatsTotal; $p >= 0; $p--) {
			$palette[] = $this->getColorByPercentil($p / $seatsTotal, 0, 1);
		}

		// DATA
		$renderData = [
			//'now'			=> $this->getDateNowArray(),
			'pagination'	=> $pagination,
			'monthName'		=> $this->getMonthNameFull($month),
			'dayNamesShort'	=> $this->dayNamesShort,
			'calMonthPage'	=> $calMonthPage,
			'palette'		=> $palette,

			// DEBUG: DUMMY
			'debug'			=> "N/A",
		];

		return $renderData;
	}

	/* ###################################################################################### */

	/** Get RenderData for SELECT-HOUR
	 * @param	int				$year
	 * @param	int				$month
	 * @param	int				$day
	 * 
	 * @return	array|bool		$renderData[...]
	 */
	public function getRenderData_Selecthour($year, $month, $day)
	{
		if (!Validators::is($year, 'numericint:' . (Carbon::now()->year + 0) . '..' . (Carbon::now()->year + 1)) ||
			!Validators::is($month, 'numericint:1..12') ||
			!Validators::is($day, 'numericint:1..' . Carbon::create($year, $month, 1)->daysInMonth) ||
			strlen((string)$year) != 4 || strlen((string)$month) > 2 || strlen((string)$day) > 2)
		{
			return false;
		}

		$renderData = [
			'monthName'		=> $this->getMonthNameOrdinal($month),
			//'dayName'		=> $this->getDayNameFull($day),
			'occupancyData'	=> $this->renderOccupancy($year, $month, $day),

			// DEBUG: DUMMY
			'debug'			=> "N/A",
		];

		return $renderData;
	}

	/* ###################################################################################### */
	/* ##################################### UNITS DATA ##################################### */
	/* ###################################################################################### */

	/** Get UNITS Data
	 * @return	Object|NULL		$unitsData[...]
	 */
	public function getUnitsData()
	{
		$result = $this->database->query('SELECT * FROM units WHERE active = 1 ORDER BY hourBegin ASC, minuteBegin ASC, unitID ASC');
		$resultData = $result->fetchAll();
		$outputData = [];

		//$alphabet = range('A', 'Z');
		foreach ($resultData as $item) {
			$id = $item['id'];

			$outputData[$id] = $item;
			$outputData[$id] ['unitLetter'] = $this->getAZbyID($item['unitID']);

			$minuteBegin = $item['minuteBegin'] < 10 ? "0" . $item['minuteBegin'] : $item['minuteBegin'];
			$outputData[$id] ['unitCode'] = $item['hourBegin'] . $minuteBegin . $this->getAZbyID($item['unitID']);
			$outputData[$id] ['minuteBegin'] = $minuteBegin;

			$minuteEnd = $item['minuteEnd'] < 10 ? "0" . $item['minuteEnd'] : $item['minuteEnd'];
			$outputData[$id] ['unitCodeEnd'] = $item['hourEnd'] . $minuteEnd . $this->getAZbyID($item['unitID']);
			$outputData[$id] ['minuteEnd'] = $minuteEnd;
		}

		return ($outputData && $result && $result->getRowCount() > 0) ? $outputData : NULL;
	}

	/** Get UNITS Count - Total
	 * @return	int				$unitsTotal
	 */
	public function getUnitsCountTotal()
	{
		$units = $this->getUnitsData();
		return ($units ? count($units) : 0);
	}

	/** Get UNITS Counter - Array
	 * @param	int				$year
	 * @param	int				$month
	 * @param	int				$day
	 * 
	 * @return	array			$unitsCount ['total','free','occupied','error']
	 */
	public function getUnitsCount($year, $month, $day)
	{
		$free = 0;
		$occupied = 0;
		$total = $this->getUnitsCountTotal();

		foreach($this->getOccupancy($year, $month, $day) as /*$unitName =>*/ $value) {
			switch($value) {
				case 0: $free++; break;
				case 1: $occupied++; break;
			}
		}

		return [
			'total'		=> $total,
			'free'		=> $free,
			'occupied'	=> $occupied,
			'error'		=> ($total != $free + $occupied),
		];
	}

	/** Return TRUE if given UNIT is Enabled (reservable)
	 * @param	string			$unit		// Unit string (eg. '1630A')
	 * 
	 * @return	bool			$result
	 */
	public function isUnitEnabled(string $unit)
	{
		if(strlen($unit) !== 5) {
			return false;
		}

		foreach($this->getUnitsData() as $item) {
			$unitName = $item['hourBegin'] . $item['minuteBegin'] . $this->getAZbyID($item['unitID']);

			if($unit === $unitName) {
				return true;
			}
		}

		return false;
	}

	/** GET Reservation First Hour as int	
	 * @param	string			$units		// Units string (eg. '16A')
	 * 
	 * @return	int				$result
	 */
	public function getReservationFirstHour($units)
	{
		$result = 24; // ERROR = 24 (hourBegin)

		foreach(json_decode($units) as $item) {
			$hourBegin = (int)substr($item, 0, 2);
			if($hourBegin < $result) {
				$result = $hourBegin;
			}
		}

		return (int)$result;
	}

	/* ###################################################################################### */
	/* ################################### OCCUPANCY DATA ################################### */
	/* ###################################################################################### */

	/** RENDER Occupancy data for day
	 * @param	int				$year
	 * @param	int				$month
	 * @param	int				$day
	 * 
	 * @return	array|NULL		$units
	 */
	public function renderOccupancy($year, $month, $day)
	{
		$units = array();
		$date = (string)sprintf("%4d-%02d-%02d", (int)$year, (int)$month, (int)$day);

		// Time machine prevention
		$hourLimit = Carbon::now(parent::$_TIMEZONE_)->addMinute(5)->hour + 1;
		$dateRes = Carbon::create((int)$year, (int)$month, (int)$day)->setTimezone(parent::$_TIMEZONE_)->startOfDay();
		$dateNow = Carbon::now(parent::$_TIMEZONE_)->startOfDay();
		$today = ($dateNow == $dateRes) ? true : false;

		// Units array prepare
		//$alphabet = range('A', 'Z');
		foreach($this->getUnitsData() as $item) {
			$unitName = $item['hourBegin'] . $item['minuteBegin'] . $this->getAZbyID($item['unitID']);
			$units[$unitName] = ($today && $item['hourBegin'] < $hourLimit) ? 1 : 0;
		}
		if(empty($units)) {
			return NULL;
		}

		// Get all reservations for day
		$resultR = $this->database->query('SELECT units FROM reservation_request WHERE date = ? AND status = ?', $date, "CONFIRMED");
		if($resultR && $resultR->getRowCount() >= 1) {
			foreach($resultR->fetchAll() as $unitString) {
				foreach(json_decode($unitString->units) as $unitName) {
					$units[$unitName] = 1;
				}
			}
		}

		// Update occupancy fields
		$resultO = $this->database->query('REPLACE INTO occupancy', [
			'date' => $date,
			'occupancyData' => json_encode($units),
		]);

		return $units;
	}

	/** GET Occupancy data for day
	 * @param	int				$year
	 * @param	int				$month
	 * @param	int				$day
	 * 
	 * @return	array|NULL		$units
	 */
	public function getOccupancy($year, $month, $day)
	{
		$units = array();
		$date = (string)sprintf("%4d-%02d-%02d", (int)$year, (int)$month, (int)$day);

		// Get occupancy fields
		$result = $this->database->query('SELECT occupancyData FROM occupancy WHERE date = ?', $date);
		if($result && $result->getRowCount() > 0) {
			return json_decode($result->fetch()->occupancyData);
		} else {
			return $this->renderOccupancy($year, $month, $day);
		}
	}

	/* ###################################################################################### */
	/* ################################ RESERVATION REQUESTS ################################ */
	/* ###################################################################################### */

	/** Create Reservation request in Database (RAW)
	 * @param	int				$year			// Datum - Rok
	 * @param	int				$month			// Datum - Mesic
	 * @param	int				$day			// Datum - Den
	 * @param	string			$unitsJson		// Units - JSON String s popisem rezervovanych hernich jednotek
	 * @param	string			$name			// Zakaznik - Jmeno
	 * @param	string			$surname		// Zakaznik - Prijmeni
	 * @param	string			$email			// Zakaznik - Email
	 * @param	string			$phone			// Zakaznik - Telefon
	 * @param	bool			$subscribe		// Zakaznik - Odber novinek
	 * 
	 * @return	bool|string		$result			// Uspech == TRUE / Chyba == FALSE
	 */
	public function createReservationRequest_raw($year, $month, $day, $unitsJson, $name, $surname, $email, $phone, $subscribe)
	{
		$date = Carbon::create($year, $month, $day, 0, 0, 0, "Europe/Prague");
		$units = json_decode($unitsJson);

		$customer = [
			'name'		=> $name,
			'surname'	=> $surname,
			'email'		=> $email,
			'phone'		=> $phone,
			'subscribe'	=> $subscribe,
		];

		return $this->createReservationRequest($date, $units, $customer);
	}

	/** Create Reservation request in Database (COMPLEX)
	 * @param	Carbon			$date			// Datum (staci jenom rok, mesic a den; zbytek NULL (hodina se generuje z UNITS))
	 * @param	array			$units			// Pole s popisem rezervovanych hernich jednotek
	 * @param	array			$customer		// Pole s informacemi o zakaznikovi
	 * 
	 * @return	bool|string		$result			// Uspech == TRUE / Chyba == FALSE
	 */
	public function createReservationRequest(Carbon $date, array $units, array $customer)
	{
		// 1.) Kontrola - DATE
		if(Carbon::now("Europe/Prague")->startOfDay() > $date) {
			return "E1"; // Starsi nez dnesni datum
		}

		// 2.) Kontrola - BANLIST (Email + Telefon)
		$resBlEmail = $this->database->query('SELECT * FROM reservation_banlist WHERE type = ? AND value = ? LIMIT 1', 'EMAIL', $customer['email']);
		if($resBlEmail && $resBlEmail->getRowCount() > 0) {
			return "E2_A"; // Email BAN
		}
		$resBlPhone = $this->database->query('SELECT * FROM reservation_banlist WHERE type = ? AND value = ? LIMIT 1', 'PHONE', $customer['phone']);
		if($resBlPhone && $resBlPhone->getRowCount() > 0) {
			return "E3_B"; // Telefon BAN
		}

		// 3.) Kontrola - UNITS
		if(count($units) > $this->getUnitsCountTotal()) {
			return "E3_A"; // Prilis mnoho Units
		}
		if(empty($units)) {
			return "E3_B"; // Prazdne Units
		}

		// 4.) Zapsat data 'customer' do DB -> ziskam customerID
		$resultC = $this->database->table('customer')->insert($customer);
		if(!$resultC) {
			return "E4"; // Nepodarilo se zapsat do DB
		}
		$customerID = $resultC->id;

		// 5.) Vygenerovani Auth Code
		$authCode = $this->getRandomAuthCode(4);
		if($authCode == str_repeat('9', 4)) {
			return "E5"; // Nepodarilo se najit unikatni AuthCode
		}

		// 6.) Zapsat data 'reservation_request' do DB
		$resultR = $this->database->table('reservation_request')->insert([
			'customerID'	=> $customerID,
		//	'created'		=> Carbon::now(),
			'date'			=> (string)sprintf("%4d-%02d-%02d", (int)$date->year, (int)$date->month, (int)$date->day),
		//	'firstHour'		=> $this->getReservationFirstHour(json_encode($units)),
			'firstHour'		=> NULL, // MOVED TO THE COMPLETE RES. REQ. PROCESS
			'firstMinute'	=> NULL, // MOVED TO THE COMPLETE RES. REQ. PROCESS
			'units'			=> json_encode($units),
			'authCode'		=> $authCode,
			'status'		=> 'NEW',
			'reminder'		=> 'DISABLED',
		]);
		if(!$resultR) {
			return "E6"; // Nepodarilo se zapsat do DB
		}

		// 7.) SEND SMS: Auth Code
		if (isset(parent::$_DEBUG_) && parent::$_DEBUG_ !== true) {
			$this->smsbrana->sendSMS($customer['phone'], "Vas SMS Kod pro potvrzeni rezervace VRko.cz je ". $authCode .". Tesime se na Vas! :)");
		} else {
			return (string)$authCode; // AUTH-OVERRIDE (DEBUG-ONLY)
		}

		// DONE
		return true; // "OK: Reservation Request Created...";		
	}

	/** Complete Reservation from Reservation request in Database
	 * @param	string			$authCode		// Autorizacni kod
	 * @param	string			$email			// Emailova adesa (!!! TODO !!!)
	 * 
	 * @return	bool			$result			// Uspech == TRUE / Chyba == FALSE
	 */
	public function completeReservationRequest(string $authCode, string $email = "")
	{
		// 1.) Ziskat 'reservation_request' z DB (podle $authCode a ...?)
		$resultR = $this->database->query('SELECT * FROM reservation_request WHERE authCode = ? AND status = ? LIMIT 1', $authCode, 'NEW');
		if(!$resultR || $resultR->getRowCount() != 1) { // Nepodarilo ziskat data z DB
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB01;
		}
		$reservationRequest = $resultR->fetch();

		$date = Carbon::create($reservationRequest['date']->format('Y-m-d H:i:s.u'), "Europe/Prague")->startOfDay();
		$units = json_decode($reservationRequest['units']);

		// 2.) Ziskat 'customer' z DB (podle 'reservation_request'.'customerID')
		$resultC = $this->database->query('SELECT * FROM customer WHERE id = ?', $reservationRequest['customerID']);
		if(!$resultC || $resultC->getRowCount() != 1) { // Nepodarilo ziskat data z DB
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB02;
		}
		$customer = $resultC->fetch();

		// 3.) Kontrola emailove adresy zakaznika
		if($customer['email'] !== $email) {
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB03;
		}

		// 4.) Pripravit data pro Rezervace (TODO: zkontrolovat jestli je SLOT volny!)
		$startHour = 24;	// 24 == error
		$startMinute = 60;	// 60 == error
		$htmlTableString = "";

		foreach($units as $item) {
			$unitHour = (int)substr($item, 0, 2);
			$unitMinute = (int)substr($item, 2, 2);
			$unitCode = substr($item, 4, 1);

			if($unitHour < $startHour) {
				$startHour = $unitHour;
				$startMinute = $unitMinute;
			}

			if($unitHour == $startHour && $unitMinute < $startMinute) {
				$startMinute = $unitMinute;
			}

			$htmlTableString .= "<p>" . $unitHour . ":" . ($unitMinute < 10 ? "0" : "") . $unitMinute . " / UNIT " . $unitCode . "</p>";
		}

		// 5.) Aktualizace 'reservation_request' v DB
		$this->database->query('UPDATE reservation_request SET', [
			'firstHour'		=> $startHour,
			'firstMinute'	=> $startMinute,
			'status'		=> 'CONFIRMED',
			'reminder'		=> 'WAITING',
			'authCode'		=> NULL,
		], 'WHERE id = ?', $reservationRequest['id']);

		// 6.) TODO: Odeslat Email (Potvrzeni o Rezervaci)
		$mailTemplateData = [
			'customer'		=> $customer,
			'date'			=> $date->format('d.m.Y'),
			'hour'			=> $startHour,
			'minute'		=> ($startMinute < 10 ? "0" : "") . $startMinute,
			'units'			=> var_export($units, true),
			'htmlTabString'	=> $htmlTableString,
		];
		$this->sendMail($customer['email'], "@confirmationEmail", "Potvrzení o rezervaci VRko.cz", $mailTemplateData);

		// 7.) Synchronizace rezervaci pro cely den (podle 'reservation_request'.'date')
		$this->renderOccupancy($date->year, $date->month, $date->day); // REDRAW

		// 8.) Vytvoreni pozadavku na "hodnoceni"
		$reviewRequestResult = $this->reviews->createRequest(
			$date,
			$reservationRequest['units'],
			$customer['name']." ".$customer['surname'],
			$customer['email'],
			$customer['phone'],
			$reservationRequest['id']
		);
		/*if($reviewRequestResult === false) {
			return "Chyba: Nepodařilo se vytvořit požadavek na hodnocení zážitku."; // EB08
		}*/

		// 9.) Odeslání rezervace na VRKO Admina
		$this->sendMail("kozeluh@zetcomp.cz", "@confirmationEmailAdmin", "Nová rezervace VRko.cz", $mailTemplateData);

		// Hotovo ???
		return true; // "OK: Reservation Request Completed...";
	}

	// ### SEND MAIL ###
	private function sendMail($recipient, $templateName, $subject, $data)
	{
		$latte = new Latte\Engine;
		$template = $latte->renderToString(__DIR__ . "/" . $templateName . ".latte", $data);

		$mailMsg = new Mail\Message();
		$mailMsg->setFrom("Rezervace VRko.cz <info@vrko.cz>");
		$mailMsg->addTo($recipient); // TODO: EMAIL Validator: $recipient
		$mailMsg->setSubject($subject);
		$mailMsg->setHtmlBody($template, __DIR__ . "/../../../www/img/email/");

		$this->mailer->send($mailMsg);
	}
}

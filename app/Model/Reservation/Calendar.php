<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

//declare(strict_types=1);

namespace App\Model\Reservation;

use Latte;
use Nette;
use App\Model;
use App\Model\Reservation;
//use App\Model\Dotykacka;
use App\Model\Ecomail;
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

	/** @var Ecomail\EcomailApi @inject */
	protected $ecomail;

	/** @var Model\Reviews\Reviews @inject */
	protected $reviews;

	/** @var Nette\Mail\Mailer @inject */
	public $mailer;

	public function __construct(Explorer $database,
								SmsBrana\SmsBrana $smsbrana,
								Reviews\Reviews $reviews,
								Ecomail\EcomailApi $ecomail,
								Mail\Mailer $mailer)
	{
		$this->database = $database;
		$this->smsbrana = $smsbrana;
		$this->reviews = $reviews;
		$this->ecomail = $ecomail;
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

				$bgColor = $this->getColorByPercentil($randomFree / $unitsCount['total'], 0, 1); // "#EEE";
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
		//	'debug'			=> $this->_RENDER_DEBUG_DATA_(),
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

		// RESYNC Reservations
		$this->syncReservationsByDay($year, $month, $day);

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
	 * @param	string			$unit		// Unit string (eg. '16A')
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

	/** Get Dotykacka TableID by Reservation UnitName
	 * @param	string			$unit		// Unit string (eg. '16A')
	 * 
	 * @return	string|NULL		$result
	 */
	public function getTableIdByUnitName(string $unit)
	{
		if(strlen($unit) !== 5) {
			return NULL;
		}

		//$tables = DOTYKACKA_GAMEOVER->getTables();

		foreach($this->getUnitsData() as $item) {
			$unitName = $item['hourBegin'] . $item['minuteBegin'] . $this->getAZbyID($item['unitID']);
			/*if($unit === $unitName && $item['unitID'] < count($tables)) {
				return $tables[$item['unitID']];
			}*/
		}

		// TODO: CHECK THE DATABESE NOW !!! <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
		return NULL;
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
		//$resultR = $this->database->query('SELECT units FROM reservation WHERE date = ? AND (state = ? OR state = ?) ORDER BY id ASC', $date, 'new', 'active');
		$resultR = $this->database->query('SELECT units FROM reservation WHERE date = ? LIMIT 1', $date);
		if($resultR && $resultR->getRowCount() === 1) {
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
	/* ############################### RESERVATION SLOTS DATA ############################### */
	/* ###################################################################################### */

	public function _RENDER_DEBUG_DATA_()
	{
		return "N/A";
	}

	/** DOTYKACKA: Checks if Reservation SLOT is FREE?
	 * @param	int				$_tableId		// ID Stolu
	 * @param	Carbon			$date			// Datum rezervace
	 * @param	int				$minutes = 60	// Doba trvani rezervace (vychozi: 60 minut)
	 * 
	 * @return	bool			$isFree			// Je SLOT volny? (true: ano / false: ne)
	 */
	public function checkReservationSlot($_tableId, Carbon $date, $minutes = 30)
	{
/*		// Validate _tableId
		if(!in_array($_tableId, DOTYKACKA_GAMEOVER->getTables())) {
			return false;
		}

		// Construct DATE
		$startDate	= Carbon::create($date->year, $date->month, $date->day, $date->hour, 0, 0);
		$endDate	= Carbon::create($date->year, $date->month, $date->day, $date->hour, 0, 0)->addMinutes($minutes);

		// Build FILTER string
		$filter = "_tableId|eq|"	. $_tableId . ";"
				. "startDate|gteq|"	. $startDate->toIso8601ZuluString() . ";"
				. "startDate|lt|"	. $endDate->toIso8601ZuluString();

		// Build "SFPL" string (sort, filter, page, limit)
		//$sfpl = DOTYKACKA_GAMEOVER->translateSFPL("", $filter, 1, 100);

		// Get result from DTK API v2
		$result = DOTYKACKA_GAMEOVER->getReservationList($sfpl);

		if(!empty($result)) {
			if(empty($result->data)) {
				return false; // ERROR ?! CATCH THE ERROR MESSAGES [ !! HERE !! ]
			}

			foreach($result->data as $reservation) {
				if($reservation->status != 'CANCELLED') {
					return false; // FOUND & NOT CANCELLED == NOT FREE
				}
			}
		}
*/
		// TODO: CHECK THE DATABESE NOW !!! <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
		return true; // NOT FOUND OVER ALL == IS FREE
	}

	/** DOTYKACKA: Prepare SLOT data for the Reservation
	 * @param	int				$_customerId	// ID Zakaznika
	 * @param	int				$_tableId		// ID Stolu
	 * @param	int				$year			// Rok
	 * @param	int				$month			// Mesic
	 * @param	int				$day			// Den
	 * @param	int				$hour			// Hodina
	 * @param	string			$note			// Poznamka
	 * 
	 * @return	array|bool		$result			// Data pro vytvoreni polozky v Dotykacce (nejsou-li data == FALSE)
	 */
	public function prepareReservationSlot($_customerId, $_tableId, $year, $month, $day, $hour, $minute, $note = "")
	{
		// Construct DATE
		$minutes	= 30;
		$startDate	= Carbon::create($year, $month, $day, $hour, $minute, 0);
		$endDate	= Carbon::create($year, $month, $day, $hour, $minute, 0)->addMinutes($minutes);
		
		if($this->checkReservationSlot($_tableId, $startDate, $minutes) == false) {
			return false;
		}
/*
		//ReservationSchema($tableId, $seats, $startDate, $endDate, $customerId = 0, $employeeId = 0, $note = "", $flags = 0, $status = 'CONFIRMED');
		$result = DOTYKACKA_GAMEOVER->ReservationSchema(
			$_tableId,							// ID Stolu
			4,									// Pocet mist (zidli)
			$startDate->toIso8601ZuluString(),	// Datum zacatku rezervace (ISO 8601 Zulu)
			$endDate->toIso8601ZuluString(),	// Datum konce rezervace (ISO 8601 Zulu)
			$_customerId,						// ID Zakaznika
			0,									// ID Zamestnance (Vychozi: 0)
			$note,								// Poznamka
			0,									// Priznaky (Vychozi: 0)
			'CONFIRMED'							// Stav rezervace [NEW, CONFIRMED, CANCELLED]
		);
*/
		// TODO: CHECK THE DATABESE NOW !!! <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
		return NULL; // $result;
	}

	/* ###################################################################################### */
	/* ################################## RESERVATION SYNC ################################## */
	/* ###################################################################################### */

	/** DOTYKACKA: Synchronize Reservations Database (DOTY -> DB)
	 * @param	int				$year			// Rok
	 * @param	int				$month			// Mesic
	 * @param	int				$day			// Den
	 * 
	 * @return	array|bool		$result			// Pole dat s rezervacemi pro Dotykacku (nejsou-li data == FALSE)
	 */
	public function syncReservationsByDay($year, $month, $day)
	{
/*		$tableString = implode(",", DOTYKACKA_GAMEOVER->getTables());

		// Construct DATE
		$startDate	= Carbon::create($year, $month, $day, 0, 0, 0);
		$endDate	= Carbon::create($year, $month, $day, 0, 0, 0)->addDay();

		$filter = "_tableId|in|" . $tableString . ";"
				. "startDate|gteq|" . $startDate->toIso8601ZuluString() . ";"
				. "startDate|lt|" . $endDate->toIso8601ZuluString();

		$sfpl = DOTYKACKA_GAMEOVER->translateSFPL("startDate", $filter, 1, 100);
		$reservationList = DOTYKACKA_GAMEOVER->getReservationList($sfpl);

		$units = [];
		$reservations = [];
		if(!empty($reservationList)) {
			foreach($reservationList->data as $reservation) {
				if($reservation->status == 'CANCELLED') {
					continue;
				}

				$reservations[] = (array)$reservation;
				$rsDate = Carbon::create($reservation->startDate /*, 'UTC'* /)->setTimezone(parent::$_TIMEZONE_);
				$rsMinute = $rsDate->minute < 10 ? "0" . $rsDate->minute : $rsDate->minute;

				//$tableChar = range('A', 'Z')[array_search($reservation->_tableId, DOTYKACKA_GAMEOVER->getTables())];
				$tableChar = $this->getAZbyID(array_search($reservation->_tableId, DOTYKACKA_GAMEOVER->getTables()));
				$units[] = $rsDate->hour . $rsMinute . $tableChar;
			}
		}

		//$unitsString = '["' . implode('","', $units) . '"]';
		$dateString = (string)sprintf("%4d-%02d-%02d", (int)$year, (int)$month, (int)$day);

		//$this->database->query('DELETE FROM reservation WHERE date = ?', $dateString);
		$resultO = $this->database->query('REPLACE INTO reservation', [
		//	'customerID'	=> 0,
			'date'			=> $dateString,
			'units'			=> json_encode($units),
		//	'state'			=> "new",
		//	'note'			=> "",
		]);
*/
		// TODO: CHECK THE DATABESE NOW !!! <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
		return true; //$unitsString;
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
			return "E2"; // Email BAN
		}

		$resBlPhone = $this->database->query('SELECT * FROM reservation_banlist WHERE type = ? AND value = ? LIMIT 1', 'PHONE', $customer['phone']);
		if($resBlPhone && $resBlPhone->getRowCount() > 0) {
			return "E3"; // Telefon BAN
		}

		// 3.) Kontrola - UNITS
		if(count($units /*, COUNT_RECURSIVE*/) > $this->getUnitsCountTotal()) {
			return "E4_A"; // Prilis mnoho Units
		}
		if(empty($units)) {
			return "E4_B"; // Prazdne Units
		}

		foreach($units as $item) {
			$_tableId = $this->getTableIdByUnitName($item);

			if(empty($_tableId)) {
				return "E5: " . $item; //"E5"; // Chybi _tableId (neplatny Unit Name?)
			}

			$unitHour = (int)substr($item, 0, 2);
			$startDate = Carbon::create($date->year, $date->month, $date->day, $unitHour, 0, 0);

			if($this->checkReservationSlot($_tableId, $startDate, 30) == false) {
				return "E6"; // Slot jiz byl obsazen
			}
		}

		// 4.) Zapsat data 'customer' do DB -> ziskam customerID
		$resultC = $this->database->table('customer')->insert($customer);
		if(!$resultC) {
			return "E7"; // Nepodarilo se zapsat do DB
		}
		$customerID = $resultC->id;

		// 5.) Vygenerovani Auth Code
		$authCode = $this->getRandomAuthCode(4);
		if($authCode == str_repeat('9', 4)) {
			return "E8"; // Nepodarilo se najit unikatni AuthCode
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
			return "E9"; // Nepodarilo se zapsat do DB
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
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB02-A;
		}

		$customer = $resultC->fetch();

		// !!! HARDCODED: Kontrola emailove adresy zakaznika
		if($customer['email'] !== $email) {
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB02-B;
		}
/*
		// TODO: CHECK THE DATABESE NOW !!! <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
		// 3.) Vytvorit zakaznika v Dotykacce => Ziskam 'dotykackaID'
		// (POUZE kdyz 'customer'.'dotykackaID' == NULL; jinak ziskat z DB)
		$dotykackaID = (string)$customer['dotykackaID'];
		if(empty($dotykackaID)) {
			$newCustomer = DOTYKACKA_GAMEOVER->createCustomers([
				DOTYKACKA_GAMEOVER->CustomerSchema($customer['name'], $customer['surname'], $customer['email'], $customer['phone'])
			]);

			if(empty($newCustomer[0]->id)) { // Nepodarilo se vytvorit zakaznika v Dotykacce (nedostali jsme ID)
				return "Chyba: Nepodařilo se vytvořit záznam zákazníka."; // EB03
			}

			$dotykackaID = $newCustomer[0]->id;
		}
*/
		// 4.) Vytvorit zaznam v EcoMailu => Ziskam 'ecomailID'
		// (POUZE kdyz 'customer'.'subscribe' == 1 && 'customer'.'ecomailID' == NULL)
		$ecomailID = $customer['ecomailID'];
		/*if($customer['subscribe'] == 1 && empty($ecomailID)) {
			$ecomailData = $this->ecomail->addSubscriber(2, [	// <<<<<<<<<<< BRZDA???
				'name'		=> $customer['name'],
				'surname'	=> $customer['surname'],
				'email'		=> $customer['email'],
				'phone'		=> $customer['phone'],
				'vokativ'	=> $customer['name'],
				'vokativ_s'	=> $customer['surname'],
				], FALSE, TRUE, TRUE);
			$ecomailID = (!isset($ecomailData['id'])) ? NULL : $ecomailData['id']; // V pripade chyby je NULL
		}*/

		// 5.) Aktualizovat 'customer' v DB ('dotykackaID', 'ecomailID')
		$this->database->query('UPDATE customer SET', [
			'dotykackaID'	=> NULL, // DOTYKACKA WAS HERE
			'ecomailID'		=> $ecomailID,
		], 'WHERE id = ?', $reservationRequest['customerID']);

		// 6.) Pripravit data pro Rezervace (a zkontrolovat jestli je SLOT volny!)
		$startHour = 24;	// 24 == error
		$startMinute = 60;	// 60 == error
		$note = $customer['name'] . " " . $customer['surname'] . " / " . $customer['phone'];
		$reservations = [];
		foreach($units as $item) {
			$_tableId = $this->getTableIdByUnitName($item);

			if(empty($_tableId)) { // Chybi _tableId (neplatny Unit Name?)
				return "Chyba: Vámi vybrané místo se nám nepodařilo najít. Prosím, zkuste zadat novou rezervaci."; // EB06-A
			}

			$unitHour = (int)substr($item, 0, 2);
			$unitMinute = (int)substr($item, 2, 2);

			if($unitHour < $startHour) {
				$startHour = $unitHour;
			}

			if($unitMinute < $startMinute) {
				$startMinute = $unitMinute;
			}

			$reservation = $this->prepareReservationSlot(NULL /* DOTYKACKA WAS HERE */, $_tableId, $date->year, $date->month, $date->day, $unitHour, $unitMinute, $note);
			if($reservation == false) { // Slot jiz byl obsazen
				return "Chyba: Některé z vybraných míst je již obsazeno. Prosím, vyberte jiný čas rezervace."; // EB06-B
			}

			$reservations[] = $reservation;
		}
/*
		// TODO: CHECK THE DATABESE NOW !!! <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
		// 7.) Vytvorit Rezervace v Dotykacce
		if(empty($reservations)) {
			return "Chyba: Data o rezervaci nejsou k dispozici. Prosím, kontaktujte nás."; // EB07-A
		}

		$resultDtk = DOTYKACKA_GAMEOVER->createReservations($reservations);
		if(empty($resultDtk[0]->id)) {
			return "Chyba: Rezervaci se nepodařilo založit. Prosím, kontaktujte nás."; // EB07-B
		}
*/
		// 8.) Aktualizovat 'reservation_request' v DB (status = 'CONFIRMED', [vymazat 'authCode' ??? ])
		$this->database->query('UPDATE reservation_request SET', [
			'firstHour'		=> $startHour,
			'firstMinute'	=> $startMinute,
			'status'		=> 'CONFIRMED',
			'reminder'		=> 'WAITING',
			'authCode'		=> NULL,
		], 'WHERE id = ?', $reservationRequest['id']);

		// 9.) TODO: Odeslat Email (Potvrzeni o Rezervaci)
		$mailTemplateData = [
		//	'customer'		=> $customer,
			'date'			=> $date->format('d.m.Y'),
		//	'hour'			=> getReservationFirstHour($reservationRequest['units']),
			'hour'			=> $startHour,
			'minute'		=> ($startMinute < 10 ? "0" : "") . $startMinute,
			'units'			=> var_export($units, true),
		//	'resultDtk'		=> var_export($resultDtk, true),
		];
		$this->sendMail($customer['email'], "@confirmationEmail", "Potvrzení o rezervaci VRko.cz", $mailTemplateData);

		// 10.) Synchronizace rezervaci pro cely den (podle 'reservation_request'.'date')
		$this->syncReservationsByDay($date->year, $date->month, $date->day); // SYNC
		$this->renderOccupancy($date->year, $date->month, $date->day); // REDRAW

		// 11.) Vytvoreni pozadavku na "hodnoceni"
		$reviewRequestResult = $this->reviews->createRequest(
			$date,
			$reservationRequest['units'],
			$customer['name']." ".$customer['surname'],
			$customer['email'],
			$customer['phone'],
			$reservationRequest['id']
		);
		/*if($reviewRequestResult === false) {
			return "Chyba: Nepodařilo se vytvořit požadavek na hodnocení zážitku."; // EB11
		}*/

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
		$mailMsg->addBcc("info@vrko.cz");
		$mailMsg->setSubject($subject);
		$mailMsg->setHtmlBody($template, __DIR__ . "/../../../www/img/email/");

		$this->mailer->send($mailMsg);
	}
}

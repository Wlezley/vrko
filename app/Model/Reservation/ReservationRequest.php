<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

declare(strict_types=1);

namespace App\Model\Reservation;

use Latte;
use App\Model\Reservation;
use Carbon\Carbon;
use Nette\Mail;
use Nette\Application\UI\ITemplateFactory;


class ReservationRequest extends Reservation
{
	public function __construct()
	{
	}

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
	 * @return	bool|string		$result			// Uspech == TRUE / Chyba == FALSE / Debug AUTHCODE string
	 */
	public function createReservationRequest_raw(int $year, int $month, int $day, string $unitsJson,
		string $name, string $surname, string $email, string $phone, bool $subscribe): mixed
	{
		if (!$this->checkDate($year, $month, $day)) {
			return false;
		}

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
	 * @param	Carbon			$date			// Datum (staci jenom rok, mesic a den; zbytek null (hodina se generuje z UNITS))
	 * @param	array			$units			// Pole s popisem rezervovanych hernich jednotek
	 * @param	array			$customer		// Pole s informacemi o zakaznikovi
	 * 
	 * @return	bool|string		$result			// Uspech == TRUE / Chyba == FALSE / Debug AUTHCODE string
	 */
	public function createReservationRequest(Carbon $date, array $units, array $customer): mixed
	{
		// CHECK: Date must be today or more
		if (Carbon::now("Europe/Prague")->startOfDay() > $date) {
			return false;
		}

		// CHECK: Blacklist - EMAIL
		$resBlEmail = $this->database->query('SELECT * FROM reservation_banlist WHERE type = ? AND value = ? LIMIT 1', 'EMAIL', $customer['email']);
		if ($resBlEmail && $resBlEmail->getRowCount() > 0) {
			return false;
		}

		// CHECK: Blacklist - PHONE
		$resBlPhone = $this->database->query('SELECT * FROM reservation_banlist WHERE type = ? AND value = ? LIMIT 1', 'PHONE', $customer['phone']);
		if ($resBlPhone && $resBlPhone->getRowCount() > 0) {
			return false;
		}

		// CHECK: Units count
		if (count($units /*, COUNT_RECURSIVE*/) > $this->reservationUnits->getUnitsCountTotal()) {
			return false;
		}

		// CHECK: Unit name and slot occupancy
		foreach ($units as $item) {
			$_tableId = $this->reservationUnits->getTableIdByUnitName($item);

			if (empty($_tableId) || $_tableId == null) {
				return false;
			}

			$unitHour = (int)substr($item, 0, 2);
			$startDate = Carbon::create($date->year, $date->month, $date->day, $unitHour, 0, 0);

			if ($this->reservationSlots->checkReservationSlot($_tableId, $startDate, 60) == false) {
				return false;
			}
		}

		// STORE DATA: 'customer' (gets customerID)
		$resultC = $this->database->table('customer')->insert($customer);
		if (!$resultC) {
			return false;
		}
		$customerID = $resultC->id;

		// GENERATE: Auth Code
		$authCode = $this->getRandomAuthCode(4);
		if ($authCode == str_repeat('9', 4)) {
			return false;
		}

		// STORE DATA: 'reservation_request'
		$resultR = $this->database->table('reservation_request')->insert([
			'customerID'	=> $customerID,
		//	'created'		=> Carbon::now(),
			'date'			=> (string)sprintf("%4d-%02d-%02d", (int)$date->year, (int)$date->month, (int)$date->day),
		//	'firstHour'		=> $this->getReservationFirstHour(json_encode($units)),
			'firstHour'		=> null, // MOVED TO THE COMPLETE RES. REQ. PROCESS
			'units'			=> json_encode($units),
			'authCode'		=> $authCode,
			'status'		=> 'NEW',
			'reminder'		=> 'DISABLED',
		]);
		if (!$resultR) {
			return false;
		}

		// SEND SMS: Auth Code
		if (isset(parent::$_DEBUG_) && parent::$_DEBUG_ !== true) {
			$this->smsbrana->sendSMS($customer['phone'], "Vas SMS Kod pro potvrzeni rezervace VRko.cz je ". $authCode .". Tesime se na Vas! :)");
		}
		else {
			return (string)$authCode; // AUTH-OVERRIDE (DEBUG-ONLY)
		}

		// DONE
		return true; // "OK: Reservation Request Created...";
	}

	/** Complete Reservation from Reservation request in Database
	 * @param	string			$authCode		// Autorizacni kod
	 * @param	string			$email			// Emailova adesa (!!! TODO !!!)
	 * 
	 * @return	string|bool		$result			// Uspech == TRUE / Chyba == FALSE / Chybova zprava (?)
	 */
	public function completeReservationRequest(string $authCode, string $email = ""): mixed
	{
		// 1.) Ziskat 'reservation_request' z DB (podle $authCode a ...?)
		$resultR = $this->database->query('SELECT * FROM reservation_request WHERE authCode = ? AND status = ? LIMIT 1', $authCode, 'NEW');
		if (!$resultR || $resultR->getRowCount() != 1) {
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB01;
		}

		$reservationRequest = $resultR->fetch();

		$date = Carbon::create($reservationRequest['date']->format('Y-m-d H:i:s.u'), "Europe/Prague")->startOfDay();
		$units = json_decode($reservationRequest['units']);

		// 2.) Ziskat 'customer' z DB (podle 'reservation_request'.'customerID')
		$resultC = $this->database->query('SELECT * FROM customer WHERE id = ?', $reservationRequest['customerID']);
		if (!$resultC || $resultC->getRowCount() != 1) {
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB02-A;
		}

		$customer = $resultC->fetch();

		// !!! HARDCODED: Kontrola emailove adresy zakaznika
		if ($customer['email'] !== $email) {
			return "Chyba: Zadaný kód je neplatný, nebo vypršela jeho platnost."; // EB02-B;
		}

		// 3.) Vytvorit zakaznika v Dotykacce => Ziskam 'dotykackaID'
		// (POUZE kdyz 'customer'.'dotykackaID' == null; jinak ziskat z DB)
		$dotykackaID = (string)$customer['dotykackaID'];
		if (empty($dotykackaID)) {
			$newCustomer = $this->doty2->createCustomers([
				$this->doty2->CustomerSchema($customer['name'], $customer['surname'], $customer['email'], $customer['phone'])
			]);

			// Nepodarilo se vytvorit zakaznika v Dotykacce (nedostali jsme ID)
			if (empty($newCustomer[0]->id)) {
				return "Chyba: Nepodařilo se vytvořit záznam zákazníka."; // EB03
			}

			$dotykackaID = $newCustomer[0]->id;
		}

		// 4.) Vytvorit zaznam v EcoMailu => Ziskam 'ecomailID'
		// (POUZE kdyz 'customer'.'subscribe' == 1 && 'customer'.'ecomailID' == null)
		$ecomailID = $customer['ecomailID'];
		/*if ($customer['subscribe'] == 1 && empty($ecomailID)) {
			$ecomailData = $this->ecomail->addSubscriber(2, [	// <<<<<<<<<<< BRZDA???
				'name'		=> $customer['name'],
				'surname'	=> $customer['surname'],
				'email'		=> $customer['email'],
				'phone'		=> $customer['phone'],
				'vokativ'	=> $customer['name'],
				'vokativ_s'	=> $customer['surname'],
				], FALSE, TRUE, TRUE);
			$ecomailID = (!isset($ecomailData['id'])) ? null : $ecomailData['id']; // V pripade chyby je null
		}*/

		// 5.) Aktualizovat 'customer' v DB ('dotykackaID', 'ecomailID')
		$this->database->query('UPDATE customer SET', [
			'dotykackaID'	=> $dotykackaID,
			'ecomailID'		=> $ecomailID,
		], 'WHERE id = ?', $reservationRequest['customerID']);

		// 6.) Pripravit data pro Rezervace (a zkontrolovat jestli je SLOT volny!)
		$startHour = 24;
		$note = $customer['name']." ".$customer['surname']." / ".$customer['phone'];
		$reservations = [];
		foreach ($units as $item) {
			$_tableId = $this->reservationUnits->getTableIdByUnitName($item);

			// Chybi _tableId (neplatny Unit Name?)
			if (empty($_tableId) || $_tableId == null) {
				return "Chyba: Vámi vybrané místo se nám nepodařilo najít. Prosím, zkuste zadat novou rezervaci."; // EB06-A
			}

			$unitHour = (int)substr($item, 0, 2);

			if ($unitHour < $startHour) {
				$startHour = $unitHour;
			}

			$reservation = $this->reservationSlots->prepareReservationSlot($dotykackaID, $_tableId, $date->year, $date->month, $date->day, $unitHour, $note);

			// Slot jiz byl obsazen?
			if ($reservation == false) {
				return "Chyba: Některé z vybraných míst je již obsazeno. Prosím, vyberte jiný čas rezervace."; // EB06-B
			}

			$reservations[] = $reservation;
		}

		// 7.) Vytvorit Rezervace v Dotykacce
		if (empty($reservations)) {
			return "Chyba: Data o rezervaci nejsou k dispozici. Prosím, kontaktujte nás."; // EB07-A
		}

		$resultDtk = $this->doty2->createReservations($reservations);
		if (empty($resultDtk[0]->id)) {
			return "Chyba: Rezervaci se nepodařilo založit. Prosím, kontaktujte nás."; // EB07-B
		}

		// 8.) Aktualizovat 'reservation_request' v DB (status = 'CONFIRMED', [vymazat 'authCode' ??? ])
		$this->database->query('UPDATE reservation_request SET', [
			'firstHour'		=> $startHour,
			'status'		=> 'CONFIRMED',
			'reminder'		=> 'WAITING',
			'authCode'		=> null,
		], 'WHERE id = ?', $reservationRequest['id']);

		// 9.) TODO: Odeslat Email (Potvrzeni o Rezervaci)
		$mailTemplateData = [
		//	'customer'		=> $customer,
			'date'			=> $date->format('d.m.Y'),
		//	'hour'			=> getReservationFirstHour($reservationRequest['units']),
			'hour'			=> $startHour,
			'units'			=> var_export($units, true),
		//	'resultDtk'		=> var_export($resultDtk, true),
		];
		$this->sendConfirmationMail($customer['email'], "Potvrzení o rezervaci VRko.cz", "@confirmationEmail", $mailTemplateData);

		// 10.) Synchronizace rezervaci pro cely den (podle 'reservation_request'.'date')
		$this->reservationSlots->syncReservationsByDay($date->year, $date->month, $date->day); // SYNC
		$this->reservationOccupancy->renderOccupancy($date->year, $date->month, $date->day); // REDRAW

		// 11.) Vytvoreni pozadavku na "hodnoceni"
		$reviewRequestResult = $this->reviews->createRequest(
			$date,
			$reservationRequest['units'],
			$customer['name']." ".$customer['surname'],
			$customer['email'],
			$customer['phone'],
			$reservationRequest['id']
		);
		/*if ($reviewRequestResult === false) {
			return "Chyba: Nepodařilo se vytvořit požadavek na hodnocení zážitku."; // EB11
		}*/

		// DONE
		return true; // "OK: Reservation Request Completed...";
	}

	/** Send Comfirmation Mail
	 * @param	string			$recipient		// Emailova adesa prijemce
	 * @param	string			$subject		// Predmet mailu
	 * @param	string			$templateName	// Nazev sablony
	 * @param	array			$templateData	// Data pro sablonu
	 */
	private function sendConfirmationMail(string $recipient, string $subject, string $templateName, array $templateData): void
	{
		$latte = new Latte\Engine;
		$template = $latte->renderToString(__DIR__ . "/" . $templateName . ".latte", $templateData);

		$mailMsg = new Mail\Message();
		$mailMsg->setFrom("Rezervace VRko.cz <info@vrko.cz>");
		$mailMsg->addTo($recipient); // TODO: EMAIL Validator: $recipient
		$mailMsg->setSubject($subject);
		$mailMsg->setHtmlBody($template, __DIR__ . "/../../../www/img/email/");

		$this->mailer->send($mailMsg);
	}
}
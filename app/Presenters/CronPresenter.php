<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model;
use App\Model\Reviews;
use App\Model\Reservation;
use App\Model\SmsBrana;
use App\Model\Voucher;
use Nette\Database\Explorer;
use Tracy\Debugger;

// DATE / TIME
use Carbon\Carbon;


use samdark\sitemap\Sitemap;

class CronPresenter extends BasePresenter
{
	//const CRON_DEBOUT = true; // DEBUG
	const CRON_DEBOUT = false; // PRODUCTION

	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Model\Reviews\Reviews */
	protected $reviews;

	/** @var Model\Reservation\Calendar */
	protected $calendar;

	/** @var Model\SmsBrana\SmsBrana */
	protected $smsbrana;

	/** @var Model\Voucher\Voucher */
	protected $voucher;

	public function __construct(Explorer $database,
								Reviews\Reviews $reviews,
								Reservation\Calendar $calendar,
								SmsBrana\SmsBrana $smsbrana,
								Voucher\Voucher $voucher)
	{
		Debugger::$showBar = false; // Disable Tracy Debug Bar
		$this->database = $database;
		$this->reviews = $reviews;
		$this->calendar = $calendar;
		$this->smsbrana = $smsbrana;
		$this->voucher = $voucher;
	}

	public function actionDefault($hash, $type = "default")
	{
		if($hash == 'pmzs7jy2vhb7k78qun3z4qjdl9rw66sw')
		{
			switch(strtoupper($type))
			{
				case 'REVIEW': // Email: Review Requests
					$this->reviews->sendRequests();
					return;
				case 'REMINDER': // SMS: Reservation Reminder
					$this->cronTrigger_REMINDER();
					return;
				case 'ECOMAIL': // EcoMail Handler (TODO)
					if(SELF::CRON_DEBOUT) echo "Not implemented yet.";
					return;
				case 'VOUCHERFAKE':
					//$this->cronTrigger_VOUCHERFAKE(0);
					return;
				/*default:
					if(SELF::CRON_DEBOUT) echo "Wrong type provided.";
					return;*/
			}
		}

		header("HTTP/1.0 404 Not Found");
		$this->template->error = true;
		//$this->terminate();
	}

	private function cronTrigger_REMINDER($aheadSchedule = 3)
	{
		// Date and time initialization
		$carbon = Carbon::now("Europe/Prague")->addHours($aheadSchedule)->startOfHour(); // Default: 3 hours
		$dateNow = $carbon->format("Y-m-d");
		$hourNow = (int)$carbon->format("H");

		// Gets reservation data from DB (WHERE: status = CONFIRMED, reminder = WAITING, date = dateNow, firstHour = hourNow (OR NULL))
		$result1 = $this->database->query(
			"SELECT id,customerID,units,firstHour,firstMinute FROM reservation_request WHERE status = ? AND reminder = ? AND date = ? AND (firstHour = ? OR firstHour IS NULL)",
			"CONFIRMED", "WAITING", $dateNow, $hourNow
		);

		if(!isset($result1) || $result1->getRowCount() < 1)
		{
			if(SELF::CRON_DEBOUT) echo "WARNING: No records found.";
			return false;
		}

		foreach($result1->fetchAll() as $row)
		{
			$firstHour = $row['firstHour'];
			$firstMinute = $row['firstMinute'];

			// If the "firstHour" is NULL, then tries to prepare its value from the "units" column
			if(!isset($firstHour))
			{
				$firstHour = $this->calendar->getReservationFirstHour($row['units']);
			}

			// Checks "firstHour" range, or issues an error
			if($firstHour >= 24 || $firstHour < 0)
			{
				if(SELF::CRON_DEBOUT) echo "ERROR: The 'firstHour' parameter (inherid from 'units') is invalid in reservation_request table, ID: " . $row['id'] . "; SKIPPED.\n";
				$this->database->query("UPDATE reservation_request SET", ["reminder" => "ERROR"], "WHERE id = ?", $row['id']); // REMINDER STATUS -> ERROR
				continue;
			}

			// Checks if we got right hour to send message, otherwise issues a WARNING and jump to the next row
			if($firstHour != $hourNow)
			{
				if(SELF::CRON_DEBOUT) echo "WARNING: The 'firstHour' (" . $firstHour . ") parameter is not equal to 'hourNow' (" . $hourNow . "), ID: " . $row['id'] . "; SKIPPED.\n";
				//$this->database->query("UPDATE reservation_request SET", ["reminder" => "ERROR"], "WHERE id = ?", $row['id']); // REMINDER STATUS -> ERROR
				continue;
			}

			// Obtains the customer's phone number from the DB
			$result2 = $this->database->query("SELECT phone FROM customer WHERE id = ?", $row['customerID']);

			// Customer not found, or the result returned two or more rows
			if(!$result2 || $result2->getRowCount() != 1)
			{
				if(SELF::CRON_DEBOUT) echo "ERROR: Customer data not found, ID: " . $row['id'] . ", customerID: " . $row['customerID'] . "; SKIPPED.\n";
				$this->database->query("UPDATE reservation_request SET", ["reminder" => "ERROR"], "WHERE id = ?", $row['id']); // REMINDER STATUS -> ERROR
				continue;
			}

			// Prepare SMS data
			$customerPhone = $result2->fetch()['phone'];
			$reminderMessage = "Vase rezervace VRko.cz zacina dnes " . $this->getCzechHourNouns($firstHour, $firstMinute) . ". Prosime, dorazte alespon 5 minut predem. Dekujeme.";

			// UPDATE REMINDER STATUS -> SENT
			$this->database->query("UPDATE reservation_request SET", ["reminder" => "SENT"], "WHERE id = ?", $row['id']);

			// SEND SMS
			$this->smsbrana->sendSMS($customerPhone, $reminderMessage);

			// Done message (debug only)
			if(SELF::CRON_DEBOUT) echo "SENT_OK: ID: " . $row['id'] . ", customerID: " . $row['customerID'] . ", Hour: " . $firstHour . "; SUCCESS.\n";
		}

		return true;
	}

	private function getCzechHourNouns($hour, $minute = 0)
	{
		// V:  [0,1,      5,6,7,8,9,10,11,         15,16,17,18,19,              ] * Default
		// VE: [    2,3,4,                12,13,14,               20,21,22,23,24] (maybe set this as default?)
		$v = "v";
		if(in_array($hour, [2,3,4,12,13,14,20,21,22,23,24])) $v = "ve";

		$minute = ($minute < 10 ? "0" : "") . (string)$minute;

		//return ['v' => $v,'h' => $h];
		return $v . " " . $hour . ":" . $minute;
	}

	private function cronTrigger_VOUCHERFAKE($count = 0)
	{
		// TODO: Get voucher price from the global config sorage / DB (?)
		$voucherPrice = 250;

		// CREATE ORDER									  PRICE TOTAL,  COUNT,    NAME,   SURNAME,          EMAIL,           PHONE
		$orderId = $this->voucher->createOrder($voucherPrice * $count, $count, "Filip", "Koželuh", "info@vrko.cz", "+420608284446");

		// COMPLETE (without payment process)
		if(isset($orderId) && $this->voucher->completeOrder($orderId))
		{
			if(SELF::CRON_DEBOUT) echo "Vouchers has been created. Amount: " . $count . ", Order ID: " . $orderId;
			return true;
		}
		else
		{
			if(SELF::CRON_DEBOUT) echo "An error occurred while completing the order. Amount: " . $count . ", Order ID: " . $orderId;
			return false;
		}
	}

	public function actionTesting()
	{
		$filename = "sitemap-test.xml";
		$baseUrl = $this->template->baseUrl;

		$sitemap = new Sitemap(__DIR__ . "/" . $filename);
		$sitemap->addItem($baseUrl . "/mylink1");
		$sitemap->addItem($baseUrl . "/mylink2", time());
		$sitemap->addItem($baseUrl . "/mylink3", time(), Sitemap::HOURLY);
		$sitemap->addItem($baseUrl . "/mylink4", time(), Sitemap::DAILY, 0.3);
		$sitemap->write();

		// OUTPUT (DEBUG)
		header("Content-Type: application/xml");
		echo file_get_contents(__DIR__ . "/" . $filename);

		$this->terminate();
	}
}

<?php

declare(strict_types=1);

namespace App\Model\Reviews;

use Latte;
use Nette;
use App\Model;

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


class Reviews
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Nette\Mail\Mailer @inject */
	public $mailer;

	/** @var bool */
	private $request_debug;

	/** @var string */
	private $report_email;

	/** @var string */
	private $google_review_url;

	/** @var string */
	private $positive_url;

	/** @var string */
	private $negative_url;

	public function __construct($request_debug, $report_email, $google_review_url, $positive_url, $negative_url,
								Explorer $database, Mail\Mailer $mailer)
	{
		$this->request_debug = $request_debug;
		$this->report_email = $report_email;
		$this->google_review_url = $google_review_url;
		$this->positive_url = $positive_url;
		$this->negative_url = $negative_url;

		$this->database = $database;
		$this->mailer = $mailer;
	}

	// ######################################################

	/** Unique HASH Generator (returns NULL after 10 attempts)
	 * @param	integer			$size
	 *
	 * @return	string|NULL
	 */
	private function getUniqueHash($size = 32)
	{
		$randomCode = NULL;
		$counter = 0;
		$limit = 10;

		for ($counter; $counter < $limit; $counter++) {
			$randomCode = Random::generate($size, '0-9a-z');
			$result = $this->database->query('SELECT * FROM reviews WHERE hash = ? LIMIT 1', $randomCode);
			if (!isset($result) || $result->getRowCount() == 0) {
				break;
			}
		}

		return ($counter == $limit) ? NULL : $randomCode;
	}

	// ######################################################

	/** Create the Review Request
	 * @param	Carbon			$cReservationDate
	 * @param	string			$reservationUnits
	 * @param	string			$customerName
	 * @param	string			$customerEmail
	 * @param	string			$customerPhone
	 * @param	int				$reservationId
	 *
	 * @return	bool|NULL
	 */
	public function createRequest(Carbon $cReservationDate, $reservationUnits, $customerName, $customerEmail, $customerPhone, $reservationId = NULL)
	{
		$sReservationDate = $cReservationDate->format("Y-m-d");
		$triggerDay = $cReservationDate->addDay(1)->format("Y-m-d");

		$queryData = [
		//	'id'			=> 0,						// UID
		//	'createdDate'	=> NULL,					// Datum vytvoreni (handled by DB)
			'triggerDay'	=> $triggerDay,				// Den, kdy se ma odeslat Email (YYYY-MM-DD)
			'answerDate'	=> NULL,					// Datum odpovedi (NULL)
			'hash'			=> $this->getUniqueHash(),	// Hash (Review URL)
			'email'			=> $customerEmail,			// Email
			'status'		=> "WAITING",				// Stav ('WAITING','SENT','EXPIRED','COMPLETED','GMAPS')
			'data'			=> json_encode([			// Data (objednane hodiny a jednotky, datum, jmeno, prijmeni, email, telefon)
				'date'	=> $sReservationDate,
				'units'	=> $reservationUnits,
				'name'	=> $customerName,
				'email'	=> $customerEmail,
				'phone'	=> $customerPhone,
			]),
			'reservationId'	=> $reservationId,			// ID Rezervace
		//	'review'		=> NULL,					// Hodnoceni
		];

		$result = $this->database->query('INSERT INTO reviews', $queryData);

		return true;
	}

	/** Send All Review Requests (daily CRON job)
	 * @return	bool|NULL
	 */
	public function sendRequests()
	{
		$triggerDay = Carbon::now('Europe/Prague')->format("Y-m-d");
		//$sReservationDate = Carbon::now('Europe/Prague')->subDay()->format("d.m.Y");

		if ($this->request_debug) {
			$result = $this->database->query('SELECT * FROM reviews WHERE id = ? AND status = ?', 2, "WAITING"); // DEBUG
		} else {
			$result = $this->database->query('SELECT * FROM reviews WHERE triggerDay = ? AND status = ?', $triggerDay, "WAITING"); // PRODUCTION
		}

		if ($result && $result->getRowCount() > 0) {
			foreach ($result->fetchAll() as $row) {
				$reviewData = json_decode($row['data'], true);

				$data = [
				//	'date'			=> $sReservationDate,
				//	'date'			=> $reviewData['date'],
					'date'			=> Carbon::createFromFormat('Y-m-d', $reviewData['date'])->format("d.m.Y"),
					'reviewUrlPOS'	=> $this->positive_url . $row['hash'],
					'reviewUrlNEG'	=> $this->negative_url . $row['hash'],
					'email'			=> $row['email'],
				];

				if ($this->request_debug) {
					$this->sendMail($this->report_email, "@reviewRequestEmail", "Hodnocení zážitku VRko.cz", $data); // DEBUG
				} else {
					$this->sendMail($row['email'], "@reviewRequestEmail", "Hodnocení zážitku VRko.cz", $data); // PRODUCTION
				}

				$this->database->query('UPDATE reviews SET', [
					'status'	=> "SENT",
				], 'WHERE id = ?', $row['id']);
			}
		}
	}

	/** Get Review Email
	 * @param	string			$hash
	 *
	 * @return	string|NULL
	 */
	public function getReviewEmail($hash)
	{
		//$result = $this->database->query('SELECT * FROM reviews WHERE hash = ? AND status = ? LIMIT 1', $hash, "SENT");
		$result = $this->database->query('SELECT * FROM reviews WHERE hash = ? AND (status = ? OR status = ?) LIMIT 1', $hash, "SENT", "GMAPS");

		if ($result && $result->getRowCount() == 1) {
			$row = $result->fetch();
			return $row['email'];
		}

		return NULL;
	}

	/** Google Review Handler (just redirect...)
	 * @param	string			$hash
	 *
	 * @return	string|NULL
	 */
	public function googleReview($hash)
	{
		if (!Validators::is($hash, 'string:32')) {
			return NULL;
		}

		//$result = $this->database->query('SELECT * FROM reviews WHERE hash = ? AND status = ? LIMIT 1', $hash, "SENT");
		$result = $this->database->query('SELECT * FROM reviews WHERE hash = ? AND (status = ? OR status = ?) LIMIT 1', $hash, "SENT", "GMAPS");

		if ($result && $result->getRowCount() == 1) {
			$row = $result->fetch();

			$this->database->query('UPDATE reviews SET', [
				'status'	=> "GMAPS", // POSITIVE
			], 'WHERE id = ?', $row['id']);

			//$this->sendMail($this->report_email, "@reviewReportEmail", "Hodnocení [#".$row['id']."]", $reportData);
			return $this->google_review_url;
		}

		return NULL;
	}

	/** Saves the Review (after completing the review by the customer)
	 * @param	string			$hash
	 * @param	string			$review
	 *
	 * @return	bool
	 */
	public function saveReview($hash, $review)
	{
		if (!Validators::is($hash, 'string:32') || !Validators::is($review, 'string:1..500')) {
			return false;
		}

		//$result = $this->database->query('SELECT * FROM reviews WHERE hash = ? AND status = ? LIMIT 1', $hash, "SENT");
		$result = $this->database->query('SELECT * FROM reviews WHERE hash = ? AND (status = ? OR status = ?) LIMIT 1', $hash, "SENT", "GMAPS");

		if ($result && $result->getRowCount() == 1) {
			$row = $result->fetch();

			$this->database->query('UPDATE reviews SET', [
				'status'	=> "COMPLETED", // NEGATIVE
				'review'	=> $review,
			], 'WHERE id = ?', $row['id']);

			$reviewData = json_decode($row['data'], true);

			$this->sendMail($this->report_email, "@reviewReportEmail", "Hodnocení ID: " . $row['id'], [
				'id'		=> $row['id'],
				'date'		=> $reviewData['date'],
				'units'		=> var_export($reviewData['units'], true),
				'name'		=> $reviewData['name'],
				'email'		=> $reviewData['email'],
				'phone'		=> $reviewData['phone'],
				'review'	=> $review,
			]);

			return true;
		}

		return false;
	}

	// ### SEND MAIL ###
	private function sendMail($recipient, $templateName, $subject, $data)
	{
		$latte = new Latte\Engine;
		$template = $latte->renderToString(__DIR__ . "/" . $templateName . ".latte", $data);

		$mailMsg = new Mail\Message();
		$mailMsg->setFrom("Hodnocení VRko.cz <info@vrko.cz>");
		$mailMsg->addTo($recipient); // TODO: EMAIL Validator: $recipient
		$mailMsg->setSubject($subject);
		$mailMsg->setHtmlBody($template, __DIR__ . "/../../../www/img/email/");

		$this->mailer->send($mailMsg);
	}
}

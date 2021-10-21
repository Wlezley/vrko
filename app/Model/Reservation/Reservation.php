<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

declare(strict_types=1);

namespace App\Model;

use Latte;
use Nette;
use App\Model;
use Nette\Utils\Json;
use Nette\Utils\Random;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;
use Nette\Database\Explorer;
use Tracy\Debugger;
use Carbon\Carbon;
use Nette\Mail;
use Nette\Application\UI\ITemplateFactory;


class Reservation
{
	// DEBUG MODE
	protected static $_DEBUG_ = false;

	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Dotykacka\DotykackaApi2 */
	protected $doty2;

	/** @var Model\SmsBrana\SmsBrana */
	protected $smsbrana;

	/** @var Ecomail\EcomailApi */
	protected $ecomail;

	/** @var Model\Reviews\Reviews */
	protected $reviews;

	/** @var Nette\Mail\Mailer @inject */
	protected $mailer;

	/** @var Model\Reservation\ReservationOccupancy @inject */
	public $reservationOccupancy;

	/** @var Model\Reservation\ReservationRender @inject */
	public $reservationRender;

	/** @var Model\Reservation\ReservationRequest @inject */
	public $reservationRequest;

	/** @var Model\Reservation\ReservationSlots @inject */
	public $reservationSlots;

	/** @var Model\Reservation\ReservationUnits @inject */
	public $reservationUnits;

	public function __construct(Explorer $database,
								Dotykacka\DotykackaApi2 $doty2,
								SmsBrana\SmsBrana $smsbrana,
								Reviews\Reviews $reviews,
								Ecomail\EcomailApi $ecomail,
								Mail\Mailer $mailer)
	{
		$this->database = $database;
		$this->doty2 = $doty2;
		$this->smsbrana = $smsbrana;
		$this->reviews = $reviews;
		$this->ecomail = $ecomail;
		$this->mailer = $mailer;
	}

	protected $monthNamesShort = [
		1 => "Led",
		2 => "Úno",
		3 => "Bře",
		4 => "Dub",
		5 => "Kvě",
		6 => "Čer",
		7 => "Čvc",
		8 => "Srp",
		9 => "Zář",
		10 => "Říj",
		11 => "Lis",
		12 => "Pro"
	];

	protected $monthNamesFull = [
		1 => "Leden",
		2 => "Únor",
		3 => "Březen",
		4 => "Duben",
		5 => "Květen",
		6 => "Červen",
		7 => "Červenec",
		8 => "Srpen",
		9 => "Září",
		10 => "Říjen",
		11 => "Listopad",
		12 => "Prosinec"
	];

	protected $monthNamesOrdinal = [
		1 => "Ledna",
		2 => "Února",
		3 => "Března",
		4 => "Dubna",
		5 => "Května",
		6 => "Června",
		7 => "Července",
		8 => "Srpna",
		9 => "Září",
		10 => "Října",
		11 => "Listopadu",
		12 => "Prosince"
	];

	protected $dayNamesShort = [
		0 => "NE",
		1 => "PO",
		2 => "ÚT",
		3 => "ST",
		4 => "ČT",
		5 => "PÁ",
		6 => "SO",
		7 => "NE"
	];

	protected $dayNamesFull = [
		0 => "Neděle",
		1 => "Pondělí",
		2 => "Úterý",
		3 => "Středa",
		4 => "Čtvrtek",
		5 => "Pátek",
		6 => "Sobota",
		7 => "Neděle"
	];

	/** Get Alphabet array (upper case) */
	public function getAZ(): array
	{
		return range('A', 'Z');
	}

	/** Get Alphabet character (upper case) by ID (A == 0) */
	public function getAZbyID(int $id): string
	{
		return range('A', 'Z')[$id];
	}

	/** Get ID (0 == A) by Alphabet character (upper case) */
	public function getIDbyAZ(string $az): int
	{
		return array_search(strtoupper($az), range('A', 'Z'));
	}

	/** Get MONTH Name / SHORT */
	public function getMonthNameShort(int $month): string
	{
		return (Validators::is($month, 'numericint:1..12') ? $this->monthNamesShort[$month] : "UNK");
	}

	/** Get MONTH Name / FULL */
	public function getMonthNameFull(int $month): string
	{
		return (Validators::is($month, 'numericint:1..12') ? $this->monthNamesFull[$month] : "UNKNOWN");
	}

	/** Get MONTH Name / ORDINAL */
	public function getMonthNameOrdinal(int $month): string
	{
		return (Validators::is($month, 'numericint:1..12') ? $this->monthNamesOrdinal[$month] : "UNKNOWN");
	}

	/** Get DAY Name / SHORT */
	public function getDayNameShort(int $day): string
	{
		return (Validators::is($day, 'numericint:0..7') ? $this->dayNamesShort[$day] : "UNK");
	}

	/** Get DAY Name / FULL */
	public function getDayNameFull(int $day): string
	{
		return (Validators::is($day, 'numericint:0..7') ? $this->dayNamesFull[$day] : "UNKNOWN");
	}

	/** Get Today Date as Array ['year','month','day','hour','minute','second'] */
	public function getDateNowArray(): array
	{
		$now = Carbon::now();

		return [
			'year'		=> $now->year,
			'month'		=> $now->month,
			'day'		=> $now->day,
			'hour'		=> $now->hour,
			'minute'	=> $now->minute,
			'second'	=> $now->second,
		];
	}

	/** Generate Random AuthCode (SMS) */
	public function getRandomAuthCode(int $size = 4, int $runLimit = 10): string
	{
		$errorCode = str_repeat('9', $size);

		for($i = 0; $i < $runLimit; $i++) {

			$randomCode = Random::generate($size, '0-9');

			if($randomCode == $errorCode) {
				continue;
			}

			$result = $this->database->query('SELECT * FROM reservation_request WHERE created > NOW() - INTERVAL 15 MINUTE AND status = ? AND authCode = ? LIMIT 1', 'NEW', $randomCode);

			if(!isset($result) || $result->getRowCount() == 0) {
				return (string)$randomCode;
			}
		}

		return $errorCode;
	}

	/** Color Converter: HSL (hue, saturation, luminiscence) to RGB Array ['r','g','b'] */
	protected function ColorHSL2RGB(float $h, float $s, float $l): array
	{
		$r = $l;
		$g = $l;
		$b = $l;
		$v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);

		if ($v > 0) {
			$m = $l + $l - $v;
			$sv = ($v - $m ) / $v;
			$h *= 6.0;
			$sextant = floor($h);
			$fract = $h - $sextant;
			$vsf = $v * $sv * $fract;
			$mid1 = $m + $vsf;
			$mid2 = $v - $vsf;

			switch ($sextant) {
				case 0: $r = $v;	$g = $mid1;	$b = $m;	break;
				case 1: $r = $mid2;	$g = $v;	$b = $m;	break;
				case 2: $r = $m;	$g = $v;	$b = $mid1;	break;
				case 3: $r = $m;	$g = $mid2;	$b = $v;	break;
				case 4: $r = $mid1;	$g = $m;	$b = $v;	break;
				case 5: $r = $v;	$g = $m;	$b = $mid2;	break;
			}
		}

		return [
			'r' => $r * 255.0,
			'g' => $g * 255.0,
			'b' => $b * 255.0
		];
	}

	/** Get HEX Color (#RRGGBB) by Percentil (value, low treshold, high treshold) */
	protected function getColorByPercentil(float $value, float $min = 0, float $max = 0.5): string
	{
		$ratio = $value;

		if ($min > 0 || $max < 1) {
			if ($value < $min) {
				$ratio = 1;
			}
			else if ($value > $max) {
				$ratio = 0;
			}
			else {
				$range = $min - $max;
				$ratio = ($value - $max) / $range;
			}
		}

		$hue = ($ratio * 1.2) / 3.60;
		$rgb = $this->ColorHSL2RGB($hue, 1, 0.5);

		return sprintf("#%02X%02X%02X",
			(int)round($rgb['r'], 0),
			(int)round($rgb['g'], 0),
			(int)round($rgb['b'], 0)
		);
	}
}

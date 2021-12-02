<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model;
use App\Model\Reservation;

use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;
use Nette\Database\Explorer;
use Tracy\Debugger;

use Carbon\Carbon;


class PartialsPresenter extends BasePresenter
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Reservation\Calendar */
	private $calendar;

	/** @var mixed */
	private $referer_check;

	public function __construct($referer_check,
								Explorer $database,
								Reservation\Calendar $calendar)
	{
		Debugger::$showBar = false; // Disable Tracy Debug Bar

		$this->referer_check = $referer_check;
		$this->database = $database;
		$this->calendar = $calendar;
	}

	public function renderDefault()
	{
		$this->template->error = true;
		header("HTTP/1.0 404 Not Found");
		return false;

		/* DEBUG: Check input date range
		$startDate = Carbon::now()->startOfMonth()->startOfDay();		// NOW DATE
		$endDate = Carbon::now()->addMonth()->endOfMonth()->endOfDay();	// END DATE

		bdump($startDate, "START DATE");
		bdump($endDate, "END DATE");

		for ($y = 2021; $y <= 2022; $y++) {
			echo "### ### ### " . $y . " ### ### ###<br>";
			for ($m = 1; $m <= 12; $m++) {
				echo "[ " . $y . " / " . $m . " ]<br>";
				$daysInMonth = Carbon::create($y, $m, 1)->endOfMonth()->startOfDay()->day;

				for ($d = 1; $d <= $daysInMonth; $d++) {
					$selDate = Carbon::create($y, $m, $d)->endOfDay(); // SELECTED

					echo $y . "-" . $m . "-" . $d . ": ";
					if($selDate < $startDate || $selDate > $endDate)
					{
						echo "ERROR!";
					} else {
						echo "PASSED";
					}
					echo "<br>";
				}
				echo "<br>";
			}
			echo "<br>";
		}*/
	}

	public function renderSelectday($year, $month) // TODO: Just parse DATE from one $string
	{
		$this->template->error = true; // Error Handler (Default: true)

		// Check input DATE before sending to major functions (Memory overflow protection)
		if (!Validators::is($year, 'numericint:' . (Carbon::now()->year + 0) . '..' . (Carbon::now()->year + 1)) ||
			!Validators::is($month, 'numericint:1..12'))
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// Check input date range
		$selectedDate = Carbon::create($year, $month, 1);
		$startDate = Carbon::now()->startOfMonth()->startOfDay();
		$endDate = Carbon::now()->addMonth()->endOfMonth()->endOfDay();
		if($selectedDate < $startDate || $selectedDate > $endDate)
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// Referer check
		if ($this->referer_check !== false && (
			empty($_SERVER['HTTP_REFERER']) || 
			empty(parse_url($_SERVER['HTTP_REFERER'])) || 
			parse_url($_SERVER['HTTP_REFERER'])['host'] !== $this->referer_check))
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// Render data
		$renderData = $this->calendar->getRenderData_Selectday($year, $month);
		if(!$renderData)
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// No error anymore...
		$this->template->error = false;

		$this->template->monthName	= $renderData['monthName'];
		$this->template->dayNames	= $renderData['dayNamesShort'];
		$this->template->pagination	= $renderData['pagination'];
		$this->template->now = $this->calendar->getDateNowArray();
		$this->template->calMonthPage = $renderData['calMonthPage'];

		// LEGEND
		$this->template->palette = $renderData['palette'];

		// NAVIGATOR
		$this->template->navigator = [
			"prev" => ($year == Carbon::now()->addMonth()->year && $month == Carbon::now()->addMonth()->month),
			"next" => ($year == Carbon::now()->year && $month == Carbon::now()->month),
		];

		// DEBUG
		$this->template->debug = "N/A";
	}

	public function renderSelectunit($year, $month, $day) // TODO: Just parse DATE from one $string
	{
		$this->template->error = true; // Error Handler (Default: true)

		// Check input DATE before sending to major functions (Memory overflow protection)
		if (!Validators::is($year, 'numericint:' . (Carbon::now()->year + 0) . '..' . (Carbon::now()->year + 1)) ||
			!Validators::is($month, 'numericint:1..12') ||
			!Validators::is($day, 'numericint:1..' . Carbon::create($year, $month, 1)->endOfMonth()->day))
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// Check input date range
		$selectedDate = Carbon::create($year, $month, $day);
		$startDate = Carbon::now()->startOfDay();
		$endDate = Carbon::now()->addMonth()->endOfMonth()->endOfDay();
		if($selectedDate < $startDate || $selectedDate > $endDate)
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// Referer check
		if ($this->referer_check !== false && (
			empty($_SERVER['HTTP_REFERER']) || 
			empty(parse_url($_SERVER['HTTP_REFERER'])) || 
			parse_url($_SERVER['HTTP_REFERER'])['host'] !== $this->referer_check))
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// Render data
		$renderData = $this->calendar->getRenderData_Selecthour($year, $month, $day);
		if(!$renderData)
		{
			header("HTTP/1.0 404 Not Found");
			return false;
		}

		// No error anymore...
		$this->template->error = false;

		$this->template->year		= (int)$year;
		$this->template->month		= (int)$month;
		$this->template->day		= (int)$day;

		$this->template->monthName	= $renderData['monthName'];
		//$this->template->dayName	= $renderData['dayName'];

		$this->template->occupancyData = $renderData['occupancyData'];
		//$this->template->unitsData = $this->calendar->getUnitsData();

		$this->template->unitsData = [];
		foreach ($this->calendar->getUnitsData() as $key => $unit) {
			$unitStringID = $unit['hourBegin'] . $unit['minuteBegin'];

			$this->template->unitsData[$unitStringID][$unit['unitID']] = $unit;
		}

		// DEBUG
		$this->template->debug = "N/A";
	}
}

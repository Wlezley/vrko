<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

declare(strict_types=1);

namespace App\Model\Reservation;

use App\Model\Reservation;
use Carbon\Carbon;


class ReservationOccupancy extends Reservation
{
	public function __construct()
	{
	}

	/** RENDER Occupancy data for day
	 * @param	int				$year
	 * @param	int				$month
	 * @param	int				$day
	 * 
	 * @return	array			$units
	 */
	public function renderOccupancy(int $year, int $month, int $day): array
	{
		if (!$this->checkDate($year, $month, $day)) {
			return [];
		}

		$units = array();
		$date = (string)sprintf("%4d-%02d-%02d", (int)$year, (int)$month, (int)$day);

		// Time machine prevention
		$hourLimit = Carbon::now(parent::$_TIMEZONE_)->addMinute(5)->hour + 1;
		$dateRes = Carbon::create((int)$year, (int)$month, (int)$day)->setTimezone(parent::$_TIMEZONE_)->startOfDay();
		$dateNow = Carbon::now(parent::$_TIMEZONE_)->startOfDay();
		$today = ($dateNow == $dateRes) ? true : false;

		$reservationUnits = new ReservationUnits();

		// Units array prepare
		foreach ($reservationUnits->getUnitsData() as $item) {
			$unitName = $item->hour . $this->getAZbyID($item->unitID);
			$units[$unitName] = ($today && $item->hour < $hourLimit) ? 1 : 0;
		}

		if (empty($units)) {
			return [];
		}

		// Get all reservations for day
		//$resultR = $this->database->query('SELECT units FROM reservation WHERE date = ? AND (state = ? OR state = ?) ORDER BY id ASC', $date, 'new', 'active');
		$resultR = $this->database->query('SELECT units FROM reservation WHERE date = ? LIMIT 1', $date);
		if ($resultR && $resultR->getRowCount() === 1) {
			foreach ($resultR->fetchAll() as $unitString) {
				foreach (json_decode($unitString->units) as $unitName) {
					$units[$unitName] = 1;
				}
			}
		}

		// Update occupancy fields
		$this->database->query('REPLACE INTO occupancy', [
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
	 * @return	array
	 */
	public function getOccupancy(int $year, int $month, int $day): array
	{
		if (!$this->checkDate($year, $month, $day)) {
			return [];
		}

		$date = (string)sprintf("%4d-%02d-%02d", (int)$year, (int)$month, (int)$day);

		$result = $this->database->query('SELECT occupancyData FROM occupancy WHERE date = ?', $date);
		if ($result && $result->getRowCount() > 0) {
			return json_decode($result->fetch()->occupancyData);
		}
		else {
			return $this->renderOccupancy($year, $month, $day);
		}
	}
}
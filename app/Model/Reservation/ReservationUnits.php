<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

declare(strict_types=1);

namespace App\Model\Reservation;

use App\Model\Reservation;
use Nette\Database\Row;

class ReservationUnits extends Reservation
{
	public function __construct()
	{
	}

	/** Get UNITS Data
	 * @return	Row[]			$unitsData[...]
	 */
	public function getUnitsData(): array
	{
		$result = $this->database->query('SELECT * FROM units ORDER BY hour ASC, unitID ASC');

		return ($result && $result->getRowCount() > 0) ? $result->fetchAll() : null;
	}

	/** Get UNITS Count - Total
	 * @return	int				$unitsTotal
	 */
	public function getUnitsCountTotal(): int
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
	public function getUnitsCount(int $year, int $month, int $day): array
	{
		if (!$this->checkDate($year, $month, $day)) {
			return [];
		}

		$total = $this->getUnitsCountTotal();
		$free = 0;
		$occupied = 0;

		$reservationOccupancy = new ReservationOccupancy($this->database);

		foreach ($reservationOccupancy->getOccupancy($year, $month, $day) as /*$unitName =>*/ $value) {
			switch ($value) {
				case 0:
					$free++;
					break;
				case 1:
					$occupied++;
					break;
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
	public function isUnitEnabled(string $unit): bool
	{
		if (strlen($unit) !== 3) {
			return false;
		}

		foreach ($this->getUnitsData() as $item) {
			$unitName = $item->hour . $this->getAZbyID($item->unitID);

			if ($unit === $unitName) {
				return true;
			}
		}

		return false;
	}

	/** Get Dotykacka TableID by Reservation UnitName
	 * @param	string			$unit		// Unit string (eg. '16A')
	 * 
	 * @return	string|null		$result
	 */
	public function getTableIdByUnitName(string $unit): string
	{
		if (strlen($unit) !== 3) {
			return null;
		}

		$tables = $this->doty2->getTables();

		foreach ($this->getUnitsData() as $item) {
			$unitName = $item->hour . $this->getAZbyID($item->unitID);

			if ($unit === $unitName && $item->unitID < count($tables)) {
				return $tables[$item->unitID];
			}
		}

		return null;
	}

	/** GET Reservation First Hour as int	
	 * @param	string			$units		// Units string (eg. '16A')
	 * 
	 * @return	int				$result		// 24 == ERROR
	 */
	public function getReservationFirstHour(string $units): int
	{
		$result = 24;

		foreach (json_decode($units) as $item) {
			$hour = (int)substr($item, 0, 2);

			if ($hour < $result) {
				$result = $hour;
			}
		}

		return (int)$result;
	}
}
<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

declare(strict_types=1);

namespace App\Model\Reservation;

use App\Model\Reservation;
use Nette\Utils\Validators;
use Carbon\Carbon;


class ReservationSlots extends Reservation
{
	public function __construct()
	{
	}

	/** DOTYKACKA: Checks if Reservation SLOT is FREE?
	 * @param	int|string		$_tableId		// ID Stolu
	 * @param	Carbon			$date			// Datum rezervace
	 * @param	int				$minutes		// Doba trvani rezervace (vychozi: 60 minut)
	 * 
	 * @return	bool			$isFree			// Je SLOT volny? (true: ano / false: ne)
	 */
	public function checkReservationSlot(mixed $_tableId, Carbon $date, int $minutes = 60): bool
	{
		// Validate _tableId
		if (!in_array($_tableId, $this->doty2->getTables())) {
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
		$sfpl = $this->doty2->translateSFPL("", $filter, 1, 100);

		// Get result from DTK API v2
		$result = $this->doty2->getReservationList($sfpl);

		if (!empty($result)) {
			if (empty($result->data)) {
				return false; // ERROR ?! CATCH THE ERROR MESSAGES [ !! HERE !! ]
			}

			foreach ($result->data as $reservation) {
				if ($reservation->status != 'CANCELLED') {
					return false; // FOUND & NOT CANCELLED == NOT FREE
				}
			}
		}

		return true; // NOT FOUND OVER ALL == IS FREE
	}

	/** DOTYKACKA: Prepare SLOT data for the Reservation
	 * @param	int|string		$_customerId	// ID Zakaznika
	 * @param	int|string		$_tableId		// ID Stolu
	 * @param	int				$year			// Rok
	 * @param	int				$month			// Mesic
	 * @param	int				$day			// Den
	 * @param	int				$hour			// Hodina
	 * @param	string			$note			// Poznamka
	 * 
	 * @return	array|bool		$result			// Data pro vytvoreni polozky v Dotykacce (nejsou-li data == FALSE)
	 */
	public function prepareReservationSlot(mixed $_customerId, mixed $_tableId, int $year, int $month, int $day, int $hour, string $note = ""): mixed
	{
		if (!$this->checkDate($year, $month, $day)) {
			return false;
		}

		// Construct DATE
		$minutes	= 60;
		$startDate	= Carbon::create($year, $month, $day, $hour, 0, 0);
		$endDate	= Carbon::create($year, $month, $day, $hour, 0, 0)->addMinutes($minutes);
		
		if ($this->checkReservationSlot($_tableId, $startDate, $minutes) == false) {
			return false;
		}

		//ReservationSchema($tableId, $seats, $startDate, $endDate, $customerId = 0, $employeeId = 0, $note = "", $flags = 0, $status = 'CONFIRMED');
		$result = $this->doty2->ReservationSchema(
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

		return $result;
	}

	/** DOTYKACKA: Synchronize Reservations Database (DOTY -> DB)
	 * @param	int				$year			// Rok
	 * @param	int				$month			// Mesic
	 * @param	int				$day			// Den
	 * 
	 * @return	bool
	 */
	public function syncReservationsByDay(int $year, int $month, int $day): bool
	{
		if (!$this->checkDate($year, $month, $day)) {
			return false;
		}

		$tableString = implode(",", $this->doty2->getTables());

		// Construct DATE
		$startDate	= Carbon::create($year, $month, $day, 0, 0, 0);
		$endDate	= Carbon::create($year, $month, $day, 0, 0, 0)->addDay();

		$filter = "_tableId|in|" . $tableString . ";"
				. "startDate|gteq|" . $startDate->toIso8601ZuluString() . ";"
				. "startDate|lt|" . $endDate->toIso8601ZuluString();

		$sfpl = $this->doty2->translateSFPL("startDate", $filter, 1, 100);
		$reservationList = $this->doty2->getReservationList($sfpl);

		$units = [];
		$reservations = [];

		if (!empty($reservationList)) {
			foreach ($reservationList->data as $reservation) {
				if ($reservation->status == 'CANCELLED') {
					continue;
				}

				$reservations[] = (array)$reservation;
				$rsDate = Carbon::create($reservation->startDate /*, 'UTC'*/)->setTimezone(parent::$_TIMEZONE_);

				$tableChar = $this->getAZbyID(array_search($reservation->_tableId, $this->doty2->getTables()));
				$units[] = $rsDate->hour . $tableChar;
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

		return true; //$unitsString;
	}
}
<?php

// In strict mode, only a variable of exact type of the type
// declaration will be accepted, or a TypeError will be thrown.

declare(strict_types=1);

namespace App\Model\Reservation;

use App\Model\Reservation;
use Nette\Utils\Validators;
use Carbon\Carbon;


class ReservationRender extends Reservation
{
	public function __construct()
	{
	}

	/** Get RenderData for SELECT-DAY
	 * @param	int				$year
	 * @param	int				$month
	 * 
	 * @return	array			$renderData[...]
	 */
	public function getRenderData_Selectday(int $year, int $month): array
	{
		if (!$this->checkDate($year, $month)) {
			return [];
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
			$unitsCount = $this->reservationUnits->getUnitsCount($calendar->year, $calendar->month, $calendar->day);
			$disabled = ($calendar->year <= $dateYesterday->year && $calendar->month <= $dateYesterday->month && $calendar->day <= $dateYesterday->day);
			$isToday = (Carbon::now()->year == $calendar->year && Carbon::now()->month == $calendar->month && Carbon::now()->day == $calendar->day);
			$bgColor = $this->getColorByPercentil($unitsCount['free'] / $unitsCount['total'], 0, 1);

			// RESERVATION_FAKE
			if ($disabled) {
				$randomFree = (int)rand(0, $unitsCount['total'] - 8);
				$dbDateString = $calendar->year."-".$calendar->month."-".$calendar->day;

				$result = $this->database->query('SELECT * FROM reservation_fake WHERE date = ? LIMIT 1', $dbDateString);
				if ($result && $result->getRowCount() == 1) {
					$randomFree = (int)$result->fetch()['randomFree'];
				}
				else {
					$this->database->query('REPLACE INTO reservation_fake', ['date' => $dbDateString, 'randomFree' => $randomFree]);
				}

				$bgColor = $this->getColorByPercentil($randomFree / $unitsCount['total'], 0, 1);
			}
			else if ($isToday) {
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
		$seatsTotal = $this->reservationUnits->getUnitsCountTotal();
		for ($p = $seatsTotal; $p >= 0; $p--) {
			$palette[] = $this->getColorByPercentil($p / $seatsTotal, 0, 1);
		}

		$renderData = [
			//'now'			=> $this->getDateNowArray(),
			'pagination'	=> $pagination,
			'monthName'		=> $this->getMonthNameFull($month),
			'dayNamesShort'	=> $this->dayNamesShort,
			'calMonthPage'	=> $calMonthPage,
			'palette'		=> $palette,
		];

		if (isset(parent::$_DEBUG_) && parent::$_DEBUG_ !== true) {
			$renderData['debug'] = "N/A";
		}
		else {
			$renderData['debug'] = $this->_RENDER_DEBUG_DATA_();
		}

		return $renderData;
	}

	/** Get RenderData for SELECT-HOUR
	 * @param	int				$year
	 * @param	int				$month
	 * @param	int				$day
	 * 
	 * @return	array			$renderData[...]
	 */
	public function getRenderData_Selecthour(int $year, int $month, int $day): array
	{
		if (!$this->checkDate($year, $month, $day)) {
			return [];
		}

		// RESYNC Reservations
		$this->reservationSlots->syncReservationsByDay($year, $month, $day);

		$renderData = [
			'monthName'		=> $this->getMonthNameOrdinal($month),
			//'dayName'		=> $this->getDayNameFull($day),
			'occupancyData'	=> $this->reservationOccupancy->renderOccupancy($year, $month, $day),

			// DEBUG: DUMMY
			'debug'			=> "N/A",
		];

		return $renderData;
	}

	public function _RENDER_DEBUG_DATA_(): mixed
	{
		return "N/A";

		// CHECK RESERVATION SLOT
		//return $this->reservationSlots->checkReservationSlot($this->doty2->getTables()[0], Carbon::create(2021, 04, 16, 5, 0, 0));

		// PREPARE RESERVATION SLOT
		//return $this->reservationSlots->prepareReservationSlot(123456, 7890123, 2021, 4, 18, 3, 'Poznamka');

		// SYNC RESERVATIONS
		//return $this->reservationSlots->syncReservationsByDay(2021, 4, 18);

		// RESERVATION REQUEST (RAW)
		//return $this->reservationRequest->createReservationRequest_raw(2021, 4, 19, '["20B","21B","19C"]', 'Prymoš', 'Roglič', 'email@example.com', '+420123456789', false);

		// RESERVATION COMPLETE
		//return $this->reservationRequest->completeReservationRequest('0910');

		// GET CUSTOMER LIST
		//return $this->doty2->getCustomerList();

		// GET RESERVATION LIST
		//return $this->doty2->getReservationList();

		// TRY SEND SMS
		// $this->smsbrana->sendSMS("+420736168785", "Toto je testovaci zprava SMS.");

		// RESERVATION UNITS FIRST HOUR
		//return $this->reservationUnits->getReservationFirstHour('["18A","14A","20A"]');

		//return \base64_decode("CiAgICA8c3R5bGU+CiAgICAgICAgcCB7IG1hcmdpbi10b3A6IDI4cHg7IH0KICAgICAgICBhIHsgY29sb3I6IzE1YzsgfQogICAgPC9zdHlsZT4KCgo8ZGl2IHN0eWxlPSJ3aWR0aDogMTAwJTsgYmFja2dyb3VuZC1jb2xvcjogI2Y0ZjRmNDsiPgogICAgPGRpdiBzdHlsZT0iY29sb3I6ICMwMDAwMDA7IGZvbnQtZmFtaWx5OiBIZWx2ZXRpY2E7IGZvbnQtc2l6ZTogMTZweDsgbGluZS1oZWlnaHQ6IDI1cHg7IG1hcmdpbjogMCBhdXRvOyBtYXgtd2lkdGg6IDYwMHB4OyI+CiAgICAgICAgPGRpdiBzdHlsZT0icGFkZGluZzogMTVweCAyNXB4IDEwcHggMjVweDsiPgogICAgICAgICAgICA8aW1nIHNyYz0iaHR0cHM6Ly9wYXltZW50cy5jb21nYXRlLmN6L2Fzc2V0cy9pbWFnZXMvY2dsb2dvcHMxMTUucG5nIiBhbHQ9ImxvZ28gQ29tR2F0ZSIgdGl0bGU9ImxvZ28gQ29tR2F0ZSIvPgogICAgICAgIDwvZGl2PgogICAgICAgIDxkaXYgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IHBhZGRpbmc6IDI1cHg7Ij4KCiAgICAgICAgICAgIDxwIHN0eWxlPSJtYXJnaW4tdG9wOjBweDsiPgogICAgICAgICAgICAgICAgWiBvYmNob2R1IDxhIGhyZWY9Imh0dHA6Ly93d3cuVlJrby5jeiI+d3d3LlZSa28uY3o8L2E+IGpzbWUgcMWZaWphbGkgcG/FvmFkYXZlayBrIHByb3ZlZGVuw60gcGxhdGJ5PGJyIC8+dmUgdsO9xaFpIDxiPjQwMCwwMCBDWks8L2I+LgogICAgICAgICAgICA8L3A+CgogICAgICAgICAgICA8cD4KICAgICAgICAgICAgICAgIDx0YWJsZT4KICAgICAgICAgICAgICAgICAgICA8dGJvZHk+CiAgICAgICAgICAgICAgICAgICAgICAgIDx0cj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgIDx0ZCBzdHlsZT0icGFkZGluZzogMTBweCAxNXB4OyBiYWNrZ3JvdW5kLWNvbG9yOiAjMWE3M2U4OyBib3JkZXItcmFkaXVzOiA0cHg7ICI+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGEgaHJlZj0iaHR0cHM6Ly9wYXltZW50cy5jb21nYXRlLmN6L2NsaWVudC9pbnN0cnVjdGlvbnMvcGF5bWVudC1zdGF0dXMtaW5mby9pZC9CTk5BLUFXNVYtUUVZNy9oL0Z4eDFGdVlla1lWbUlrZXE5REdjM3FoTUtGc0dQYWllL3Jlc3RhcnQvMSIgc3R5bGU9ImZvbnQtd2VpZ2h0OiBib2xkOyBsZXR0ZXItc3BhY2luZzogbm9ybWFsOwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgbGluZS1oZWlnaHQ6IDEwMCU7IHRleHQtYWxpZ246IGNlbnRlcjsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsgY29sb3I6ICNmZmZmZmY7Ij5aamlzdGl0IHN0YXYgcGxhdGJ5PC9hPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPC90ZD4KICAgICAgICAgICAgICAgICAgICAgICAgPC90cj4KICAgICAgICAgICAgICAgICAgICA8L3Rib2R5PgogICAgICAgICAgICAgICAgPC90YWJsZT4KICAgICAgICAgICAgPC9wPgoKICAgICAgICAgICAgPHA+S2xpa251dMOtbSBuYSB0bGHEjcOtdGtvIG3Fr8W+ZXRlIHpqaXN0aXQgcG9kcm9ibm9zdGkgbyBzdGF2dSBwbGF0YnksIHDFmcOtcGFkbsSbIHp2b2xpdCBqaW5vdSBwbGF0ZWJuw60gbWV0b2R1LjwvcD4KCiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8cD5Qb2t1ZCBqacW+IHBsYXRiYSBwcm9ixJtobGEgYSBuZW3DoXRlIGluZm9ybWFjaSBvIHZ5xZnDrXplbsOtIHZhxaHDrSBvYmplZG7DoXZreSwga29udGFrdHVqdGUgb2JjaG9kbsOta2EgbmEgPGEgaHJlZj0ibWFpbHRvOmtvemVsdWhAemV0Y29tcC5jeiI+a296ZWx1aEB6ZXRjb21wLmN6PC9hPi48L3A+CiAgICAgICAgICAgIAogICAgICAgICAgICAKICAgICAgICAgICAgPHA+SUQgcGxhdGVibsOtIHRyYW5zYWtjZTogQk5OQS1BVzVWLVFFWTc8L3A+CgogICAgICAgICAgICA8cCBzdHlsZT0ibWFyZ2luLXRvcDogYXV0bzsgbWFyZ2luLWJvdHRvbTogMDsiPlRhdG8genByw6F2YSBqZSBnZW5lcm92w6FuYSBhdXRvbWF0aWNreS4gUHJvc8OtbWUsIG5lb2Rwb3bDrWRlanRlIG5hIG5pLjwvcD4KICAgICAgICA8L2Rpdj4KICAgICAgICA8ZGl2IHN0eWxlPSJwYWRkaW5nOiAyNXB4OyBmb250LXNpemU6IDEzcHg7IGNvbG9yOiAjNzU3NTc1OyI+CiAgICAgICAgICAgIDxwIHN0eWxlPSJtYXJnaW46IDAiPkNvbUdhdGUgUGF5bWVudHMsIGEucy4sIEdvxI3DoXJvdmEgdMWZw61kYSAxNzU0IC8gNDhiLCA1MDAgMDIgSHJhZGVjIEtyw6Fsb3bDqTxiciAvPgogICAgICAgICAgICBPc29ibsOtIMO6ZGFqZSB6cHJhY292w6F2w6FtZSA8YSBocmVmPSJodHRwczovL3d3dy5jb21nYXRlLmN6L2N6L29zb2JuaS11ZGFqZSI+cG9kbGUgdMSbY2h0byBwcmF2aWRlbDwvYT48L3A+CiAgICAgICAgPC9kaXY+CiAgICA8L2Rpdj4KPC9kaXY+");
	}
}

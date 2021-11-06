<?php

declare(strict_types=1);

namespace App\Forms;

use Nette;
use App\Model;
use App\Model\Reservation;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\Responses\JsonResponse;

use Nette\Utils\Json;
use Nette\Utils\Strings;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;

use Carbon\Carbon;


class ReservationFormFactory extends Control
{
	/** @var App\Model\Reservation\Calendar */
	private $calendar;

	/** @var callback */
	public $onUserSave;

	/** @var callback */
	public $onError;

	/** @var array */
	private $exchanger;

	public function __construct(Reservation\Calendar $calendar)
	{
		$this->calendar = $calendar;
	}

	// Data Exchanger
	public function getData() { return $this->exchanger; }
	public function setData($exchanger) { $this->exchanger = $exchanger; }

	protected function createComponentForm()
	{		
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		// INIT
		$form->addTextArea('reservation_date');
		$form->addTextArea('reservation_units')
			->setValue('[]');

		// KONTAKTNI UDAJE ---->>
		$form->addText('jmeno',								'Jméno')
			->setHtmlAttribute('placeholder',				'Jméno')
			->addRule(Form::MAX_LENGTH, 'JMÉNO: Maximálně 64 znaků.', 64)
			->setRequired('Položka "Jméno" je povinná.');

		$form->addText('prijmeni',							'Příjmení')
			->setHtmlAttribute('placeholder',				'Příjmení')
			->addRule(Form::MAX_LENGTH, 'PŘÍJMENÍ: Maximálně 64 znaků.', 64)
			->setRequired('Položka "Příjmení" je povinná.');

		$form->addText('email',								'Email')
			->setHtmlAttribute('placeholder',				'Email')
			->addRule(Form::EMAIL, 'Zadejte platný Email.')
			->addRule(Form::MAX_LENGTH, 'EMAIL: Maximálně 64 znaků.', 64)
			->setRequired('Položka "Email" je povinná.');

		$predvolby = [
			'+420' => '+420',
			'+421' => '+421',
		];
		$form->addSelect('predvolba', 'Předvolba:', $predvolby)
			->setDefaultValue('+420')
			->setRequired('Vyberte předvolbu.');

		$form->addText('telefon',							'Telefon')
			->setHtmlAttribute('placeholder',				'Telefon')
			->setHtmlType('tel')
			->addRule(Form::LENGTH, 'TELEFON: Je vyžadováno přesně 9 číslic. Předvolba ani mezery se nevyplňují.', 9)
			->addRule(Form::PATTERN, 'TELEFON: Může obsahovat pouze číslice.', '^[0-9]+$')
			->setRequired('Položka "Telefon" je povinná.');

		$form->addCheckbox('subscribe', ' Souhlasím se zasíláním e-mailových newsletterů.')
			->setDefaultValue(FALSE);

		$form->addCheckbox('agree', ' Souhlasím s obchodními podmínkami a GDPR')
			->setRequired('Je potřeba souhlasit s obchodními podmínkami a GDPR')
			->setDefaultValue(FALSE);
		// <<---- KONTAKTNI UDAJE

		$form->addSubmit('send', 'Rezervovat');

		$form->onSuccess[] = [$this, 'reservationFormSuccess'];

		return $form;
	}

	public function reservationFormSuccess(Form $form, $values)
	{
		// L1 Check: Emptiness
		$validateL1 = [
			'reservation_date'	=> empty($values->reservation_date),
			'reservation_units'	=> (empty($values->reservation_units) || ($values->reservation_units == "[]")) ? true : false,
			'jmeno'				=> empty($values->jmeno),
			'prijmeni'			=> empty($values->prijmeni),
			'email'				=> empty($values->email),
			'predvolba'			=> empty($values->predvolba),
			'telefon'			=> empty($values->telefon),
			'subscribe'			=> !isset($values->subscribe),
			'agree'				=> !isset($values->agree),
		];
		foreach($validateL1 as $key => $value)
		{
			if($value)
			{
				$form->addError("Hodnota ".$key." nesmí být prázdná.");
				return $this->onError($form);
			}
		}

		// L2 Check: Format
		$validateL2 = [
			'reservation_date'	=> Validators::is($values->reservation_date, 'string:8..10'),	// String, lenght 8 to 10 (min. '2021-1-1' / max. '2021-01-01')
			'reservation_units'	=> Validators::is($values->reservation_units, 'string:5..1000'),	// String, lenght 5 to 300 (min. '[23A]' / typ. '["34A"]' / max. JSON_Array)
			'jmeno'				=> Validators::is($values->jmeno, 'string:1..30'),				// String, lenght 1 to 30
			'prijmeni'			=> Validators::is($values->prijmeni, 'string:1..30'),			// String, lenght 1 to 30
			'email'				=> Validators::is($values->email, 'string:7..64') &&			// String, lenght 7 to 64 (min. '1@34.67')
								   Validators::isEmail($values->email),							// Email, format
			'predvolba'			=> in_array($values->predvolba, ['+420','+421'], true),			// String, '+420' or '+421' only, strict
		//	'predvolba'			=> Validators::is($values->predvolba, 'numericint'),			// Number
			'telefon'			=> Validators::is($values->telefon, 'digit:9'),					// Digit, exactly 9 digits
		//	'telefon'			=> Validators::is($values->telefon, 'numericint:100000000..999999999'),	// Number, exactly 9 digits
			'subscribe'			=> Validators::is($values->subscribe, 'bool'),					// Bool
			'agree'				=> Validators::is($values->agree, 'bool'),						// Bool
		];
		foreach($validateL2 as $key => $value)
		{
			if(!$value)
			{
				$form->addError("Hodnota ".$key." nebyla vyplněna správně.");
				return $this->onError($form);
			}
		}

		// L3 Check: Values
		$validateL3 = [
			'agree'		=> ($values->agree === true),	// Must be TRUE
		];
		foreach($validateL3 as $key => $value)
		{
			if(!$value)
			{
				$form->addError("Je vyžadován souhlas s podmínkami zaškrtnutím příslušných polí.");
				return $this->onError($form);
			}
		}

		// TODO: L4 Check: Date & Hour
		$hour = $this->calendar->getReservationFirstHour($values->reservation_units);
		$dateRes = Carbon::create($values->reservation_date, 'Europe/Prague')->setHour($hour)->subMinute(5);
		$dateNow = Carbon::now('Europe/Prague');
		if($dateRes < $dateNow) // ERROR: Reservation date is older than "NOW" - 5 minutes
		{
			$form->addError("Pokud nemáte stroj času, vyberte prosím čas rezervace alespoň 5 minut dopředu. Děkujeme.");
			return $this->onError($form);
		}




		// $year, $month, $day, $unitsJson, $name, $surname, $email, $phone, $subscribe
		//$this->calendar->createReservationRequest_raw();

		// Carbon $date, array $units, array $customer
		$date = Carbon::create($values->reservation_date, 'Europe/Prague')->startOfDay();
		$customer = [
			'name'			=> $values->jmeno,
			'surname'		=> $values->prijmeni,
			'email'			=> $values->email,
			'phone'			=> $values->predvolba . $values->telefon,
			'subscribe'		=> $values->subscribe,
		];
		$result = $this->calendar->createReservationRequest($date, json_decode($values->reservation_units), $customer);

		if($result === true)
		{
			$params = [
				'jmeno'				=> $values->jmeno,
				'prijmeni'			=> $values->prijmeni,
				'email'				=> $values->email,
				'telefon'			=> $values->predvolba . $values->telefon,
				'subscribe'			=> $values->subscribe,
				'agree'				=> $values->agree,
				'reservation_date'	=> $values->reservation_date,
				'reservation_units'	=> $values->reservation_units,
				//'authCode'			=> $result, // AUTH-OVERRIDE (TEMP)
			];
			//$this->calendar->completeReservationRequest($result);

			$this->onUserSave($this, $params);
		}
		else
		{
			if(is_string($result)) $form->addError($result);
			else $form->addError("Došlo k neznámé chybě. Zkuste to prosím později.");

			$this->onError($form);
		}
	}

	public function render()
	{
		$this->template->setFile(__DIR__ .'/@reservationForm.latte');
		$this->template->render();
	}
}

interface IReservationFormFactory
{
	/**
	 * @return ReservationFormFactory
	 */
	function create();
}

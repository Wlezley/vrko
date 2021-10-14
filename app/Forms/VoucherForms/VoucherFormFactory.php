<?php

declare(strict_types=1);

namespace App\Forms;

use Nette;
use App\Model;
use App\Model\Voucher;
use App\Model\Ecomail;
use App\Model\AgmoPayments;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\Responses\JsonResponse;

use Nette\Utils\Json;
use Nette\Utils\Strings;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;

use Nette\Database\Explorer;
use Carbon\Carbon;


class VoucherFormFactory extends Control {

	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var App\Model\Voucher */
	protected $voucher;

	/** @var Ecomail\EcomailApi */
	protected $ecomail;

	/** @var AgmoPayments\AgmoPaymentsSimpleDatabase */
	private $cgDbase;

	/** @var AgmoPayments\AgmoPaymentsSimpleProtocol */
	private $cgProto;

	/** @var callback */
	public $onUserSave;

	/** @var callback */
	public $onError;

	public function __construct(Explorer $database,
								Voucher\Voucher $voucher,
								Ecomail\EcomailApi $ecomail,
								AgmoPayments\AgmoPaymentsSimpleDatabase $cgDbase,
								AgmoPayments\AgmoPaymentsSimpleProtocol $cgProto)
	{
		$this->database = $database;
		$this->voucher = $voucher;
		$this->ecomail = $ecomail;

		// DEBUG Comgate
		$this->cgDbase = $cgDbase;
		$this->cgProto = $cgProto;
	}

	protected function createComponentForm()
	{		
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		// KONTAKTNI UDAJE ---->>
		$form->addText('jmeno', 'Jméno')
			->setHtmlAttribute('placeholder', 'Jméno')
			//->addRule(Form::MIN_LENGTH, 'Jméno musí mít alespoň %d znak', 1)
			->addRule(Form::MAX_LENGTH, 'Jméno může mít nejvíce %d znaků', 30)
			->setRequired('Zadejte své jméno');

		$form->addText('prijmeni', 'Příjmení')
			->setHtmlAttribute('placeholder', 'Příjmení')
			//->addRule(Form::MIN_LENGTH, 'Příjmení musí mít alespoň %d znak', 1)
			->addRule(Form::MAX_LENGTH, 'Příjmení může mít nejvíce %d znaků', 30)
			->setRequired('Zadejte své příjmení');

		$form->addText('email', 'Email')
			->setHtmlAttribute('placeholder', 'Email')
			->addRule(Form::EMAIL, 'Email je neplatný!')
			->addRule(Form::MIN_LENGTH, 'Email musí mít alespoň %d znaků', 7)
			->addRule(Form::MAX_LENGTH, 'Email může mít nejvíce %d znaků', 64)
			->setRequired('Email musí být vyplněn!');

		$form->addSelect('predvolba', 'Předvolba:', ['+420' => '+420', '+421' => '+421'])
			->setDefaultValue('+420')
			//->setAttribute('class', 'form-control')
			//->addRule(function($control){ return in_array($control->value, ['+420','+421'], true) ? TRUE : FALSE; }, 'Předvolba je neplatná!')
			->setRequired('Vyberte předvolbu ze seznamu');

		$form->addText('telefon', 'Telefon')
			->setHtmlAttribute('placeholder', 'Telefon')
			->setHtmlType('tel')
			->addRule(Form::LENGTH, 'TELEFON: Je vyžadováno přesně 9 číslic. Předvolba ani mezery se nevyplňují.', 9)
			//->addRule(Form::INTEGER, 'TELEFON: Může obsahovat pouze číslice.')
			->addRule(Form::PATTERN, 'TELEFON: Může obsahovat pouze číslice.', '^[0-9]+$')
			->setRequired('Telefon musí být vyplněn!');
		// <<---- KONTAKTNI UDAJE

		// VOUCHER SELECT ---->>
		$vouchery = [
			'1' => '1 poukaz',
			'2' => '2 poukazy',
			'3' => '3 poukazy',
			'4' => '4 poukazy',
			'5' => '5 poukazů',
			'6' => '6 poukazů',
			'7' => '7 poukazů',
			'8' => '8 poukazů',
			'9' => '9 poukazů',
			'10' => '10 poukazů',
		];
		$form->addSelect('vouchery', 'Počet poukazů', $vouchery)
			->setDefaultValue('1')
			->setRequired('Vyberte počet poukazů.');
		// <<---- VOUCHER SELECT

		// CHECK BOXES ---->>
		$form->addCheckbox('subscribe') // NEWS-SUB
			->setDefaultValue(FALSE);

		$form->addCheckbox('agree') // VOP & GDPR
			->setRequired('Je vyžadován souhlas s obchodními podmínkami a GDPR.')
			->setDefaultValue(FALSE);

		$form->addCheckbox('agree2') // DP & RR
			->setRequired('Je vyžadován souhlas s dodacími a reklamačními podmínkami.')
			->setDefaultValue(FALSE);
		// <<---- CHECK BOXES

		// CONFIRM
		$form->addSubmit('send', 'Objednat');

		$form->onSuccess[] = [$this, 'voucherFormSuccess'];

		return $form;
	}

	public function voucherFormSuccess(Form $form, $values)
	{
		// L1 Check: Emptiness
		$validateL1 = [
			'jmeno'		=> empty($values->jmeno),
			'prijmeni'	=> empty($values->prijmeni),
			'email'		=> empty($values->email),
			'predvolba'	=> empty($values->predvolba),
			'telefon'	=> empty($values->telefon),
			'vouchery'	=> empty($values->vouchery),
			'subscribe'	=> !isset($values->subscribe),
			'agree'		=> !isset($values->agree),
			'agree2'	=> !isset($values->agree2),
		];
		foreach($validateL1 as $key => $value)
		{
			if($value)
			{
				return $this->onError($this, "Hodnota ".$key." nesmí být prázdná.");
			}
		}

		// L2 Check: Format
		$validateL2 = [
			'jmeno'		=> Validators::is($values->jmeno, 'string:1..30'),		// String, lenght 1 to 30
			'prijmeni'	=> Validators::is($values->prijmeni, 'string:1..30'),	// String, lenght 1 to 30
			'email'		=> Validators::is($values->email, 'string:7..64') &&	// String, lenght 7 to 64 (min. 1@34.67)
						   Validators::isEmail($values->email),					// Email, format
			'predvolba'	=> in_array($values->predvolba, ['+420','+421'], true),	// String, '+420' or '+421' only, strict
		//	'predvolba'	=> Validators::is($values->predvolba, 'numericint'),	// Number
			'telefon'	=> Validators::is($values->telefon, 'digit:9'),			// Digit, exactly 9 digits
		//	'telefon'	=> Validators::is($values->telefon, 'numericint:100000000..999999999'),	// Number, exactly 9 digits
			'vouchery'	=> Validators::is($values->vouchery, 'numericint:1..10'),	// Integer, from 1 to 10
			'subscribe'	=> Validators::is($values->subscribe, 'bool'),			// Bool
			'agree'		=> Validators::is($values->agree, 'bool'),				// Bool
			'agree2'	=> Validators::is($values->agree2, 'bool'),				// Bool
		];
		foreach($validateL2 as $key => $value)
		{
			if(!$value)
			{
				return $this->onError($this, "Hodnota ".$key." nebyla vyplněna správně.");
			}
		}

		// L3 Check: Values
		$validateL3 = [
			'agree'		=> ($values->agree === true),	// Must be TRUE
			'agree2'	=> ($values->agree2 === true),	// Must be TRUE
		];
		foreach($validateL3 as $key => $value)
		{
			if(!$value)
			{
				return $this->onError($this, "Je vyžadován souhlas s podmínkami zaškrtnutím příslušných polí.");
			}
		}

		// GET ECOMAIL ID
		$ecomailID = NULL;
		if($values->subscribe == true) {
			$ecomailData = $this->ecomail->addSubscriber(2, [
				'name'		=> $values->jmeno,
				'surname'	=> $values->prijmeni,
				'email'		=> $values->email,
				'phone'		=> empty($values->telefon) ? NULL : $values->predvolba . $values->telefon,
				'vokativ'	=> $values->jmeno,
				'vokativ_s'	=> $values->prijmeni,
				], FALSE, TRUE, TRUE);
			$ecomailID = (!isset($ecomailData['id'])) ? NULL : $ecomailData['id'];
		}

		// PRAMAS COLLECTOR
		$params = [
			// KONTAKTNI INFORMACE
			'jmeno'			=> $values->jmeno,		// Jmeno
			'prijmeni'		=> $values->prijmeni,	// Prijmeni
			'email'			=> $values->email,		// E-mail
			'telefon'		=> $values->predvolba .
							   $values->telefon,	// Telefon
			'vouchery'		=> $values->vouchery,	// Pocet voucheru
			'subscribe'		=> $values->subscribe,	// Povolit EcoMail newsletters
			'agree'			=> $values->agree,		// Souhlas s VOP a GDPR
			'agree2'		=> $values->agree2,		// Souhlas s DP a RR
			'ecomailID'		=> $ecomailID,			// EcoMail ID
			'redirectUrl'	=> NULL,				// ComGate redirect URL
			/*
			'date_now'		=> new \DateTime(), // CURRENT_TIMESTAMP
			'date_format'	=> Carbon::createFromFormat('d.m.Y', $values->odhad_datum, 'Europe/Prague')->format('Y-m-d'),
			*/
		];


		// CREATE COMGATE PAYMENT (PREPARE)
		$voucherPrice = 250.00;
		$voucherCount = $values->vouchery;
		$price_total = $voucherPrice * $voucherCount;
		$orderId = $this->voucher->createOrder($price_total, $voucherCount, $values->jmeno, $values->prijmeni, $values->email, $params['telefon']);

		// CREATE COMGATE PAYMENT (MAIN)
		$price = $price_total;		// Celkem k uhrade
		$label = 'Voucher VRko.cz';	// Popis produktu
		$refId = $orderId;			// Cislo objednavky
		$payerId = '';				// ID platce (?)

		try {
			$this->cgProto->createTransaction(
				'CZ',				// $country		Kod zeme podle ISO 3166-1 (default: CZ)
				$price,				// $price		Cena
				'CZK',				// $currency	Kod meny podle ISO 4217 (default: CZK)
				$label,				// $label		Popis produktu (max. 16 znaku)
				$refId,				// $refId		Reference platby v systemu klienta (ID faktury?)
				$payerId,			// $payerId		Identifikator platce v systemu klienta (nevyplnovat?)
				'',					// $vatPL		ID pro DPH v polsku?
				'',					// $category	Kategorie
				'ALL',				// $method		Platebna metody
				'',					// $account		Uziv. ucet?
				$params['email'],	// $email		E-mail
				$params['telefon'],	// $phone		Telefon
				$label				// $productName	Nazev produktu
			);
			$transId = $this->cgProto->getTransactionId();

			$this->cgDbase->saveTransaction(
				$transId,			// $transId		ID Transakce Comgate
				$refId,				// $refId		Reference platby v systemu klienta (ID faktury?)
				$price,				// $price		Cena
				'CZK',				// $currency	Kod meny podle ISO 4217 (default: CZK)
				'PENDING'			// $status		Stav (PENDING, PAID, CANCELLED)
			);

			// STORE PENDING PAYMENT TO THE DATABASE
			$transactionData = [
				'merchant'	=> $this->cgProto->getMerchant(),
													// I11
				'test'		=> ($this->cgProto->getTest() ? 'true' : 'false'),
													// E02

				'price'		=> $price * 100,		// I11	(25000)
				'curr'		=> 'CZK',				// S03	(CZK)
				'label'		=> $label,				// S32	(Voucher VRKO)
				'refId'		=> $refId,				// I11	(123456)
				'cat'		=> '',					// S32	(PHYSICAL?)
				'method'	=> '',					// S32	(CARD_CZ_CSOB_2)
				'email'		=> $params['email'],	// S64
				'transId'	=> $transId,			// S14	(AAAA-BBBB-CCCC)
				'secret'	=> '',					// S16
				'status'	=> 'PENDING',			// E03	(PENDING, PAID, CANCELLED)
				'fee'		=> NULL,				// (unknown)
				'vs'		=> 0,					// I11	(215796741)
			];
			$this->database->table('comgate_payments')->insert($transactionData); // CG MySQL INSERT

			$params['redirectUrl'] = $this->cgProto->getRedirectUrl();
		} catch (\Exception $e) {
			//$this->logger->error($e->getMessage());
			return $this->onError($this, "Chyba platební brány: " . $e->getMessage());
		}

		/////////////////////////////////////////////////////////////

		if($ecomailID != NULL)
		{
			$zakaznik = [
				'name'			=> $values->jmeno,
				'surname'		=> $values->prijmeni,
				'email'			=> $values->email,
				'phone'			=> empty($values->telefon) ? NULL : $values->predvolba . $values->telefon,
				'dotykackaID'	=> NULL, //empty($dotykackaCustomerID) ? NULL : $dotykackaCustomerID,
				'ecomailID'		=> $ecomailID,
			];
			$this->database->table('customer')->insert($zakaznik);
		}

		return $this->onUserSave($this, $params);
	}

	public function render()
	{
		$this->template->setFile(__DIR__ .'/@voucherForm.latte');
		$this->template->render();
	}
}

interface IVoucherFormFactory
{
	/**
	 * @return VoucherFormFactory
	 */
	function create();
}

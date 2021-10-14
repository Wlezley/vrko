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

use Carbon\Carbon;


class ReservationAuthorizeFormFactory extends Control
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

		//$email = (empty($this->exchanger['email']) ? NULL : $this->exchanger['email']);
		//$authCode = (empty($this->exchanger['authCode']) ? NULL : $this->exchanger['authCode']);

		$form->addText('email',								'Email')
			->setHtmlAttribute('placeholder',				'Email')
			->addRule(Form::EMAIL, 'Zadejte platný Email.')
			->addRule(Form::MAX_LENGTH, 'EMAIL: Maximálně 64 znaků.', 64)
			->setRequired('Zadejte platný Email.');
			//->setValue($email);

		$form->addText('authCode',							'SMS Kód')
			->setHtmlAttribute('placeholder',				'SMS Kód')
			->setHtmlType('tel')
			->addRule(Form::LENGTH, 'Zadejte celý kód.', 4)
			->setRequired('Zadejte SMS kód.');
			//->setValue($authCode);

		if(!empty($this->exchanger['email'])) {
			$form['email']->setDisabled();
			$form['email']->setValue($this->exchanger['email']);
			$form['authCode']->setHtmlAttribute('autofocus', true);
		}
		else {
			$form['email']->setHtmlAttribute('autofocus', true);
		}

		/*if(!empty($this->exchanger['authCode']))
		{
			$form['authCode']->setValue($this->exchanger['authCode']);
		}*/

		$form->addSubmit('send', 'Odeslat');

		$form->onSuccess[] = [$this, 'process'];

		return $form;
	}

	public function process(Form $form, $values)
	{
		$email = !empty($this->exchanger['email']) ? $this->exchanger['email'] : $values->email;

		$params = [
			'email'		=> $email,
			'authCode'	=> $values->authCode,
		];

		$result = $this->calendar->completeReservationRequest($values->authCode, $email);
		if($result === true) {
			$this->onUserSave($this, $params);
		}
		else {
			if(is_string($result)) {
				$form->addError($result);
			}
			else {
				$form->addError("Došlo k neznámé chybě. Zkuste to prosím později.");
			}

			$this->onError($form);
		}
	}

	public function render()
	{
		$this->template->setFile(__DIR__ .'/@reservationAuthorizeForm.latte');
		$this->template->render();
	}
}

interface IReservationAuthorizeFormFactory
{
	/**
	 * @return ReservationAuthorizeFormFactory
	 */
	function create();
}

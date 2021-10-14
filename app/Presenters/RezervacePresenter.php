<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model;
use App\Model\Reservation;
use App\Forms;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;
use Nette\Database\Explorer;
use Tracy\Debugger;

use Carbon\Carbon;


class RezervacePresenter extends BasePresenter
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var App\Model\Reservation\Calendar */
	private $calendar;

	/** @var Forms\IReservationFormFactory @inject */
	public $reservationForm;

	/** @var Forms\IReservationAuthorizeFormFactory @inject */
	public $reservationAuthorizeForm;

	/** @var Http\SessionSection */
	private $rSession;

	public function __construct(Explorer $database,
								Reservation\Calendar $calendar)
	{
		//Debugger::$showBar = false; // Disable Tracy Debug Bar
		$this->database = $database;
		$this->calendar = $calendar;
	}

	public function startup()
	{
		parent::startup();

		// Reservation Session
		$this->rSession = $this->getSession('reservation');
		//$this->rSession->success = TRUE;
		//$this->rSession->remove();
	}

	// #####################################################################################

	// Komponenta ReservationForm
	protected function createComponentReservationForm()
	{
		$form = $this->reservationForm->create();

		$form->onUserSave[] = function ($form, $values)
		{
			$this->rSession->success = TRUE;
			$this->rSession->email = $values['email'];
			//$this->rSession->authCode = $values['authCode'];

			$this->flashMessage('Na Vaše telefoní číslo jsme odeslali SMS s kódem pro potvrzení. Tento kód prosím zadejte níže.', 'info');
			$this->redirect('Rezervace:authorize');
		};

		$form->onError[] = function ($form)
		{
			foreach($form->getErrors() as $error)
			{
				$this->flashMessage($error, 'danger');
			}
			$this->redirect('this');
		};

		return $form;
	}

	// Render default
	public function renderDefault()
	{
		// Session Cleanup
		$this->rSession->remove();

		$year = Carbon::now()->year;
		$month = Carbon::now()->month;
		$renderData = $this->calendar->getRenderData_Selectday($year, $month);

		if(!$renderData)
		{
			$this->template->error = true;
			return;
		}
		$this->template->error = false;

		$this->template->monthName	= $renderData['monthName'];
		$this->template->dayNames	= $renderData['dayNamesShort'];
		$this->template->pagination	= $renderData['pagination'];
		$this->template->now = $this->calendar->getDateNowArray();
		$this->template->calMonthPage = $renderData['calMonthPage'];

		//$this->flashMessage('Vážený zákazníku, omlouváme se, provoz je pozastaven z důvodu vládních nařízení. Brzy se na Vás těšíme!', 'info');
	}

	// #####################################################################################

	// Komponenta ReservationAuthorizeForm
	protected function createComponentReservationAuthorizeForm()
	{
		$form = $this->reservationAuthorizeForm->create();

		if(!empty($this->rSession->success) && $this->rSession->success === TRUE)
		{
			$form->setData([
				'email'		=> (empty($this->rSession->email) ? NULL : $this->rSession->email),
			//	'authCode'	=> (empty($this->rSession->authCode) ? NULL : $this->rSession->authCode),
			]);
		}
		/*else
		{
			$this->redirect('Rezervace:default');
		}*/

		$form->onUserSave[] = function ($form, $values)
		{
			// Session Cleanup
			$this->rSession->remove();

			$this->flashMessage('Rezervace proběhla úspěšně. Potvrzení zasíláme na Váš email. Děkujeme.', 'success');
			$this->redirect('Rezervace:default');
		};

		$form->onError[] = function ($form)
		{
			foreach($form->getErrors() as $error)
			{
				$this->flashMessage($error, 'danger');
			}
			$this->redirect('this');
		};

		return $form;
	}

	// Render Authorize
	public function renderAuthorize()
	{
		// return;
	}
}

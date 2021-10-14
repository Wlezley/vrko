<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model;
use App\Model\Reviews;
use App\Forms;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;
use Nette\Database\Explorer;
use Tracy\Debugger;

use Carbon\Carbon;


class HodnoceniPresenter extends BasePresenter
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Model\Reviews\Reviews */
	protected $reviews;

	/** @var Forms\IReviewFormFactory @inject */
	public $reviewForm;

	/** @var Http\SessionSection */
	private $rSession;

	public function __construct(Explorer $database,
								Reviews\Reviews $reviews)
	{
		//Debugger::$showBar = false; // Disable Tracy Debug Bar
		$this->database = $database;
		$this->reviews = $reviews;
	}

	public function startup()
	{
		parent::startup();

		// Review Session
		$this->rSession = $this->getSession('review');
		//$this->rSession->success = TRUE;
		//$this->rSession->remove();
	}

	// #####################################################################################

	// Komponenta ReviewForm
	protected function createComponentReviewForm()
	{
		$form = $this->reviewForm->create();

		$form->setData([
			'reviewHash'	=> $this->rSession->reviewHash,
			'reviewEmail'	=> $this->rSession->reviewEmail,
		]);

		$form->onUserSave[] = function ($form, $values)
		{
			$this->reviews->saveReview($values['hash'], $values['review']);
			$this->flashMessage('Děkujeme za Vaše hodnocení.', 'success');
			$this->redirect('Homepage:');
		};

		$form->onError[] = function ($form)
		{
			/*foreach($form->getErrors() as $error)
			{
				$this->flashMessage($error, 'danger');
			}
			$this->redirect('this');*/
		};

		return $form;
	}

	// Render Review
	public function renderDefault($reviewHash = NULL)
	{
		// Session Cleanup
		$this->rSession->remove();

		if(!isset($reviewHash))
		{
			$this->redirect('Homepage:');
			return;
		}

		// 1.) Ziskat email z DB podle reviewHash
		$reviewEmail = $this->reviews->getReviewEmail($reviewHash);
		if(!isset($reviewEmail))
		{
			$this->flashMessage('Hodnocení již bylo odesláno.', 'info');
			$this->redirect('Homepage:');
			return;
		}

		// 2.) Zapsat data do session
		$this->rSession->reviewHash = $reviewHash;
		$this->rSession->reviewEmail = $reviewEmail;
	}

	public function renderGoogle($reviewHash = NULL)
	{
		if(!isset($reviewHash))
		{
			$this->flashMessage('Hodnocení již bylo odesláno.', 'info');
			$this->redirect('Homepage:');
			return;
		}

		$redirectUrl = $this->reviews->googleReview($reviewHash);

		if(isset($redirectUrl))
		{
			header("Location: " . $redirectUrl, true, 302);
		}
		else
		{
			$this->flashMessage('Hodnocení již bylo odesláno.', 'info');
			$this->redirect('Homepage:');
		}

		return;
	}
}

//$this->flashMessage('SUCCESS', 'success');
//$this->flashMessage('INFO', 'info');
//$this->flashMessage('WARNING', 'warning');
//$this->flashMessage('DANGER', 'danger');

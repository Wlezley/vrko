<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Utils\Json;
use Nette\Utils\ArrayHash;
use Nette\Database\Explorer;
use Tracy\Debugger;

//use DotykackaPHPApiClient;
use Nette\Security\Passwords;

final class AdminPresenter extends SecuredPresenter
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Model\Dotykacka\DotykackaApi */
	protected $dotyApi;

	/** @var Passwords */
	private $passwords;

	public function __construct(Explorer $database, Passwords $passwords)
	{
		$this->database = $database;
		//$this->dotyApi = new Model\Dotykacka\DotykackaApi($this->database);
		$this->passwords = $passwords;
	}

	//public function actionDefault($hash)
	public function renderDefault($hash)
	{
		//$this->redirect('Homepage:');
		//$this->template->debug = print_r($this->dotyApi->Testing(), true);
		//$this->template->debug2 = $this->passwords->hash('HeheSloslo.357');
	}

	public function actionLogout()
	{
		$this->user->logout();
		$this->flashMessage('Byli jste úspěšně odhlášeni');
		$this->redirect('Sign:in');
	}
}

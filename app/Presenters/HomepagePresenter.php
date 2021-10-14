<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Utils\Json;
use Nette\Utils\ArrayHash;
use Nette\Database\Explorer;
use Tracy\Debugger;


final class HomepagePresenter extends BasePresenter
{
	/* * @var Nette\Database\Explorer * /
	protected $database;*/

	public function __construct(/*Explorer $database*/)
	{
		//$this->database = $database;
	}

	public function startup()
	{
		parent::startup();
	}

	//public function actionDefault()
	public function renderDefault()
	{
	}
	//public function actionOherne()
	public function renderOherne()
	{
	}
	//public function actionFaq()
	public function renderFaq()
	{
	}
	//public function actionTeambuilding()
	public function renderTeambuilding()
	{
	}
	//public function actionKatalogher()
	public function renderKatalogher()
	{
	}
	//public function actionCenik()
	public function renderCenik()
	{
	}
	//public function actionKontakt()
	public function renderKontakt()
	{
	}

	public function actionZazitek()
	{
		header('Location: https://www.zetcomp.cz/');
		return;
	}
	public function actionLetak()
	{
		$this->redirect('Homepage:default');
	}
}

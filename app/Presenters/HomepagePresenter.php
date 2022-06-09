<?php

declare(strict_types=1);

namespace App\Presenters;

final class HomepagePresenter extends BasePresenter
{
	public function __construct()
	{
	}

	public function startup()
	{
		parent::startup();
	}

	public function renderDefault()
	{
	}

	public function renderOherne()
	{
	}

	public function renderFaq()
	{
	}

	public function renderTeambuilding()
	{
	}

	public function renderKatalogher()
	{
		$this->redirect("Katalog:default");
	}

	public function renderCenik()
	{
	}

	public function renderKontakt()
	{
	}

	public function actionZazitek()
	{
		$this->redirectUrl("https://www.zetcomp.cz/", 302);
	}

	public function actionLetak()
	{
		$this->redirect("Homepage:default");
	}
}

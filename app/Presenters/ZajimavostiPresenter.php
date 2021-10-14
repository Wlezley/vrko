<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Forms;
//use Tracy\Debugger;

final class ZajimavostiPresenter extends BasePresenter
{
	/** @var Forms\IHadankaFormFactory @inject */
	public $HadankaForm;

	public function __construct() {}

	public function renderDefault()
	{
		$this->redirect('Homepage:default');
	}

	// Komponenta HadankaForm
	protected function createComponentHadankaForm()
	{
		$form = $this->HadankaForm->create();

		$form->onUserSave[] = function ($form, $values) {
			$this->flashMessage('Správně, je to Tvé jméno: ' . $values['odpoved'], 'success');
			$this->redirect('Zajimavosti:hadanka');
		};

		return $form;
	}

	public function renderChirurg() {}
	public function renderVojak() {}
	public function renderHistorie() {}
	public function renderPredstava() {}
	public function renderSkolnivyuka() {}
	public function renderLecba() {}
	public function renderHadanka() {}
}

<?php

declare(strict_types=1);

namespace App\Forms;

use Nette;
use App\Model;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\Responses\JsonResponse;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Nette\Utils\ArrayHash;


class HadankaFormFactory extends Control
{
	/** @var callback */
	public $onUserSave;

	public function __construct()
	{
	}

	protected function createComponentForm()
	{		
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		// VSTUP ---->>
		$form->addText('odpoved',							'Jméno hráče')
			->setHtmlAttribute('placeholder',				'Jméno hráče')
			->addRule(Form::MAX_LENGTH, 'Maximálně 64 znaků.', 64)
			->setRequired('Položka je povinná.');
		// <<---- VSTUP

		$form->addSubmit('send', 'Pokračovat');

		$form->onSuccess[] = [$this, 'process'];

		return $form;
	}

	public function process(Form $form, $values)
	{
		$params = [
			'odpoved'	=> $values->odpoved,
		];
		$this->onUserSave($this, $params);
	}

	public function render()
	{
		$this->template->setFile(__DIR__ .'/@hadankaForm.latte');
		$this->template->render();
	}
}

interface IHadankaFormFactory
{
	/**
	 * @return HadankaFormFactory
	 */
	function create();
}

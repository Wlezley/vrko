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


class ReviewFormFactory extends Control
{
	/** @var callback */
	public $onUserSave;

	/** @var callback */
	public $onError;

	/** @var array */
	private $exchanger;

	public function __construct()
	{
	}

	// Data Exchanger
	public function getData() { return $this->exchanger; }
	public function setData($exchanger) { $this->exchanger = $exchanger; }

	protected function createComponentForm()
	{		
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addHidden('hash')
			->setValue($this->exchanger['reviewHash']);

		// EMAIL ---->>
		$form->addText('email',								'Email')
			->setHtmlAttribute('placeholder',				'Email')
			->addRule(Form::EMAIL, 'Zadejte platný Email.')
			->addRule(Form::MAX_LENGTH, 'EMAIL: Maximálně 64 znaků.', 64)
			->setRequired('Zadejte platný Email.');
		// <<---- EMAIL

		// REVIEW ---->>
		$form->addTextArea('review',						'Vaše připomínky')
			->setHtmlAttribute('placeholder',				'Dobrý den, mám připomínku ohledně:')
			->addRule(Form::MAX_LENGTH, 'Hodnocení nesmí přesáhnout 500 znaků.', 500)
			->setRequired('Položka hodnocení je povinná.');
		// <<---- REVIEW

		// INIT DEFAULTS ---->>
		if(!empty($this->exchanger['reviewEmail']))
		{
			$form['email']->setDisabled();
			$form['email']->setValue($this->exchanger['reviewEmail']);
		}
		// <<---- INIT DEFAULTS

		$form->addSubmit('send', 'Odeslat');

		$form->onSuccess[] = [$this, 'process'];

		return $form;
	}

	public function process(Form $form, $values)
	{
		$params = [
			'hash'		=> $values->hash,
		//	'email'		=> $values->email,
			'review'	=> $values->review,
		];

		$this->onUserSave($this, $params);
	}

	public function render()
	{
		$this->template->setFile(__DIR__ .'/@reviewForm.latte');
		$this->template->render();
	}
}

interface IReviewFormFactory
{
	/**
	 * @return ReviewFormFactory
	 */
	function create();
}

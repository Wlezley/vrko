<?php

namespace App\Forms;

use Nette;
use Nette\Security\User;
use Nette\Application\UI\Form;
use Tracy\Debugger;

class SignInFormFactory /*extends Nette\Object*/
{
	/** @var User */
	protected $user;

	public function __construct(User $user)
	{
		$this->user = $user;
	}

	public function create()
	{
		$form = new Form();

		$form->addText('username', 'Přihlašovací jméno')
			->setHtmlAttribute('placeholder', 'Přihlašovací jméno')
			->setRequired();

		$form->addPassword('password', 'Heslo')
			->setHtmlAttribute('placeholder', 'Heslo')
			->setRequired();

		$form->addSubmit('send', 'Přihlásit se');

		$form->onSuccess[] = [$this, 'process'];

		return $form;
	}

	public function process(Form $form, $values)
	{
		try {
			$this->user->login($values->username, $values->password);
			//$this->user->setExpiration('+6 hours', true);
			//$this->user->setExpiration('+6 hours');
			$this->user->setExpiration(null);
		} catch(Nette\Security\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}
}


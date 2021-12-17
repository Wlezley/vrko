<?php

namespace App\Presenters;

use Nette;

class SecuredPresenter extends BasePresenter
{
	public function startup()
	{
		parent::startup();

		if (!$this->user->isLoggedIn()) {
			$this->redirect('Sign:in');
		}
	}
}

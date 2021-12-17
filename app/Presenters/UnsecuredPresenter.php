<?php

namespace App\Presenters;

use Nette;

class UnsecuredPresenter extends BasePresenter
{
	public function startup()
	{
		parent::startup();

		/*if ($this->user->isLoggedIn()) {
			$this->redirect('Homepage:');
		}*/
	}
}

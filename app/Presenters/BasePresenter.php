<?php

namespace App\Presenters;

use Nette;

class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @var string */
    protected $baseImgUrl;

	public function __construct()
	{
        parent::__construct();

        //$this->baseImgUrl = $this->template->baseUrl . "/img";
	}
}

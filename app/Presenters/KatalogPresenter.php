<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model;
use App\Model\Katalog;
use Nette\Utils\Json;
use Nette\Utils\ArrayHash;
use Nette\Database\Explorer;
use Tracy\Debugger;


final class KatalogPresenter extends BasePresenter
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Model\Katalog\Katalog */
	protected $katalog;

	public function __construct(Explorer $database,
								Katalog\Katalog $katalog)
	{
		$this->database = $database;
		$this->katalog = $katalog;
	}

	public function startup()
	{
		parent::startup();
	}

	public function renderDefault()
	{
		// Seznam kategorii
		$this->template->categories = $this->katalog->getCategoryList();

		// Pocet vsech her v DB
		$this->template->gamesTotal = $this->katalog->getGamesCountByCategoryId(0);

		// Seznam her (vsechny hry? pagination?)
		//$this->template->gamelist = $this->katalog->getGamesByCategory(NULL); // NULL == ALL
	}

	public function renderCategory($category)
	{
		// Seznam her podle kategorie (URL)
		$this->template->category = $this->katalog->getCategoryDataByUrl($category);

		if($this->template->category == NULL)
		{
			//$this->redirect('Katalog:default');
			$this->error();
		}

		$this->template->gamelist = $this->katalog->getGamesByCategory($this->template->category['id']);
	}

	public function renderGamepage($category, $game)
	{
		// Data na stranku o hre
		$data = $this->katalog->getGameInfo($game);

		if(is_null($data))
		{
			//$this->redirect('Katalog:default');
			$this->error();
		}

		$this->template->gamepage = $data;

		$this->template->params['category'] = [
			'url'	=> $data['categoryData']['url'],
			'nameS'	=> $data['categoryData']['nameS'],
			'icon'	=> $data['categoryData']['icon'],
		];

		switch($data['players'])
		{
			case -2:
				$this->template->params['players']['icon'] = "/img/icon/pocethracu3.png";
				$this->template->params['players']['desc'] = "MMO";
				break;
			case -1:
				$this->template->params['players']['icon'] = "/img/icon/pocethracu3.png";
				$this->template->params['players']['desc'] = "PvP Multiplayer";
				break;
			case 0: // NULL
				$this->template->params['players']['icon'] = "/img/icon/pocethracu3.png";
				$this->template->params['players']['desc'] = "Počet hráčů neznámý";
				break;
			case 1:
				$this->template->params['players']['icon'] = "/img/icon/pocethracu1.png";
				$this->template->params['players']['desc'] = "Pro jednoho hráče";
				break;
			case 2:
				$this->template->params['players']['icon'] = "/img/icon/pocethracu2.png";
				$this->template->params['players']['desc'] = "Pro 1 nebo 2 hráče";
				break;
			case 3:
				$this->template->params['players']['icon'] = "/img/icon/pocethracu3.png";
				$this->template->params['players']['desc'] = "Až pro 3 hráče";
				break;
			case 4:
				$this->template->params['players']['icon'] = "/img/icon/pocethracu3.png";
				$this->template->params['players']['desc'] = "Až pro 4 hráče";
				break;
			default:
				$this->template->params['players']['icon'] = "/img/icon/pocethracu3.png";
				$this->template->params['players']['desc'] = "Až pro ".$data['players']." hráčů";
				break;
		}

		switch($data['skills'])
		{
			case 1:
				$this->template->params['skills']['icon'] = "/img/icon/narocnost1.png";
				$this->template->params['skills']['desc'] = "Pro všechny hráče";
				break;
			case 2:
				$this->template->params['skills']['icon'] = "/img/icon/narocnost2.png";
				$this->template->params['skills']['desc'] = "Pro mírně pokročilé";
				break;
			case 3:
				$this->template->params['skills']['icon'] = "/img/icon/narocnost3.png";
				$this->template->params['skills']['desc'] = "Pro zkušené hráče";
				break;
			default:
				$this->template->params['skills']['icon'] = "/img/icon/narocnost3.png";
				$this->template->params['skills']['desc'] = "Zkušenost neznámá";
				break;
		}

		switch($data['difficulty'])
		{
			case 1:
				$this->template->params['difficulty']['icon'] = "/img/icon/fyzicka1.png";
				$this->template->params['difficulty']['desc'] = "Fyzicky nenáročné";
				break;
			case 2:
				$this->template->params['difficulty']['icon'] = "/img/icon/fyzicka2.png";
				$this->template->params['difficulty']['desc'] = "Fyzicky středně náročné";
				break;
			case 3:
				$this->template->params['difficulty']['icon'] = "/img/icon/fyzicka3.png";
				$this->template->params['difficulty']['desc'] = "Fyzicky velmi náročné";
				break;
			default:
				$this->template->params['difficulty']['icon'] = "/img/icon/fyzicka3.png";
				$this->template->params['difficulty']['desc'] = "Náročnost neznámá";
				break;
		}
	}
}

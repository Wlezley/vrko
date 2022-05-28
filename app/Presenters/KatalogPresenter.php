<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model\Katalog;
use App\Model\KatalogAttributes;

use Nette\Database\Explorer;


final class KatalogPresenter extends BasePresenter
{
    /** @var Nette\Database\Explorer */
    protected $database;

    /** @var App\Model\Katalog */
    protected $katalog;

    /** @var App\Model\KatalogAttributes */
    public $attributes;

    public function __construct(Explorer $database,
                                Katalog $katalog,
                                KatalogAttributes $attributes)
    {
        $this->database = $database;
        $this->katalog = $katalog;
        $this->attributes = $attributes;
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
            'url'    => $data['categoryData']['url'],
            'nameS'  => $data['categoryData']['nameS'],
            'icon'   => empty($data['categoryData']['icon']) ? "" : $this->template->baseUrl . $data['categoryData']['icon'],
        ];

        $this->template->params['players'] = $this->attributes->getPlayers($data['players']);
        $this->template->params['skills'] = $this->attributes->getSkills($data['skills']);
        $this->template->params['difficulty'] = $this->attributes->getDifficulty($data['difficulty']);

        $this->template->params['players']['icon'] = $this->template->baseUrl . $this->template->params['players']['icon'];
        $this->template->params['skills']['icon'] = $this->template->baseUrl . $this->template->params['skills']['icon'];
        $this->template->params['difficulty']['icon'] = $this->template->baseUrl . $this->template->params['difficulty']['icon'];
    }

    public function actionEdit(int $id = null)
    {
        $param = $_POST['param'];

        if (!empty($param)) {
            $gameId = $this->katalog->saveGameInfo($param);

            if ($gameId) {
                $this->redirect('Katalog:edit', $gameId);
            }
        }

        $game = $this->katalog->getGameInfoById($id);

        // Oprava indexu categoryPool
        $categoryPool = [];
        foreach ($game['categoryPool'] as $value) {
            $categoryPool[] = $value;
        }
        $game['categoryPool'] = $categoryPool;

        $this->template->game = $game;
        $this->template->imageList = array_diff(scandir(__DIR__ . "/../../www/img/hry/"), array('.', '..'));
        $this->template->categoryList = $this->katalog->getCategoryList();
        $this->template->attributes = $this->attributes->getAttributes();

        // FILENAME REPAIR
        // $path = __DIR__ . "/../../www/img/hry/";
        // foreach ($this->template->imageList as $file_name) {
            // $new = str_replace("-jpg", ".jpg", Nette\Utils\Strings::webalize($file_name));
        //     rename($path . $file_name, $path . $new);
        // }
    }
}

<?php

declare(strict_types=1);

namespace App\Model\Katalog;

use Nette;
use App\Model;
use Nette\Utils\Json;
use Nette\Utils\ArrayHash;
use Nette\Utils\Validators;
use Nette\Database\Explorer;
use Tracy\Debugger;


class Katalog
{
	/** @var Nette\Database\Explorer */
	protected $database;

	public function __construct(Explorer $database)
	{
		$this->database = $database;
	}


	/** Get CATEGORY LIST
	 * @return	array|NULL
	 */
	public function getCategoryList()
	{
		$result = $this->database->query('SELECT * FROM gamelist_category WHERE `priority` > ? ORDER BY `priority` DESC', 0.0);

		if($result && $result->getRowCount() > 0)
		{
			$data = [];

			foreach($result->fetchAll() as $row)
			{
				$data[$row['id']] = $row;
				$data[$row['id']]['count'] = $this->getGamesCountByCategoryId($row['id']); // TEMP: Count override...
			}

			return $data;
		}

		return NULL;
	}

	/** Get CATEGORY DATA (by CATEGORY ID)
	 * @param	int			$categoryId		// ID Kategorie
	 * 
	 * @return	array|NULL
	 */
	public function getCategoryDataById($categoryId)
	{
		$result = $this->database->query('SELECT * FROM gamelist_category WHERE id = ?', $categoryId);

		if($result && $result->getRowCount() == 1)
		{
			$data = $result->fetch();
			$data['count'] = $this->getGamesCountByCategoryId($data['id']); // TEMP: Count override...

			return $data;
		}

		return NULL;
	}

	/** Get CATEGORY DATA (by URL)
	 * @param	string		$categoryUrl	// URL Kategorie (z url linku)
	 * 
	 * @return	array|NULL
	 */
	public function getCategoryDataByUrl($categoryUrl)
	{
		$result = $this->database->query('SELECT * FROM gamelist_category WHERE url = ?', $categoryUrl);

		if($result && $result->getRowCount() == 1)
		{
			$data = $result->fetch();
			$data['count'] = $this->getGamesCountByCategoryId($data['id']); // TEMP: Count override...

			return $data;
		}

		return NULL;
	}

	/** Get CATEGORY POOL (by GAME ID)
	 * @param	int			$gameId			// ID Hry
	 * 
	 * @return	array|NULL
	 */
	public function getCategoryPoolByGameId($gameId)
	{
		$result = $this->database->query('SELECT categoryId FROM gamelist_category_pool WHERE gameId = ?', $gameId);

		if($result && $result->getRowCount() > 0)
		{
			$categoryPool = [];

			foreach($result->fetchAll() as $row)
			{
				$categoryPool[$row['categoryId']] = $this->getCategoryDataById($row['categoryId']);
			}

			return $categoryPool;
		}

		return NULL;
	}

	/** Get GAMES COUNT in CATEGORY (by CATEGORY ID) - SIMPLE
	 * @param	int			$categoryId		// ID Kategorie
	 * 
	 * @return	int
	 */
	public function getGamesCountByCategoryId_SIMPLE($categoryId)
	{
		if($categoryId == 0)
		{
			$result = $this->database->query('SELECT COUNT(DISTINCT gameId) AS count FROM gamelist_category_pool');
		}
		else
		{
			$result = $this->database->query('SELECT COUNT(*) AS count FROM gamelist_category_pool WHERE categoryId = ?', $categoryId);
		}

		if($result && $result->getRowCount() == 1)
		{
			return $result->fetch()['count'];
		}

		return 0;
	}

	/** Get GAMES COUNT in CATEGORY (by CATEGORY ID) - COMPLEX
	 * @param	int			$categoryId		// ID Kategorie
	 * 
	 * @return	int
	 */
	public function getGamesCountByCategoryId_COMPLEX($categoryId)
	{
		if($categoryId == 0)
		{
			$poolQR = $this->database->query('SELECT DISTINCT gameId FROM gamelist_category_pool');
		}
		else
		{
			$poolQR = $this->database->query('SELECT gameId FROM gamelist_category_pool WHERE categoryId = ?', $categoryId);
		}

		if($poolQR && $poolQR->getRowCount() > 0)
		{
			$gameIds = [];

			foreach($poolQR->fetchAll() as $row)
			{
				$gameIds[] = $row['gameId'];
			}

			$gameQR = $this->database->query('SELECT COUNT(*) AS count FROM gamelist_gameinfo WHERE display = ? AND id IN(?)', "show", $gameIds);

			if($gameQR && $gameQR->getRowCount() == 1)
			{
				return $gameQR->fetch()['count'];
			}
		}

		return 0;
	}

	/** Get GAMES COUNT in CATEGORY (by CATEGORY ID) - COMPATIBILITY HANDLER
	 * @param	int			$categoryId		// ID Kategorie
	 * 
	 * @return	int
	 */
	public function getGamesCountByCategoryId($categoryId)
	{
		//return $this->getGamesCountByCategoryId_SIMPLE($categoryId);
		return $this->getGamesCountByCategoryId_COMPLEX($categoryId);
	}

	/** Get GAME LIST (by CATEGORY ID)
	 * @param	int			$categoryId		// ID Kategorie (NULL == vsechny hry)
	 * @param	string		$page			// TODO: Stranka
	 * @param	string		$limit			// TODO: Pocet polozek na stranku
	 *
	 * @return	array|NULL
	 */
	public function getGamesByCategory($categoryId = NULL, $page = 1, $limit = 20)
	{
		if($categoryId == NULL)
		{
			$result = $this->database->query('SELECT id, url, fullName, imageMain, categoryId FROM gamelist_gameinfo WHERE display = ?', "show");
		}
		else
		{
			$poolQR = $this->database->query('SELECT gameId FROM gamelist_category_pool WHERE categoryId = ?', $categoryId);

			$gameIds = [];

			if($poolQR && $poolQR->getRowCount() > 0)
			{
				foreach($poolQR->fetchAll() as $row)
				{
					$gameIds[] = $row['gameId'];
				}
			}

			$result = $this->database->query('SELECT id, url, fullName, imageMain, categoryId FROM gamelist_gameinfo WHERE display = ? AND id IN(?)', "show", $gameIds);
		}

		if($result && $result->getRowCount() > 0)
		{
			$data = [];

			foreach($result->fetchAll() as $row)
			{
				$data[$row['id']] = $row;
				$data[$row['id']]['mainCategory'] = $this->getCategoryDataById($row['categoryId'])['url'];
			}

			return $data;
		}

		return NULL;
	}

	/** Get GAME DATA (by GAME URL)
	 * @param	string		$gameUrl		// URL Hry (z url linku?)
	 *
	 * @return	array|NULL
	 */
	public function getGameInfo($gameUrl)
	{
		$result = $this->database->query('SELECT * FROM gamelist_gameinfo WHERE display = ? AND url = ?', "show", $gameUrl);

		if($result && $result->getRowCount() == 1)
		{
			$data = $result->fetch();
			$data['categoryData'] = $this->getCategoryDataById($data['categoryId']);	// Array
			$data['categoryPool'] = $this->getCategoryPoolByGameId($data['id']);		// Multi-Array

			return $data;
		}

		return NULL;
	}
}

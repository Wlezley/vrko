<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Explorer;

class Katalog
{
	/** @var Nette\Database\Explorer */
	protected $database;

	public function __construct(Explorer $database)
	{
		$this->database = $database;
	}


	/** Get CATEGORY LIST
	 * @return	array|null
	 */
	public function getCategoryList()
	{
		$result = $this->database->query("SELECT * FROM gamelist_category WHERE `priority` > ? ORDER BY `priority` DESC", 0.0);

		if ($result && $result->getRowCount() > 0) {
			$data = [];

			foreach ($result->fetchAll() as $row) {
				$data[$row['id']] = $row;
				$data[$row['id']]['count'] = $this->getGamesCountByCategoryId($row['id']); // TEMP: Count override...
			}

			return $data;
		}

		return null;
	}

	/** Get CATEGORY DATA (by CATEGORY ID)
	 * @param	int $categoryId
	 * @return	array|null
	 */
	public function getCategoryDataById($categoryId)
	{
		$result = $this->database->query("SELECT * FROM gamelist_category WHERE id = ?", $categoryId);

		if ($result && $result->getRowCount() == 1) {
			$data = $result->fetch();
			$data['count'] = $this->getGamesCountByCategoryId($data['id']); // TEMP: Count override...

			return $data;
		}

		return null;
	}

	/** Get CATEGORY DATA (by URL)
	 * @param	string $categoryUrl
	 * @return	array|null
	 */
	public function getCategoryDataByUrl($categoryUrl)
	{
		$result = $this->database->query("SELECT * FROM gamelist_category WHERE url = ?", $categoryUrl);

		if ($result && $result->getRowCount() == 1) {
			$data = $result->fetch();
			$data['count'] = $this->getGamesCountByCategoryId($data['id']); // TEMP: Count override...

			return $data;
		}

		return null;
	}

	/** Get CATEGORY POOL (by GAME ID)
	 * @param	int $gameId
	 * @return	array|null
	 */
	public function getCategoryPoolByGameId($gameId)
	{
		$result = $this->database->query("SELECT categoryId FROM gamelist_category_pool WHERE gameId = ?", $gameId);

		if ($result && $result->getRowCount() > 0) {
			$categoryPool = [];

			foreach ($result->fetchAll() as $row) {
				$categoryPool[$row['categoryId']] = $this->getCategoryDataById($row['categoryId']);
			}

			return $categoryPool;
		}

		return null;
	}

	/** Get GAMES COUNT in CATEGORY (by CATEGORY ID) - SIMPLE
	 * @param	int $categoryId
	 * @return	int
	 */
	public function getGamesCountByCategoryId_SIMPLE($categoryId)
	{
		if ($categoryId == 0) {
			$result = $this->database->query("SELECT COUNT(DISTINCT gameId) AS count FROM gamelist_category_pool");
		} else {
			$result = $this->database->query("SELECT COUNT(*) AS count FROM gamelist_category_pool WHERE categoryId = ?", $categoryId);
		}

		if ($result && $result->getRowCount() == 1) {
			return $result->fetch()['count'];
		}

		return 0;
	}

	/** Get GAMES COUNT in CATEGORY (by CATEGORY ID) - COMPLEX
	 * @param	int $categoryId
	 * @return	int
	 */
	public function getGamesCountByCategoryId_COMPLEX($categoryId)
	{
		if ($categoryId == 0) {
			$poolQR = $this->database->query("SELECT DISTINCT gameId FROM gamelist_category_pool");
		} else {
			$poolQR = $this->database->query("SELECT gameId FROM gamelist_category_pool WHERE categoryId = ?", $categoryId);
		}

		if ($poolQR && $poolQR->getRowCount() > 0) {
			$gameIds = [];

			foreach ($poolQR->fetchAll() as $row) {
				$gameIds[] = $row['gameId'];
			}

			$gameQR = $this->database->query("SELECT COUNT(*) AS count FROM gamelist_gameinfo WHERE display = ? AND id IN(?)", "show", $gameIds);

			if ($gameQR && $gameQR->getRowCount() == 1) {
				return $gameQR->fetch()['count'];
			}
		}

		return 0;
	}

	/** Get GAMES COUNT in CATEGORY (by CATEGORY ID) - COMPATIBILITY HANDLER
	 * @param	int $categoryId
	 * @return	int
	 */
	public function getGamesCountByCategoryId($categoryId)
	{
		//return $this->getGamesCountByCategoryId_SIMPLE($categoryId);
		return $this->getGamesCountByCategoryId_COMPLEX($categoryId);
	}

	/** Get GAME LIST (by CATEGORY ID)
	 * @param	int $categoryId
	 * @param	string $page
	 * @param	string $limit
	 *
	 * @return	array|null
	 */
	public function getGamesByCategory($categoryId = null, $page = 1, $limit = 20)
	{
		if ($categoryId == null) {
			$result = $this->database->query("SELECT id, url, fullName, imageMain, categoryId FROM gamelist_gameinfo WHERE display = ?", "show");
		} else {
			$poolQR = $this->database->query("SELECT gameId FROM gamelist_category_pool WHERE categoryId = ?", $categoryId);
			$gameIds = [];

			if ($poolQR && $poolQR->getRowCount() > 0) {
				foreach ($poolQR->fetchAll() as $row) {
					$gameIds[] = $row['gameId'];
				}
			}

			$result = $this->database->query("SELECT id, url, fullName, imageMain, categoryId FROM gamelist_gameinfo WHERE display = ? AND id IN(?)", "show", $gameIds);
		}

		if ($result && $result->getRowCount() > 0) {
			$data = [];

			foreach ($result->fetchAll() as $row) {
				$data[$row['id']] = $row;
				$data[$row['id']]['mainCategory'] = $this->getCategoryDataById($row['categoryId'])['url'];
			}

			return $data;
		}

		return null;
	}

	/** Get GAME DATA (by GAME URL)
	 * @param	string $gameUrl
	 * @return	array|null
	 */
	public function getGameInfo($gameUrl)
	{
		$result = $this->database->query("SELECT * FROM gamelist_gameinfo WHERE display = ? AND url = ?", "show", $gameUrl);

		if ($result && $result->getRowCount() == 1) {
			$data = $result->fetch();
			$data['categoryData'] = $this->getCategoryDataById($data['categoryId']);	// Array
			$data['categoryPool'] = $this->getCategoryPoolByGameId($data['id']);		// Multi-Array

			return $data;
		}

		return null;
	}

	/** Get GAME DATA (by GAME ID)
	 * @param	int $gameId
	 *
	 * @return	array|null
	 */
	public function getGameInfoById($gameId)
	{
		$result = $this->database->query("SELECT * FROM gamelist_gameinfo WHERE id = ?", $gameId);

		if ($result && $result->getRowCount() == 1) {
			$data = $result->fetch();
			$data['categoryData'] = $this->getCategoryDataById($data['categoryId']);	// Array
			$data['categoryPool'] = $this->getCategoryPoolByGameId($data['id']);		// Multi-Array

			return $data;
		}

		return null;
	}

	/** SAVE GAME DATA
	 * @param	array $param
	 *
	 * @return	int|null
	 */
	public function saveGameInfo(array $param): ?int
	{
		$data = $param;

		if (empty($data['url'])) {
			$data['url'] = Nette\Utils\Strings::webalize($data['fullName']);
		}

		$data['categoryId'] = $data['categoryPool'][0];
		$data['display'] = ($data['display'] === "show") ? "show" : "hide";
		unset($data['categoryPool']);

		if (empty($data['id'])) { // INSERT
			unset($data['id']);
			$result = $this->database->table("gamelist_gameinfo")->insert($data);

			if ($result->id) {
				$this->database->query("DELETE FROM gamelist_category_pool WHERE gameId = ?", $result->id);

				foreach ($param['categoryPool'] as $categoryId) {
					if (!empty($categoryId)) {
						$this->database->table("gamelist_category_pool")->insert([
							"gameId" => $result->id,
							"categoryId" => $categoryId,
						]);
					}
				}

				return (int)$result->id;
			}
		} else { // UPDATE
			$result = $this->database->table("gamelist_gameinfo")->where("id = ?", $param['id'])->update($data);

			$this->database->query("DELETE FROM gamelist_category_pool WHERE gameId = ?", $param['id']);

			foreach ($param['categoryPool'] as $categoryId) {
				if (!empty($categoryId)) {
					$this->database->table("gamelist_category_pool")->insert([
						"gameId" => $param['id'],
						"categoryId" => $categoryId,
					]);
				}
			}
		}

		return null;
	}

	/** Delete GAME (by GAME ID)
	 * @param	int $gameId
	 *
	 * @return	array|null
	 */
	public function deleteGame($gameId)
	{
		$this->database->query("DELETE FROM gamelist_gameinfo WHERE id = ?", $gameId);
		$this->database->query("DELETE FROM gamelist_category_pool WHERE gameId = ?", $gameId);
	}

}

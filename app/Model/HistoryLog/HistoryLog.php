<?php

declare(strict_types=1);

namespace App\Model\HistoryLog;

use Nette;
use App\Model;
use Nette\Utils\Json;
use Nette\Utils\ArrayHash;
use Nette\Database\Explorer;
use Tracy\Debugger;
use Carbon\Carbon;


class HistoryLog
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var int */
	protected $userID;

	public function __construct(Explorer $database)
	{
		$this->database = $database;
	}

	/*
	LEVEL:				ACTION:				TYPE:
		info				new					cron
		warning				add					system
		error				edit				contract
		debug				update				product_item
		n/a <DEFAULT>		delete				file
							remove				webcam
							send				signature
							print				warehouse
							upload				status
							unk <DEFAULT>		unk <DEFAULT>
	*/

	// user, level, action, type, subject, description, data
	public function writeLogIssueRAW($user = 0, $level = 'n/a', $action = 'unk', $type = 'unk', $subject = NULL, $description = "", $data = NULL)
	{
		return $this->database->table('log_history')->insert([
			//'id'			=> //AUTOINCREMENT
			//'date'		=> Carbon::now()->format('Y-m-d H:i:s'), // AUTO: This field is handled by database automatically
			'user'			=> $user,			// 0 = SYSTEM
		    'level'			=> $level,			// Options: info, warning, error, debug, n/a
			'action'		=> $action,			// Options: new, add, edit, update, delete, remove, send, print, upload, unk
			'type'			=> $type,			// Options: cron, system, contract, product_item, file, webcam, signature, warehouse, status, unk
			'subject'		=> $subject,		// ID of the item to which the listing applies (For example - productid, contractid, etc...), default is NULL
			'description'	=> $description,	// VARCHAR (255)
			'data'			=> $data,			// TODO: Array to JSON conversion!!!
		]);
	}

	public function readLogIssueRAW($id)
	{
		return;
	}

	public function getLogList($page = 1, $type = NULL, $action = NULL, $level = NULL)
	{
		return;
	}

	// COMMON ---->>
	public function log_Info($description, $data)
	{
		return $this->writeLogIssueRAW(0, 'info', 'unk', 'system', NULL, $description, $data);
	}
	public function log_Warning($description, $data)
	{
		return $this->writeLogIssueRAW(0, 'warning', 'unk', 'system', NULL, $description, $data);
	}
	public function log_Error($description, $data)
	{
		return $this->writeLogIssueRAW(0, 'error', 'unk', 'system', NULL, $description, $data);
	}
	public function log_Debug($description, $data)
	{
		return $this->writeLogIssueRAW(0, 'debug', 'unk', 'system', NULL, $description, $data);
	}
	// <<---- COMMON


	// SYSTEM ---->>
	public function log_UpdateCron($fceName, $subject = NULL, $data = NULL)
	{
		return $this->writeLogIssueRAW(0, 'info', 'update', 'cron', $subject, $fceName, $data);
	}
	// <<---- SYSTEM


	// ZAKAZKY ---->>
	public function log_UpdateContractStatus($user, $zakazka, $status, $affectedRows = 0)
	{
		return $this->writeLogIssueRAW($user, 'info', 'update', 'contract', $zakazka, 'ZAKAZKY/setZakazkaStatus('.$zakazka.', '.$status.')', 'Rows: '.$affectedRows);
	}

	public function log_UpdateContractItemCount($user, $fceName, $zakazka, $polozka, $affectedRows = 0)
	{
		return $this->writeLogIssueRAW($user, 'info', 'update', 'product_item', $zakazka, 'ZAKAZKY/'.$fceName.'('.$zakazka.', '.$polozka.')', 'Rows: '.$affectedRows);
	}

	/*
	addZakazkaPolozkaByPid
	subZakazkaPolozkaByPid
	delZakazkaPolozkaByPid
	*/
	// <<---- ZAKAZKY

}

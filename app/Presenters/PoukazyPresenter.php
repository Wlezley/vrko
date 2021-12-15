<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;

use App\Model;
use App\Model\Voucher;

use App\Forms;

use Nette\Utils\Json;
use Nette\Utils\ArrayHash;
use Nette\Database\Explorer;
use Tracy\Debugger;


final class PoukazyPresenter extends BasePresenter
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Forms\IVoucherFormFactory @inject */
	public $VoucherForm;

	/** @var Voucher\Voucher */
	private $voucher;

	public function __construct(Explorer $database,
								Voucher\Voucher $voucher)
	{
		$this->database = $database;
		$this->voucher = $voucher;
	}

	public function startup()
	{
		parent::startup();
	}

	// Komponenta VoucherForm
	protected function createComponentVoucherForm()
	{
		$form = $this->VoucherForm->create();

		$form->onUserSave[] = function ($form, $values) {
			if(isset($values['redirectUrl']) && !empty($values['redirectUrl']))
			{
				$this->flashMessage('CHECK PASSED OK!', 'success');
				header("Location: " . $values['redirectUrl'], true, 302);
			}
			else
			{
				$this->flashMessage('RedirectURL: Hodnota je chybná! Zkuste to prosím později.', 'danger');
			}
		};

		$form->onError[] = function ($form, $message) {
			$this->flashMessage($message, 'danger');
		};

		return $form;
	}

	//public function actionDefault()
	public function renderDefault()
	{
		$this->template->debug = "N/A";
	}

	/* ############################################################################ */
	private function obfuscateEmail($email)
	{
		$em = explode("@", $email);
		$name = implode('@', array_slice($em, 0, count($em) - 1));
		$len = (int)floor(strlen($name) / 2);
		return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);
	}
	private function getUserIpAddr()
	{
		if(!empty($_SERVER['HTTP_CLIENT_IP']))
			return $_SERVER['HTTP_CLIENT_IP'];

		if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
			return $_SERVER['HTTP_X_FORWARDED_FOR'];

		return $_SERVER['REMOTE_ADDR'];
	}
	/* ############################################################################ */

	public function actionStatus()
	//public function renderStatus()
	{
		// COMGATE IP CHECK
		if($this->getUserIpAddr() != "89.185.236.55")
		{
			return $this->redirect('Homepage:');
		}

		// POST KEYS CHECK
		$keys = ['merchant','test','price','curr','label','refId','cat','method','email','transId','secret','status','fee','vs'];
		foreach($keys as $key)
		{
			if(!isset($_POST[$key]))
			{
				/*throw new \InvalidArgumentException("One or more post keys missing. NECESSARY POST KEY WAS NOT FOUND!"); // Production
				//throw new \InvalidArgumentException("NECESSARY POST KEY '".$key."' WAS NOT FOUND!"); // Debug
				return false;*/
				return $this->redirect('Homepage:');
			}
		}

		// DATA COLLECTOR - TODO: Use a foreach, maybe?
		$data = [
			'merchant'	=> $_POST['merchant'],
			'test'		=> $_POST['test'],
			'price'		=> $_POST['price'],
			'curr'		=> $_POST['curr'],
			'label'		=> $_POST['label'],
			'refId'		=> $_POST['refId'],
			'cat'		=> $_POST['cat'],
			'method'	=> $_POST['method'],
			'email'		=> $_POST['email'],
			//'transId'	=> $_POST['transId'],
			'secret'	=> $_POST['secret'],
			'status'	=> $_POST['status'],
			'fee'		=> NULL, //$_POST['fee'],
			'vs'		=> $_POST['vs'],
		];

		// MySQL UPDATE (INSERT)
		$result = $this->database->query('UPDATE comgate_payments SET', $data, 'WHERE transId = ?', $_POST['transId']);
		/*if($result && $result->getRowCount() == 0)
		{
			$this->database->table('comgate_payments')->insert($data);
		}*/

		if($data['status'] == "PAID")
		{
			$rawPrice = ($data['price'] / 100) * (-1);
			$vatPrice = ($data['price'] / 100) * (-1);

			// COMPLETE ORDER
			$orderId = $data['refId'];
			$this->voucher->completeOrder($orderId);
		}
	}

	//public function actionResult()
	public function renderResult($transId, $refId)
	{
		$this->template->status = "ERROR";

		$result = $this->database->query('SELECT * FROM comgate_payments WHERE transId = ? AND refId = ? LIMIT 1', $transId, $refId);

		if($result && $result->getRowCount() == 1)
		{
			$data = $result->fetch();
			$this->template->data = $data;
			$this->template->status = $data->status;
			$this->template->email = $this->obfuscateEmail($data->email);
		}

		switch($this->template->status)
		{
			case 'PAID':		$this->flashMessage('Platba byla přijata.',				'success');	break;
			case 'PENDING':		$this->flashMessage('Platba se zpracovává.',			'info');	break;
			case 'CANCELLED':	$this->flashMessage('Platba byla zrušena.',				'warning');	break;
			case 'ERROR':		$this->flashMessage('CHYBA: Položka nebyla nalezena.',	'danger');	break;
		}
	}
}

<?php

//declare(strict_types=1);

namespace App\Model\Voucher;

use Latte;
use Nette;
use App\Model;

use Nette\Utils\Random;
use Nette\Database\Explorer;

// DATE / TIME
use Carbon\Carbon;

// CREATE PDF
use Mpdf\Mpdf as mPDF;
use Nette\Application\UI\ITemplateFactory;

// SEND MAIL
use Nette\Mail;


class Voucher
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Nette\Mail\Mailer @inject */
	public $mailer;

	public function __construct(Explorer $database,
								Mail\Mailer $mailer)
	{
		$this->database = $database;
		$this->mailer = $mailer;
	}

	// ######################################################

	/** Vygeneruje nahodny ciselny kod
	 * @param	integer			$size
	 * @param	string|null		$table
	 * @param	string|null		$field
	 *
	 * @return	int|null
	 */
	public function getRandomCode($size, $table = null, $field = null)
	{
		if($table == null || $field == null)
		{
			return Random::generate($size, '1-9');
		}

		$randomCode = null;
		$counter = 0;
		$limit = 10;

		for($counter; $counter < $limit; $counter++)
		{
			$randomCode = Random::generate($size, '1-9');
			$result = $this->database->query('SELECT * FROM `'.$table.'` WHERE ? = ? LIMIT 1', $field, $randomCode);
			if(!isset($result) || $result->getRowCount() == 0)
			{
				break;
			}
		}

		return ($counter == $limit) ? str_repeat('9', $size) : $randomCode;
	}

	// ######################################################

	/** Vygeneruje nahodny ciselny kod EAN-13 (vcetne CHECKSUM)
	 * @param	string			$prefix
	 * @param	string|null		$table
	 * @param	string|null		$field
	 *
	 * @return	string|null
	 */
	public function getRandomEAN13($prefix = '', $table = null, $field = null)
	{
		// TODO: Check if $prefix is STRING of DIGITS (empty string allowed)

		$size = 12 - strlen($prefix);
		$charlist = empty($prefix) ? '1-9' : '0-9';

		if($size <= 0)
			return null;

		if($table == null || $field == null)
			return $this->getChecksum_EAN13($prefix . (string)Random::generate($size, $charlist));

		$randomCode = null;
		$counter = 0;
		$limit = 10;

		for($counter; $counter < $limit; $counter++)
		{
			$randomCode = $this->getChecksum_EAN13($prefix . (string)Random::generate($size, $charlist));
			$result = $this->database->query('SELECT * FROM `'.$table.'` WHERE ? = ? LIMIT 1', $field, $randomCode);
			if(!isset($result) || $result->getRowCount() == 0)
			{
				break;
			}
		}

		return ($counter == $limit) ? $this->getChecksum_EAN13($prefix . str_repeat('9', $size)) : $randomCode;
	}

	// ######################################################

	/** GET BARCODE EAN-8 + CHECKSUM (LAST NUMBER)
	 * @param	string		$digits		// 7 DIGITS as string
	 *
	 * @return	string					// 8 DIGITS as string (with CHECKSUM)
	 */
	function getChecksum_EAN8($barCode)
	{
		$sum = 0;
		$arr = str_split($barCode, 1);

		for($i = 0; $i < count($arr); $i++)
		{
			if($i === 0 || $i % 2 === 0)
			{
				$arr[$i] = $arr[$i] * 3;
			}
			$sum += $arr[$i];
		}
		$check_digit = (10 - ($sum % 10)) % 10;
		return $barCode . $check_digit;
	}

	// ######################################################

	/** GET BARCODE EAN-13 + CHECKSUM (LAST NUMBER)
	 * @param	string		$digits		// 12 DIGITS as string
	 *
	 * @return	string					// 13 DIGITS as string (with CHECKSUM)
	 */
	function getChecksum_EAN13($digits)
	{
		// First change digits to a string so that we can access individual numbers
		$digits = (string)$digits;
		// 1. Add the values of the digits in the even-numbered positions: 2, 4, 6, etc.
		$even_sum = $digits[1] + $digits[3] + $digits[5] + $digits[7] + $digits[9] + $digits[11];
		// 2. Multiply this result by 3.
		$even_sum_three = $even_sum * 3;
		// 3. Add the values of the digits in the odd-numbered positions: 1, 3, 5, etc.
		$odd_sum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8] + $digits[10];
		// 4. Sum the results of steps 2 and 3.
		$total_sum = $even_sum_three + $odd_sum;
		// 5. The check character is the smallest number which, when added to the result in step 4, produces a multiple of 10.
		$next_ten = (ceil($total_sum/10))*10;
		$check_digit = $next_ten - $total_sum;
		return $digits . $check_digit;
	}

	// #############################################################################################################################

	/** Vytvoření objednávky včetně order_items
	 * @param	double		$price_total	// Cena celkem
	 * @param	integer		$count			// Pocet poukazu
	 * @param	string		$z_name			// Zakaznik / Jmeno
	 * @param	string		$z_surname		// Zakaznik / Prijmeni
	 * @param	string		$z_email		// Zakaznik / Email
	 * @param	string		$z_phone		// Zakaznik / Telefon
	 *
	 * @return	integer|null				// ID Objednavky
	 */
	function createOrder($price_total, $count, $z_name, $z_surname, $z_email, $z_phone)
	{
		if($price_total <= 0 || $count <= 0 || $count > 10)
			return null;

		$orderData = [
		  //'id'			=> $orderId,		// ID Objednavky
		  //'invoice_id'	=> null,			// Unknown
		  //'date_created'	=> Carbon::now()->format('Y-m-d H:i:s'),	// CURRENT_TIMESTAMP
			'price_total'	=> $price_total,	// Cena celkem
			'z_name'		=> $z_name,			// Zakaznik / Jmeno
			'z_surname'		=> $z_surname,		// Zakaznik / Prijmeni
			'z_email'		=> $z_email,		// Zakaznik / Email
			'z_phone'		=> $z_phone,		// Zakaznik / Telefon
		];
		$result = $this->database->table('orders')->insert($orderData);
		$orderId = $result->id;

		if(!isset($orderId) || $orderId <= 0)
			return null;

		$orderItems = [];
		for($i = 0; $i < $count; $i++)
		{
			$orderItems[] = [
			  //'id'			=> null,					// ID Polozky
				'order_id'		=> $orderId,				// ID Objednavky
				'item_name'		=> "Herní poukaz VRko.cz",	// Nazev polozky
				'item_count'	=> 1,						// Pocet kusu
				'item_price'	=> ($price_total / $count),	// Cena za kus
			];
		}
		$result = $this->database->table('order_items')->insert($orderItems);

		return $orderId;
	}

	/** Dokončení objednávky (a vygenerování voucherů)
	 * @param	integer		$orderId		// ID Objednávky
	 *
	 * @return	bool|null
	 */
	function completeOrder($orderId)
	{
		// INIT DATA
		$todayDate = Carbon::now()->format('Y-m-d');
		$todayDatetime = Carbon::now()->format('Y-m-d H:i:s');

		// GET ORDER
		$result = $this->database->query('SELECT * FROM orders WHERE id = ? LIMIT 1', $orderId);
		if(!isset($result) || $result->getRowCount() != 1)
			return false;

		$orderData = $result->fetch();
		$price_total = $orderData['price_total'];

		// GET ORDER_ITEMS
		$result = $this->database->query('SELECT * FROM order_items WHERE order_id = ?', $orderId);
		if(!isset($result) || $result->getRowCount() == 0)
			return false;

		$orderItems_count = $result->getRowCount();
		$orderItems = $result->fetchAll();
		$item_price = ($price_total / $orderItems_count);

		// GENERATE VOUCHER DATA
		$vouchers = [];
		foreach($orderItems as /*$key =>*/ $item)
		{
			$result = $this->database->query('SELECT * FROM voucher WHERE order_id = ? AND order_item_id = ?', $orderId, $item['id']);
			if(isset($result) && $result->getRowCount() != 0)
				continue;

			$voucherID = $this->getRandomCode(6, 'voucher', 'voucher_id');
		  //$voucherEAN = '6660' . $this->getRandomCode(9, 'voucher', 'voucher_ean');
			$voucherEAN = $this->getRandomEAN13('6660', 'voucher', 'voucher_ean');
			$voucherData = [
			  //'id'			=> null,			// UID (možná UID pro voucher EAN6?)
				'order_id'		=> $orderId,		// ID Objednavky
				'order_item_id'	=> $item['id'],		// ID Polozky Objednavky
				'voucher_id'	=> $voucherID,		// EAN-6
				'voucher_ean'	=> $voucherEAN,		// EAN-13
				'voucher_date'	=> $todayDate,		// DATUM
			];
			$result = $this->database->table('voucher')->insert($voucherData);

			$vouchers[] = $voucherData;
		}

		// GET VOUCHER DATA
		$result = $this->database->query('SELECT * FROM voucher WHERE order_id = ?', $orderId);
		if(!isset($result) || $result->getRowCount() == 0 || $result->getRowCount() != $orderItems_count)
			return false;

		$voucherDataAll = $result->fetchAll();

		// CREATE INVOICE (id & descriptor)
		$invoiceId = null;
		$result = $this->database->query('SELECT * FROM invoice WHERE order_id = ? LIMIT 1', $orderId);
		if(isset($result) && $result->getRowCount() == 1)
		{
			$invoiceId = $result->fetch()->id;
		}
		else
		{
			$invoiceData = [
			  //'id'			=> null,			// ID Faktury / Uctenky
				'order_id'		=> $orderId,		// ID Objednavky
			  //'date_created'	=> $todayDatetime,	// Datum vytvoreni 
				'date_payed'	=> $todayDatetime,	// Datum a cas uhrady
			];
			$result = $this->database->table('invoice')->insert($invoiceData);
			$invoiceId = $result->id;
		}
		if(!isset($invoiceId) || $invoiceId <= 0)
			return false;

		// PDF: RENDER SEED
		$seedPDF = $this->getSeedPDF($invoiceId, $orderItems_count, $item_price, $price_total, $todayDatetime);

		// VOUCHER POST-PROCESS
		$files = [];
		$files['uctenka-'. $invoiceId . '.pdf'] = $seedPDF;
		foreach($voucherDataAll as $voucher)
		{
			// PDF: RENDER VOUCHER FILES
			$voucherPDF = $this->getVoucherPDF($voucher['voucher_id'], $voucher['voucher_ean'], $voucher['voucher_date']);
			$files['voucher-' . $voucher['voucher_ean'] . '.pdf'] = $voucherPDF;
		}

		// VOUCHER COUNT SPELLING
		$spelling = "herních poukazů"; // Default
		switch((int)$orderItems_count)
		{
			case 1: $spelling = "herní poukaz"; break;
			case 2: case 3: case 4: $spelling = "herní poukazy"; break;
		}

		// SEND MAIL
		$mailTemplateData = [
			'orderId'		=> $orderId,
			'pocetPoukazu'	=> (int)$orderItems_count,
			'spelling'		=> $spelling,
		];
		$this->sendMail($orderData['z_email'], "@emailSuccess", "Herní poukaz VRko.cz", $files, $mailTemplateData);

		// SEND ADMIN MAIL
		$mailTemplateDataAdmin = [
			'orderId'		=> $orderId,
			'pocetPoukazu'	=> (int)$orderItems_count,
			'vouchers'		=> $vouchers,
			'todayDatetime'	=> $todayDatetime,
		];
		$this->sendMail("kozeluh@zetcomp.cz", "@emailAdmin", "Herní poukaz VRko.cz", $files, $mailTemplateDataAdmin, false);

		// SUCCESS
		return true;
	}

	// #############################################################################################################################

	// ### PDF - SEED DATA ###
	function getSeedPDF($invoiceId, $orderItems_count, $item_price, $price_total, $date)
	{
		$dataHTML = [
			'seedId'		=> $invoiceId,
			'voucherPocet'	=> $orderItems_count,
			'priceItem'		=> $item_price,
			'priceTotal'	=> $price_total,
			'dateTodayFull'	=> $date,
			'logoImg'		=> __DIR__ . "/../../../www/img/logo/vrko-ruzova-horizontal.png"
		];

		$latte = new Latte\Engine;
		$template = $latte->renderToString(__DIR__ . "/@seed.latte", $dataHTML);
		$stylesheet = $latte->renderToString(__DIR__ . "/@seed.css");

		$pdf = new mPDF([
			'mode'					=> 'utf-8',		// Charset
			'format'				=> 'A4',		// Page format
			'tempDir'				=> '../temp',	// Temp directory
			'ignore_invalid_utf8'	=> true,		// 
			//'useOnlyCoreFonts'	=> true,		// 
		]);

		$pdf->SetTitle("Účtenka VRko.cz");
		$pdf->SetAuthor("https://vrko.cz/");
		$pdf->SetDisplayMode("fullpage");

		//$pdf->WriteHTML($template);		// \Mpdf\HTMLParserMode::DEFAULT_MODE
		$pdf->WriteHTML($stylesheet, 1);	// \Mpdf\HTMLParserMode::HEADER_CSS
		$pdf->WriteHTML($template, 2);		// \Mpdf\HTMLParserMode::HTML_BODY

		return $pdf->Output("uctenka-" . $invoiceId . ".pdf", "S");
	}

	// ### PDF - VOUCHER DATA ###
	function getVoucherPDF($voucherId, $voucherEAN, $voucherDate)
	{
		$dataHTML = [
			'voucherId'		=> $voucherId,
			'voucherEAN'	=> $voucherEAN,
			'voucherDate'	=> $voucherDate
		];

		$dataCSS = [
			'backgroundImg'	=> __DIR__ . "/../../../www/img/poukazy/poukaz_blank.png"
		];

		$latte = new Latte\Engine;
		$template = $latte->renderToString(__DIR__ . "/@voucher.latte", $dataHTML);
		$stylesheet = $latte->renderToString(__DIR__ . "/@voucher.css", $dataCSS);

		$pdf = new mPDF([
			'mode'					=> 'utf-8',			// Charset
			//'format'				=> [175.0, 86.0],	// Page format
			'format'				=> [230.0, 112.5],	// Page format
			'tempDir'				=> '../temp',		// Temp directory
			'ignore_invalid_utf8'	=> true,
			'margin_left'			=> 0,
			'margin_right'			=> 0,
			'margin_top'			=> 0,
			'margin_bottom'			=> 0,
		]);

		$pdf->SetTitle("Herní poukaz VRko.cz");
		$pdf->SetAuthor("https://vrko.cz/");
		$pdf->SetDisplayMode("fullpage");

		//$pdf->WriteHTML($template);		// \Mpdf\HTMLParserMode::DEFAULT_MODE
		$pdf->WriteHTML($stylesheet, 1);	// \Mpdf\HTMLParserMode::HEADER_CSS
		$pdf->WriteHTML($template, 2);		// \Mpdf\HTMLParserMode::HTML_BODY

		/*$pdf->SetProtection([
			//'copy',			// 
			'print',			// 
			//'modify',			// 
			//'annot-forms',	// 
			//'fill-forms',		// 
			//'extract',		// 
			//'assemble',		// 
			'print-highres',	// 
		]);*/

		return $pdf->Output("voucher-" . $voucherEAN . ".pdf", "S");
	}

	// ### SEND MAIL ###
	private function sendMail($recipient, $templateName, $subject, $files, $data, $sendCopy = true)
	{
		$latte = new Latte\Engine;
		$template = $latte->renderToString(__DIR__ . "/" . $templateName . ".latte", $data);

		$mailMsg = new Mail\Message();
		$mailMsg->setFrom("Vouchery VRko.cz <info@vrko.cz>");
		$mailMsg->addTo($recipient); // TODO: EMAIL Validator: $recipient
		if ($sendCopy) {
			$mailMsg->addBcc("faktura@vrko.cz");
		}
		$mailMsg->setSubject($subject);
		foreach($files as $fileName => $fileData) {
			$mailMsg->addAttachment($fileName, $fileData, "application/pdf");
		}
		$mailMsg->setHtmlBody($template, __DIR__ . "/../../../www/img/email/");

		$this->mailer->send($mailMsg);
	}
}

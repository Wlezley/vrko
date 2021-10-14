<?php

echo "status.php<br>\n";
echo "-- DEBUG START --<br>\n";

$fp = fopen("./temp/data-status.log", "a");
fwrite($fp, "START\n");

// HTTP_REFERER: 
// HTTP_REFERER_HOST: 
//if(isset($_SERVER['HTTP_REFERER']))
{
	echo        "HTTP_REFERER: " . $_SERVER['HTTP_REFERER'] . "<br>\n";
	fwrite($fp, "HTTP_REFERER: " . $_SERVER['HTTP_REFERER'] . "\n");

	echo        "HTTP_REFERER_HOST: " . parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) . "<br>\n";
	fwrite($fp, "HTTP_REFERER_HOST: " . parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) . "\n");
}

foreach ($_POST as $key => $value) {
	echo "POST [".htmlspecialchars($key)."] (".htmlspecialchars($value).")<br>\n";
	fwrite($fp, "POST [".htmlspecialchars($key)."] (".htmlspecialchars($value).")\n");
}

foreach ($_GET as $key => $value) {
	echo "GET [".htmlspecialchars($key)."] (".htmlspecialchars($value).")<br>\n";
	fwrite($fp, "GET [".htmlspecialchars($key)."] (".htmlspecialchars($value).")\n");
}

fwrite($fp, "STOP\n\n");
fclose($fp);

echo "-- DEBUG END --\n";

/*
HTTP_REFERER: 
HTTP_REFERER_HOST: 
POST [merchant] (151591)
POST [test] (true)
POST [price] (40000)
POST [curr] (CZK)
POST [label] (Voucher VRKO)
POST [refId] (123456)
POST [cat] (PHYSICAL)
POST [method] (CARD_CZ_CSOB_2)
POST [email] (kuzelicka@seznam.cz)
POST [transId] (DO97-U0HG-OFJX)
POST [secret] (ltMybbQ7gBUjocOgMlrlKL2LHSjGbFh9)
POST [status] (PAID)
POST [fee] (unknown)
POST [vs] (215796741)
*/
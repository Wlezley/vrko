<?php

echo "result.php<br>\n";
echo "-- DEBUG START --<br>\n";

$fp = fopen("./temp/data-result.log", "a");
fwrite($fp, "START\n");

// HTTP_REFERER: https://payments.comgate.cz/
// HTTP_REFERER_HOST: payments.comgate.cz
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
HTTP_REFERER: https://payments.comgate.cz/provider/testing/display/?id=DO97-U0HG-OFJX
HTTP_REFERER_HOST: payments.comgate.cz
GET [id] (DO97-U0HG-OFJX)
GET [refId] (123456)
*/

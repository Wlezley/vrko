<?php

declare(strict_types=1);

// REDIRECT FROM "/www" TO "/"
$regex = "!^/www!";
if(preg_match($regex, $_SERVER['REQUEST_URI']))
{
	//$webUrl = "http".((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')?"s":"")."://".$_SERVER['HTTP_HOST'];
	$uriTarget = preg_replace($regex, "", $_SERVER['REQUEST_URI']);
	//header("Location: " . $webUrl . $uriTarget, true, 301);
	header("Location: " . $uriTarget, true, 301);
	exit;
}

require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::boot()
	->createContainer()
	->getByType(Nette\Application\Application::class)
	->run();

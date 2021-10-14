<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;

		// STATIC PAGES
		$router->addRoute('o-herne',		'Homepage:oherne');
		$router->addRoute('faq',			'Homepage:faq');
		$router->addRoute('teambuilding',	'Homepage:teambuilding');
		$router->addRoute('vrko-pomaha',	'Homepage:vrkopomaha');
		$router->addRoute('katalog-her',	'Homepage:katalogher');
		$router->addRoute('cenik',			'Homepage:cenik');
		$router->addRoute('kontakt',		'Homepage:kontakt');

		// REDIRECTS
		$router->addRoute('zazitek',		'Homepage:zazitek');
		$router->addRoute('letak',			'Homepage:letak');

		// ZAJIMAVOSTI
		$router->addRoute('zajimavosti',	'Zajimavosti:default');
		$router->addRoute('chirurg',		'Zajimavosti:chirurg');
		$router->addRoute('vojak',			'Zajimavosti:vojak');
		$router->addRoute('historie',		'Zajimavosti:historie');
		$router->addRoute('predstava',		'Zajimavosti:predstava');
		$router->addRoute('skolnivyuka',	'Zajimavosti:skolnivyuka');
		$router->addRoute('lecba',			'Zajimavosti:lecba');
		$router->addRoute('hadanka',		'Zajimavosti:hadanka');

		// POUKAZY
		$router->addRoute('poukazy',									'Poukazy:default');
		$router->addRoute('payment-process/',							'Poukazy:status');
		$router->addRoute('poukazy/<transId>/<refId>/',					'Poukazy:result');

		// REZERVACE
		$router->addRoute('rezervace',									'Rezervace:default');
		$router->addRoute('potvrzeni-rezervace',						'Rezervace:authorize');

		// PARTIALS
		$router->addRoute('partials/',									'Partials:default');
		$router->addRoute('partials/selectday/<year>/<month>/',			'Partials:selectday');
		$router->addRoute('partials/selecthour/<year>/<month>/<day>/',	'Partials:selecthour');

		// HODNOCENI
		$router->addRoute('hodnoceni/<reviewHash>',						'Hodnoceni:default');
		$router->addRoute('hodnoceni-google/<reviewHash>',				'Hodnoceni:google');

		// KATALOG HER (GAME-LIST)
		$router->addRoute('hry',										'Katalog:default');
		$router->addRoute('hry/<category>',								'Katalog:category');
		$router->addRoute('hry/<category>/<game>',						'Katalog:gamepage');

		// DEFAULT
		//$router->addRoute('/', 'Homepage:default');
		$router->addRoute('<presenter>/<action>[/<id>]', 'Homepage:default');

		// ERRORS
		//$router->addRoute('404/', 'Error:404');

		return $router;
	}
}

<?php
/**
 * This file is part of the Symple framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\Http\Tests;

use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tholcomb\Symple\Http\HttpProvider;
use Tholcomb\Symple\Http\Tests\Controller\TestAbstractController;
use Tholcomb\Symple\Http\Tests\Controller\TestControllerA;
use Tholcomb\Symple\Http\Tests\Controller\TestControllerB;
use Tholcomb\Symple\Http\Tests\ControllerAgain\TestControllerC;
use Tholcomb\Symple\Logger\LoggerProvider;
use Tholcomb\Symple\Twig\TwigProvider;
use Twig\Environment;

class HttpTest extends TestCase {
	private function getContainer(): Container
	{
		$c = new Container();
		$c->register(new HttpProvider());
		$c->register(new LoggerProvider());

		return $c;
	}

	public function testAddController()
	{
		$c = $this->getContainer();
		$arr = [];
		HttpProvider::addController($c, TestControllerA::class, function () use (&$arr) {
			$arr['a'] = true;
			return new TestControllerA();
		});
		HttpProvider::addController($c, TestControllerB::class, function () use (&$arr) {
			$arr['b'] = true;
			return new TestControllerB();
		});
		$k = HttpProvider::getKernel($c);
		$k->handle(Request::create('/testA/test'));
		$this->assertTrue(isset($arr['a']), 'A not loaded');
		$this->assertFalse(isset($arr['b']), 'B loaded early');

		$k->handle(Request::create('/testB/test'));
		$this->assertTrue(isset($arr['b']), 'B not loaded');

		HttpProvider::addController($c, TestControllerC::class, function () {
			return new TestControllerC();
		});
		$res = $k->handle(Request::create('/testC/test'));
		$this->assertEquals(200, $res->getStatusCode());
	}

	public function testAbstractController()
	{
		$c = $this->getContainer();
		$c->register(new TwigProvider());
		TwigProvider::addTemplateDir($c, __DIR__ . '/templates/');
		$loaded = false;
		$urlLoaded = false;
		$c->extend(TwigProvider::KEY_ENVIRONMENT, function (Environment $env) use (&$loaded) {
			$loaded = true;
			return $env;
		});
		$c->extend(HttpProvider::KEY_URL_GENERATOR, function (UrlGeneratorInterface $url) use (&$urlLoaded) {
			$urlLoaded = true;
			return $url;
		});
		HttpProvider::addController($c, TestAbstractController::class, function () {
			return new TestAbstractController();
		});

		$res = HttpProvider::getKernel($c)->handle(Request::create('/abstract/json'));
		$this->assertFalse($loaded, 'Twig loaded early');
		$this->assertFalse($urlLoaded, 'UrlGenerator loaded early');
		$this->assertInstanceOf(JsonResponse::class, $res, 'Did not get JsonResponse');

		$arg = random_int(0, 9);
		$res = HttpProvider::getKernel($c)->handle(Request::create("/abstract/{$arg}"));
		$this->assertEquals($arg, $res->getContent(), 'Did not get arg');
		$this->assertTrue($loaded, 'Twig supposedly not loaded');

		$url = '/abstract/urlGen';
		$res = HttpProvider::getKernel($c)->handle(Request::create($url));
		$this->assertEquals(json_encode($url), $res->getContent(), 'Did not get correct URL');
		$this->assertTrue($urlLoaded, 'UrlGenerator supposedly not loaded');
	}

	public function testAbstractControllerMissingTwig()
	{
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessageMatches('/Twig environment/i');

		$ctrl = new TestAbstractController();
		$ctrl->testRender(1);
	}

	public function testRun()
	{
		$c = $this->getContainer();
		HttpProvider::addController($c, TestAbstractController::class, function () {
			return new TestAbstractController();
		});
		$c[HttpProvider::KEY_REQUEST] = function () {
			return Request::create('/abstract/json');
		};
		ob_start();
		HttpProvider::run($c);
		$res = ob_get_clean();
		$this->assertEquals(json_encode('data'), $res);
	}
}
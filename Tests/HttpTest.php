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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tholcomb\Symple\Core\Symple;
use Tholcomb\Symple\Core\Tests\ResetSymple;
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

	private function getContainerWithAbstract(): Container
	{
		$c = $this->getContainer();
		HttpProvider::addController($c, TestAbstractController::class, function () {
			return new TestAbstractController();
		});
		$c[HttpProvider::KEY_REQUEST] = function () {
			return Request::create('/abstract/exception');
		};

		return $c;
	}

	protected function tearDown(): void
	{
		ResetSymple::reset();
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
		$c = $this->getContainerWithAbstract();
		$c[HttpProvider::KEY_REQUEST] = function () {
			return Request::create('/abstract/json');
		};
		ob_start();
		HttpProvider::run($c);
		$res = ob_get_clean();
		$this->assertEquals(json_encode('data'), $res);
	}

	public function testException()
	{
		ResetSymple::reset();
		$c = $this->getContainerWithAbstract();
		$skipLog = !is_writable('/tmp');
		if ($skipLog) {
			$this->addWarning('/tmp not writable. skipping log test.');
		} else {
			$c['logger.path'] = '/tmp/' . uniqid('httpException_');
		}
		$res = HttpProvider::getKernel($c)->handle(HttpProvider::getRequest($c));
		$this->assertSame(403, $res->getStatusCode());
		if (!$skipLog) {
			$this->assertStringContainsString('AccessDeniedHttpException', file_get_contents($c['logger.path']));
			unlink($c['logger.path']);
		}
	}

	public function testExceptionDebug()
	{
		ResetSymple::reset();
		Symple::enableDebug();
		$c = $this->getContainerWithAbstract();

		$this->expectException(AccessDeniedHttpException::class);
		HttpProvider::getKernel($c)->handle(HttpProvider::getRequest($c));
	}

	public function testExceptionDebugJson()
	{
		ResetSymple::reset();
		Symple::enableDebug();
		$c = $this->getContainerWithAbstract();
		$c->extend(HttpProvider::KEY_REQUEST, function (Request $req) {
			$req->headers->set('Accept', 'application/json');

			return $req;
		});

		ob_start();
		HttpProvider::run($c);
		$res = ob_get_clean();
		$this->assertStringContainsString('AccessDeniedHttpException', $res);
	}
}
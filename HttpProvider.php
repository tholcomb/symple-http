<?php
/**
 * This file is part of the Symple framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\Http;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use Pimple\Container;
use Pimple\Exception\FrozenServiceException;
use Pimple\Psr11\ServiceLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Tholcomb\Symple\Core\AbstractProvider;
use Tholcomb\Symple\Core\Symple;
use Tholcomb\Symple\Http\Event\HttpEventProvider;
use Tholcomb\Symple\Logger\LoggerProvider;
use Tholcomb\Symple\Twig\TwigProvider;
use Tholcomb\Symple\Core\UnregisteredProviderException;
use function Tholcomb\Symple\Core\exists_and_registered;

class HttpProvider extends AbstractProvider {
	public const KEY_KERNEL = 'http.kernel';
	public const KEY_DISPATCHER = 'http.event_dispatcher';
	public const KEY_URL_GENERATOR = 'http.url_generator';
	public const KEY_REQUEST = 'http.request';
	public const KEY_SESSION = 'http.session';
	protected const NAME = 'http';

	public function register(Container $c)
	{
		parent::register($c);
		$c->register(new HttpEventProvider());

		$c[self::KEY_KERNEL] = function ($c) {
			return new HttpKernel($c['http.event_dispatcher'], $c['http.controller_resolver'], $c['http.request_stack']);
		};

		$c[self::KEY_DISPATCHER] = function ($c) { // EventDispatcherInterface
			$d = HttpEventProvider::getDispatcher($c);
			$d->addSubscriber($c['http.router_listener']);

			return $d;
		};

		$c['http.ctrl.dirs'] = [];

		$c['http.controller_resolver'] = function () { // ControllerResolverInterface
			return new LazyControllerResolver();
		};

		$c['http.file_locator'] = function () { // FileLocatorInterface
			return new FlexibleFileLocator();
		};

		$c['http.annotation_reader'] = function ($c) { // Reader
			return new CachedReader(new AnnotationReader(), $c['http.annotation_cache'], Symple::isDebug());
		};

		$c['http.annotation_cache'] = function () { // Cache
			return new ArrayCache();
		};

		$c['http.annotated_class_loader'] = function ($c) {
			return new AnnotatedClassLoader($c['http.annotation_reader']);
		};

		$c['http.route_loader'] = function ($c) { // LoaderInterface
			AnnotationRegistry::registerLoader('class_exists'); // Will be removed in doctrine/annotations 2.0
			return new AnnotationDirectoryLoader($c['http.file_locator'], $c['http.annotated_class_loader']);
		};

		$c['http.routes'] = function () {
			return new RouteCollection();
		};

		$c[self::KEY_REQUEST] = function () {
			return Request::createFromGlobals();
		};

		$c['http.context'] = function ($c) {
			$context = new RequestContext();
			return $context->fromRequest($c['http.request']);
		};

		$c['http.request_stack'] = function () {
			return new RequestStack();
		};

		$c['http.url_matcher'] = function ($c) {
			return new UrlMatcher($c['http.routes'], $c['http.context']);
		};

		$c['http.router_listener'] = function ($c) {
			$log = LoggerProvider::getLogger($c, 'router');
			return new RouterListener($c['http.url_matcher'], $c['http.request_stack'], $c['http.context'], $log);
		};

		$c[self::KEY_URL_GENERATOR] = function ($c) {
			return new UrlGenerator($c['http.routes'], $c['http.context'], LoggerProvider::getLogger($c, 'urlGen'));
		};

		$c[self::KEY_SESSION] = function () { // SessionInterface
			return new Session();
		};

		$c['http.error_file'] = __DIR__ . '/html/default_error.html';
	}

	public static function getKernel(Container $c): HttpKernel
	{
		if (!isset($c[self::KEY_KERNEL])) throw new UnregisteredProviderException(self::class);
		return $c[self::KEY_KERNEL];
	}

	public static function getRequest(Container $c): Request
	{
		if (!isset($c[self::KEY_REQUEST])) throw new UnregisteredProviderException(self::class);
		return $c[self::KEY_REQUEST];
	}

	public static function run(Container $c, bool $catch = true): void
	{
		$req = self::getRequest($c);
		$k = self::getKernel($c);
		try {
			$res = $k->handle($req, HttpKernelInterface::MASTER_REQUEST, $catch);
		} catch (\Exception $e) {
			$msg = 'Kernel threw exception';
			if ($catch === true) { // This shouldn't ever happen, but can if the ErrorHandler isn't registered
				try {
					if (LoggerProvider::isRegistered($c)) {
						LoggerProvider::getLogger($c)->error("{$msg}: '{$e->getMessage()}'");
					}
				} finally {
					Response::create(file_get_contents($c['http.error_file']), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
				}
				return;
			}
			throw new \RuntimeException($msg, 0, $e);
		}
		$res->send();
		$k->terminate($req, $res);
	}

	private static function addControllerDir(Container $c, string $dir): void
	{
		if (!is_dir($dir)) throw new \InvalidArgumentException("'$dir' is not directory");
		$locatorExt = function (FlexibleFileLocator $locator) use ($dir) {
			$locator->addPath($dir);
			return $locator;
		};
		try {
			$c->extend('http.file_locator', $locatorExt);
		} catch (FrozenServiceException $e) {
			$locatorExt($c['http.file_locator']);
		}

		$loaderExt = function (RouteCollection $routes, Container $c) use ($dir) {
			$lKey = 'http.route_loader';
			$loader = $c[$lKey];
			if (!$loader instanceof LoaderInterface) {
				throw new \LogicException(sprintf("Key '$lKey' not %s", LoaderInterface::class));
			}
			$routes->addCollection($loader->load($dir));

			return $routes;
		};
		try {
			$c->extend('http.routes', $loaderExt);
		} catch (FrozenServiceException $e) {
			$loaderExt($c['http.routes'], $c);
		}
	}

	private static function injectAbstractController(Container $c, AbstractController $ctrl): void
	{
		$services = ['url' => self::KEY_URL_GENERATOR];
		$loc = null;
		if (exists_and_registered(TwigProvider::class, $c)) {
			$services['twig'] = TwigProvider::KEY_ENVIRONMENT;
			$ctrl->setTwig(function () use (&$loc) { return $loc->get('twig'); });
		}
		$ctrl->setUrlGenerator(function () use (&$loc) { return $loc->get('url'); });
		$loc = new ServiceLocator($c, $services);
	}

	public static function addController(Container $c, string $class, callable $definition): void
	{
		if (!isset($c['http.ctrl.dirs'])) throw new UnregisteredProviderException(self::class);
		try {
			$file = (new \ReflectionClass($class))->getFileName();
		} catch (\ReflectionException $e) {
			throw new \RuntimeException("Reflection failed for class '$class'", 0, $e);
		}
		if ($file === false) throw new \RuntimeException("Could not get filename for class '$class'");
		$dir = dirname($file);
		if (!in_array($dir, $c['http.ctrl.dirs'])) {
			self::addControllerDir($c, $dir);
			$c['http.ctrl.dirs'] = array_merge($c['http.ctrl.dirs'], [$dir]);
		}

		$key = 'http.ctrl.' . $class;
		$c[$key] = $definition;
		$c->extend($key, function ($ctrl, Container $c) {
			if ($ctrl instanceof AbstractController) self::injectAbstractController($c, $ctrl);
			return $ctrl;
		});
		$extension = function (LazyControllerResolver $resolver, Container $c) use ($class, $key) {
			$loc = new ServiceLocator($c, ['ctrl' => $key]);
			$resolver->addController($class, function () use ($loc) {
				return $loc->get('ctrl');
			});
			return $resolver;
		};
		try {
			$c->extend('http.controller_resolver', $extension);
		} catch (FrozenServiceException $e) {
			$extension($c['http.controller_resolver'], $c);
		}
	}
}
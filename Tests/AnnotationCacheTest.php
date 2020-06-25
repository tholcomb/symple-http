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

use Doctrine\Common\Cache\FilesystemCache;
use Pimple\Container;
use Tholcomb\Symple\Core\Cache\SympleCacheInterface;
use Tholcomb\Symple\Core\Tests\FilesystemCacheTestAbstract;
use Tholcomb\Symple\Http\HttpProvider;
use Tholcomb\Symple\Http\Tests\Controller\TestControllerA;
use Tholcomb\Symple\Logger\LoggerProvider;

class AnnotationCacheTest extends FilesystemCacheTestAbstract {
	protected function getCache(): SympleCacheInterface
	{
		$c = new Container();
		$c->register(new LoggerProvider());
		$c->register(new HttpProvider());
		$c['http.annotation_cache'] = function () {
			return new FilesystemCache($this->path);
		};
		HttpProvider::addController($c, TestControllerA::class, function () {
			return new TestControllerA();
		});

		return $c['http.symple_cache'];
	}
}
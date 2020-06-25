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

use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\FlushableCache;
use Tholcomb\Symple\Core\Cache\SympleCacheInterface;

class SympleDoctrineCache implements SympleCacheInterface {
	private $cache;
	private $warmCb;

	public function __construct(FlushableCache $cache, ?callable $warmCb = null)
	{
		$this->cache = $cache;
		$this->warmCb = $warmCb;
	}

	public function clearCache(): void
	{
		$this->cache->flushAll();
	}

	public function warmCache(): void
	{
		if ($this->warmCb !== null) {
			call_user_func($this->warmCb);
			return;
		}
		throw new \LogicException(sprintf("Not implemented: '%s'", __METHOD__));
	}

	public function getCacheLocation(): ?string
	{
		if ($this->cache instanceof FilesystemCache) {
			return $this->cache->getDirectory();
		}

		return null;
	}
}
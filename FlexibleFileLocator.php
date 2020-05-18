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

use Symfony\Component\Config\FileLocator;

class FlexibleFileLocator extends FileLocator {
	public function addPath(string $path): void
	{
		if (!in_array($path, $this->paths)) $this->paths[] = $path;
	}
}
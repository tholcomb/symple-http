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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

class LazyControllerResolver extends ControllerResolver {
	private $controllers;

	public function __construct(array $controllers = [], LoggerInterface $logger = null)
	{
		$this->controllers = $controllers;
		parent::__construct($logger);
	}

	public function addController(string $class, callable $closure): void
	{
		$this->controllers[$class] = $closure;
	}

	protected function instantiateController(string $class)
	{
		if (!isset($this->controllers[$class])) return parent::instantiateController($class);
		$ctrl = $this->controllers[$class];
		if (is_callable($ctrl)) return $this->controllers[$class] = call_user_func($ctrl);
		if (is_object($ctrl)) return $ctrl;

		throw new \LogicException("Something went wrong");
	}
}
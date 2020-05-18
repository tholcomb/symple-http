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

use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Route;

class AnnotatedClassLoader extends AnnotationClassLoader {
	protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, $annot)
	{
		$route->setDefault('_controller', [$class->getName(), $method->getName()]);
	}
}
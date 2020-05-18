<?php
/**
 * This file is part of the Symple framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\Http\Tests\ControllerAgain;

use Symfony\Component\Routing\Annotation\Route;
use Tholcomb\Symple\Http\Tests\Controller\TestControllerA;

/**
 * @Route("/testC")
 */
class TestControllerC extends TestControllerA {}
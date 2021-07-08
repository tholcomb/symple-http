<?php
/**
 * This file is part of the Symple framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\Http\Tests\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Tholcomb\Symple\Http\AbstractController;

/**
 * @Route("/abstract")
 */
class TestAbstractController extends AbstractController {
	/**
	 * @Route("/json")
	 */
	public function testJson(): Response
	{
		return $this->json('data');
	}

	/**
	 * @Route("/urlGen", name="url-gen")
	 */
	public function testUrlGen(): Response
	{
		return $this->json($this->url('url-gen'));
	}

	/**
	 * @Route("/exception")
	 */
	public function testHttpException(): Response
	{
		throw new AccessDeniedHttpException();
	}

	/**
	 * @Route("/{argument}")
	 */
	public function testRender($argument): Response
	{
		return $this->renderToResponse('test-template.html.twig', ['argument' => $argument]);
	}
}
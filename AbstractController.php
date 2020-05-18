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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error;

abstract class AbstractController {
	private $twig;

	protected function json($data = null, int $code = Response::HTTP_OK, array $headers = []): JsonResponse
	{
		return new JsonResponse($data, $code, $headers);
	}

	protected function renderToResponse(string $template, array $params = [], int $code = Response::HTTP_OK, array $headers = []): Response
	{
		try {
			$content = $this->getTwig()->render($template, $params);
		} catch (Error $e) {
			throw new \RuntimeException('Caught Twig error', 0, $e);
		}
		return new Response($content, $code, $headers);
	}

	/**
	 * @param Environment|callable $twig
	 */
	public function setTwig($twig): void
	{
		$this->twig = $twig;
	}

	protected function getTwig(): Environment
	{
		if (!class_exists(Environment::class)) {
			throw new \LogicException('Twig not installed');
		}
		if (is_callable($this->twig)) $this->twig = call_user_func($this->twig);
		if (!$this->twig instanceof Environment) {
			throw new \LogicException('Did not get Twig Environment');
		}
		return $this->twig;
	}
}
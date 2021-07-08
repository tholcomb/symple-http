<?php
/*
 * This file is part of the Symple framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\Http\Event;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Tholcomb\Symple\Core\Symple;

class ExceptionSubscriber implements EventSubscriberInterface
{
	private $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public static function getSubscribedEvents()
	{
		return [
			KernelEvents::EXCEPTION => [
				['exceptionLogger', 1024],
				['onException', -4096],
			],
		];
	}

	public function exceptionLogger(ExceptionEvent $event): void
	{
		$this->logger->error($this->formatThrowable($event->getThrowable()));
	}

	public function onException(ExceptionEvent $event): void
	{
		$code = 500;
		$e = $event->getThrowable();
		if ($e instanceof HttpException) {
			$code = $e->getStatusCode();
		}
		if (Symple::isDebug()) {
			if ($event->getRequest()->headers->get('Accept') === 'application/json') {
				$event->setResponse(new JsonResponse($this->recurseThrowable($event->getThrowable()), $code));
			}

			return;
		}

		$event->setResponse(new Response(null, $code));
	}

	private function formatThrowable(\Throwable $e): string
	{
		return sprintf('%s: (%d) %s in %s:%d', get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
	}

	private function recurseThrowable(\Throwable $e, array $return = []): array
	{
		$return[] = $this->formatThrowable($e);
		if ($e->getPrevious() instanceof \Throwable) {
			return $this->recurseThrowable($e->getPrevious(), $return);
		}

		return $return;
	}
}
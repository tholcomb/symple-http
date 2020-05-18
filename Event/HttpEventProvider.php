<?php
/**
 * This file is part of the Symple framework
 *
 * Copyright (c) Tyler Holcomb <tyler@tholcomb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tholcomb\Symple\Http\Event;

use Tholcomb\Symple\Event\EventProvider;

class HttpEventProvider extends EventProvider {
	public const KEY_DISPATCHER = 'http.event.dispatcher';
	protected const SUBSCRIBER_PREFIX = 'http.event.sub';
	protected const NAME = 'http.event';
}
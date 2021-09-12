<?php declare(strict_types=1);

namespace Nadybot\User\Modules\BIGBOSS_MODULE;

use Nadybot\Core\MessageEmitter;

class BigBossChannel implements MessageEmitter {
	protected string $bb;

	public function __construct(string $bb) {
		$this->bb = $bb;
	}

	public function getChannelName(): string {
		return "spawn({$this->bb})";
	}
}

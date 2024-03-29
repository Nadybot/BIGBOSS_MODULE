<?php declare(strict_types=1);

namespace Nadybot\User\Modules\BIGBOSS_MODULE;

use DateTime;
use DateTimeZone;
use Nadybot\Core\{
	CommandReply,
	DB,
	Event,
	MessageHub,
	Modules\DISCORD\DiscordController,
	Nadybot,
	SettingManager,
	Text,
	Util,
};
use Nadybot\Core\Routing\RoutableMessage;
use Nadybot\Core\Routing\Source;
use Nadybot\User\Modules\DISC_MODULE\BigbossTimer;

/**
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 *
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'bb',
 *		accessLevel = 'all',
 *		description = 'Show next spawntime(s)',
 *		help        = 'bb.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'tara',
 *		accessLevel = 'all',
 *		description = 'Show next Tarasque spawntime(s)',
 *		help        = 'tara.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'tarakill',
 *		accessLevel = 'member',
 *		description = 'Update Tarasque killtimer to now',
 *		help        = 'tara.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'taraupdate',
 *		accessLevel = 'member',
 *		description = 'Update Tarasque killtimer to the given time',
 *		help        = 'tara.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'taradel',
 *		accessLevel = 'member',
 *		description = 'Delete the timer for Tarasque until someone re-creates it',
 *		help        = 'tara.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'reaper',
 *		accessLevel = 'all',
 *		description = 'Show next Reaper spawntime(s)',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'reaperkill',
 *		accessLevel = 'member',
 *		description = 'Update Reaper killtimer to now',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'reaperupdate',
 *		accessLevel = 'member',
 *		description = 'Update Reaper killtimer to the given time',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'reaperdel',
 *		accessLevel = 'member',
 *		description = 'Delete the timer for The Hollow Reaper until someone re-creates it',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'loren',
 *		accessLevel = 'all',
 *		description = 'Show next Loren Warr spawntime(s)',
 *		help        = 'loren.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'lorenkill',
 *		accessLevel = 'member',
 *		description = 'Update Loren Warr killtimer to now',
 *		help        = 'loren.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'lorenupdate',
 *		accessLevel = 'member',
 *		description = 'Update Loren killtimer to the given time',
 *		help        = 'loren.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'lorendel',
 *		accessLevel = 'member',
 *		description = 'Delete the timer for Loren Warr until someone re-creates it',
 *		help        = 'loren.txt'
 *	)
 */
class BigBossController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public string $moduleName;

	/** @Inject */
	public Text $text;

	/** @Inject */
	public SettingManager $settingManager;

	/** @Inject */
	public Util $util;

	/** @Inject */
	public DB $db;

	/** @Inject */
	public Nadybot $chatBot;

	/** @Inject */
	public MessageHub $messageHub;

	/** @Inject */
	public DiscordController $discordController;

	public const DB_TABLE = "bigboss_timers";

	public const TARA = 'Tarasque';
	public const REAPER = 'The Hollow Reaper';
	public const LOREN = 'Loren Warr';

	public const BOSS_MAP = [
		self::TARA => "tara",
		self::REAPER => "reaper",
		self::LOREN => "loren",
	];

	/** @Setup */
	public function setup() {
		$this->db->loadMigrations($this->moduleName, __DIR__ . '/Migrations');
		foreach (["tara", "lauren", "reaper"] as $bb) {
			foreach (["prespawn", "spawn", "vulnerable"] as $event) {
				$emitter = new BigBossChannel("{$bb}-{$event}");
				$this->messageHub->registerMessageEmitter($emitter);
			}
		}
	}

	/**
	 * @param BigbossTimer[] $timers
	 */
	protected function addNextDates(array $timers): void {
		foreach ($timers as $timer) {
			$invulnerableTime = $timer->killable - $timer->spawn;
			$timer->next_killable = $timer->killable;
			$timer->next_spawn    = $timer->spawn;
			while ($timer->next_killable < time()) {
				$timer->next_killable += $timer->timer + $invulnerableTime;
				$timer->next_spawn    += $timer->timer + $invulnerableTime;
			}
		}
		usort($timers, function($a, $b) {
			return $a->next_spawn <=> $b->next_spawn;
		});
	}

	protected function getBigBossTimer(string $mobName): ?BigbossTimer {
		/** @var BigbossTimer[] */
		$timers = $this->db->table("bigboss_timers")
			->where("mob_name", $mobName)
			->asObj(BigbossTimer::class)
			->toArray();
		if (!count($timers)) {
			return null;
		}
		$this->addNextDates($timers);
		return $timers[0];
	}

	/**
	 * @return BigbossTimer[]
	 */
	protected function getBigBossTimers(): array {
		/** @var BigbossTimer[] */
		$timers = $this->db->table("bigboss_timers")
			->asObj(BigbossTimer::class)
			->toArray();
		$this->addNextDates($timers);
		return $timers;
	}

	protected function niceTime(int $timestamp): string {
		$time = new DateTime();
		$time->setTimestamp($timestamp);
		$time->setTimezone(new DateTimeZone('UTC'));
		return $time->format("D, H:i T (Y-m-d)");
	}

	protected function getNextSpawnsMessage(BigbossTimer $timer, int $howMany=10): string {
		$multiplicator = $timer->timer + $timer->killable - $timer->spawn;
		$times = [];
		for ($i = 0; $i < $howMany; $i++) {
			$spawnTime = $timer->next_spawn + $i*$multiplicator;
			$times[] = $this->niceTime($spawnTime);
		}
		$msg = "Timer updated".
				" at <highlight>".$this->niceTime($timer->time_submitted)."<end>.\n\n".
				"<tab>- ".join("\n\n<tab>- ", $times);
		return $msg;
	}

	protected function getBigBossMessage(string $mobName): string {
		$timer = $this->getBigBossTimer($mobName);
		if ($timer === null) {
			$msg = "I currently don't have an accurate timer for <highlight>$mobName<end>.";
			return $msg;
		}
		return $this->formatBigBossMessage($timer, false);
	}

	public function formatBigBossMessage(BigbossTimer $timer, bool $short=true): string {
		$spawnTimeMessage = '';
		if (time() < $timer->next_spawn) {
			$timeUntilSpawn = $this->util->unixtimeToReadable($timer->next_spawn-time());
			$spawnTimeMessage = " spawns in <highlight>$timeUntilSpawn<end>";
			if ($short) {
				return "{$timer->mob_name}{$spawnTimeMessage}.";
			}
			$spawnTimeMessage .= " and";
		} else {
			$spawnTimeMessage = " spawned and";
		}
		$timeUntilKill = $this->util->unixtimeToReadable($timer->next_killable-time());
		$killTimeMessage = " will be vulnerable in <highlight>$timeUntilKill<end>";
		if ($short) {
			return "{$timer->mob_name}{$spawnTimeMessage}{$killTimeMessage}.";
		}

		$nextSpawnsMessage = $this->getNextSpawnsMessage($timer);
		$spawntimes = $this->text->makeBlob("Spawntimes for {$timer->mob_name}", $nextSpawnsMessage);
		$msg = "{$timer->mob_name}${spawnTimeMessage}${killTimeMessage}. $spawntimes";
		return $msg;
	}

	public function bigBossDeleteCommand(string $sender, string $mobName): string {
		if ($this->db->table("bigboss_timers")
			->where("mob_name", $mobName)
			->delete() === 0
		) {
			return "There is currently no timer for <highlight>$mobName<end>.";
		}
		return "The timer for <highlight>$mobName<end> has been deleted.";
	}

	public function bigBossKillCommand(string $sender, string $mobName, int $timeUntilSpawn, int $timeUntilKillable): string {
		$this->db->table("bigboss_timers")
			->upsert(
				[
					"mob_name" => $mobName,
					"timer" => $timeUntilSpawn,
					"spawn" => time() + $timeUntilSpawn,
					"killable" => time() + $timeUntilKillable,
					"time_submitted" => time(),
					"submitter_name" => $sender,
				],
				["mob_name"]
			);
		$msg = "The timer for <highlight>$mobName<end> has been updated.";
		return $msg;
	}

	public function bigBossUpdateCommand(string $sender, string $arg, string $mobName, int $downTime, int $timeUntilKillable): string {
		$newKillTime = $this->util->parseTime($arg);
		if ($newKillTime < 1) {
			$msg = "You must enter a valid time parameter for the time until <highlight>${mobName}<end> will be vulnerable.";
			return $msg;
		}
		$newKillTime += time();
		$this->db->table("bigboss_timers")
			->upsert(
				[
					"mob_name" => $mobName,
					"timer" => $downTime,
					"spawn" => $newKillTime-$timeUntilKillable,
					"killable" => $newKillTime,
					"time_submitted" => time(),
					"submitter_name" => $sender,
				],
				["mob_name"]
			);
		$msg = "The timer for <highlight>$mobName<end> has been updated.";
		return $msg;
	}

	/**
	 * @HandlesCommand("bb")
	 * @Matches("/^bb$/i")
	 */
	public function bbCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$timers = $this->getBigBossTimers();
		if (!count($timers)) {
			$msg = "I currently don't have an accurate timer for any boss.";
			$sendto->reply($msg);
			return;
		}
		$messages = array_map([$this, 'formatBigBossMessage'], $timers);
		$msg = $messages[0];
		if (count($messages) > 1) {
			$msg = "I'm currently monitoring the following bosses:\n".
				join("\n", $messages);
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("tara")
	 * @Matches("/^tara$/i")
	 */
	public function taraCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$sendto->reply($this->getBigBossMessage(static::TARA));
	}

	/**
	 * @HandlesCommand("tarakill")
	 * @Matches("/^tarakill$/i")
	 */
	public function taraKillCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossKillCommand($sender, static::TARA, 9*3600, (int)(9.5*3600));
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("taraupdate")
	 * @Matches("/^taraupdate ([a-z0-9 ]+)$/i")
	 */
	public function taraUpdateCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossUpdateCommand($sender, $args[1], static::TARA, 9*3600, 1800);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("taradel")
	 * @Matches("/^taradel$/i")
	 */
	public function taraDeleteCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossDeleteCommand($sender, static::TARA);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("reaper")
	 * @Matches("/^reaper$/i")
	 */
	public function reaperCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$sendto->reply($this->getBigBossMessage(static::REAPER));
	}

	/**
	 * @HandlesCommand("reaperkill")
	 * @Matches("/^reaperkill$/i")
	 */
	public function reaperKillCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossKillCommand($sender, static::REAPER, 9*3600, (int)(9.25*3600));
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("reaperupdate")
	 * @Matches("/^reaperupdate ([a-z0-9 ]+)$/i")
	 */
	public function reaperUpdateCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossUpdateCommand($sender, $args[1], static::REAPER, 9*3600, 900);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("reaperdel")
	 * @Matches("/^reaperdel$/i")
	 */
	public function reaperDeleteCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossDeleteCommand($sender, static::REAPER);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("loren")
	 * @Matches("/^loren$/i")
	 */
	public function lorenCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$sendto->reply($this->getBigBossMessage(static::LOREN));
	}

	/**
	 * @HandlesCommand("lorenkill")
	 * @Matches("/^lorenkill$/i")
	 */
	public function lorenKillCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossKillCommand($sender, static::LOREN, 9*3600, (int)(9.25*3600));
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("lorenupdate")
	 * @Matches("/^lorenupdate ([a-z0-9 ]+)$/i")
	 */
	public function lorenUpdateCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossUpdateCommand($sender, $args[1], static::LOREN, 9*3600, 900);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("lorendel")
	 * @Matches("/^lorendel$/i")
	 */
	public function lorenDeleteCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$msg = $this->bigBossDeleteCommand($sender, static::LOREN);
		$sendto->reply($msg);
	}

	/**
	 * Announce an event
	 *
	 * @param string $msg The nmessage to send
	 * @param int $step 1 => spawns soon, 2 => has spawned, 3 => vulnerable
	 * @return void
	 */
	protected function announceBigBossEvent(string $boss, string $msg, int $step): void {
		$event = 'spawn';
		if ($step === 1) {
			$event = 'prespawn';
		} elseif ($step === 3) {
			$event = 'vulnerable';
		}
		$rMsg = new RoutableMessage($msg);
		$rMsg->appendPath(new Source(
			"spawn",
			self::BOSS_MAP[$boss] . "-{$event}",
			ucfirst(self::BOSS_MAP[$boss])
		));
		$this->messageHub->handle($rMsg);
	}

	/**
	 * @Event("timer(10sec)")
	 * @Description("Check timer to announce big boss events")
	 */
	public function checkTimerEvent(Event $eventObj): void {
		$timers = $this->getBigBossTimers();
		foreach ($timers as $timer) {
			$invulnerableTime = $timer->killable - $timer->spawn;
			if ($timer->next_spawn <= time()+15*60 && $timer->next_spawn > time()+15*60-10) {
				$msg = "<highlight>".$timer->mob_name."<end> will spawn in ".
					"<highlight>".$this->util->unixtimeToReadable($timer->next_spawn-time())."<end>.";
				$this->announceBigBossEvent($timer->mob_name, $msg, 1);
			}
			if ($timer->next_spawn <= time() && $timer->next_spawn > time()-10) {
				$msg = "<highlight>".$timer->mob_name."<end> has spawned and will be vulnerable in ".
					"<highlight>".$this->util->unixtimeToReadable($timer->next_killable-time())."<end>.";
				$this->announceBigBossEvent($timer->mob_name, $msg, 2);
			}
			$nextKillTime = time() + $timer->timer+$invulnerableTime;
			if ($timer->next_killable == time() || ($timer->next_killable <= $nextKillTime && $timer->next_killable > $nextKillTime-10)) {
				$msg = "<highlight>".$timer->mob_name."<end> is no longer immortal.";
				$this->announceBigBossEvent($timer->mob_name, $msg, 3);
			}
		}
	}
}

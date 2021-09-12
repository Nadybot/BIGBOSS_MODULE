<?php declare(strict_types=1);

namespace Nadybot\User\Modules\BIGBOSS_MODULE\Migrations;

use Nadybot\Core\DB;
use Nadybot\Core\DBSchema\Route;
use Nadybot\Core\DBSchema\RouteHopFormat;
use Nadybot\Core\DBSchema\Setting;
use Nadybot\Core\LoggerWrapper;
use Nadybot\Core\MessageHub;
use Nadybot\Core\Routing\Source;
use Nadybot\Core\SchemaMigration;
use Nadybot\Core\SettingManager;

class MigrateToRoutes implements SchemaMigration {
	/** @Inject */
	public MessageHub $messageHub;

	protected function getSetting(DB $db, string $name): ?Setting {
		return $db->table(SettingManager::DB_TABLE)
			->where("name", $name)
			->asObj(Setting::class)
			->first();
	}

	public function migrate(LoggerWrapper $logger, DB $db): void {
		$preAlert = $this->getSetting($db, "bigboss_channels_prespawn");
		$preAlert = isset($preAlert) ? (int)$preAlert->value&3 : 3;
		$spawnAlert = $this->getSetting($db, "bigboss_channels_spawn");
		$spawnAlert = isset($spawnAlert) ? (int)$spawnAlert->value&3 : 3;
		$vulnAlert = $this->getSetting($db, "bigboss_channels_vulnerable");
		$vulnAlert = isset($vulnAlert) ? (int)$vulnAlert->value&3 : 3;
		if ($preAlert === $spawnAlert && $preAlert === $vulnAlert) {
			$this->installRoutes($db, "*", $preAlert);
		} else {
			$this->installRoutes($db, "*-prespawn", $preAlert);
			$this->installRoutes($db, "*-spawn", $spawnAlert);
			$this->installRoutes($db, "*-vulnerable", $vulnAlert);
		}
		$routeFormat = new RouteHopFormat();
		$routeFormat->hop = "spawn";
		$routeFormat->render = false;
		$db->insert(Source::DB_TABLE, $routeFormat);
		$this->messageHub->loadTagFormat();
	}

	protected function installRoutes(DB $db, string $event, int $value): void {
		if ($value & 1) {
			$route = new Route();
			$route->source = "spawn({$event})";
			$route->destination = "aoorg";
			$db->insert(MessageHub::DB_TABLE_ROUTES, $route);
		}
		if ($value & 2) {
			$route = new Route();
			$route->source = "spawn({$event})";
			$route->destination = "aopriv(" . $db->getMyname() . ")";
			$db->insert(MessageHub::DB_TABLE_ROUTES, $route);
		}
	}
}

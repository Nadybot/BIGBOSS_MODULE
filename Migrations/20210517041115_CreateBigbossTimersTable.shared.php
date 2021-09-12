<?php declare(strict_types=1);

namespace Nadybot\User\Modules\BIGBOSS_MODULE\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Nadybot\Core\DB;
use Nadybot\Core\LoggerWrapper;
use Nadybot\Core\SchemaMigration;
use Nadybot\User\Modules\BIGBOSS_MODULE\BigBossController;

class CreateBigbossTimersTable implements SchemaMigration {
	public function migrate(LoggerWrapper $logger, DB $db): void {
		$table = BigBossController::DB_TABLE;
		if ($db->schema()->hasTable($table)) {
			return;
		}
		$db->schema()->create($table, function(Blueprint $table) {
			$table->string("mob_name", 50)->primary();
			$table->integer("timer");
			$table->integer("spawn");
			$table->integer("killable");
			$table->integer("time_submitted");
			$table->string("submitter_name", 25);
		});
	}
}

<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Blindspot for BASE3 Framework.
 *
 * Blindspot provides a conversational AI confrontation game for BASE3.
 * It owns match rules, game history and the interactive display.
 * Agents execute through the existing AssistantFoundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Blindspot\Migration;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Migration\Api\IDatabaseMigration;
use Base3\CoreFoundation\Migration\Api\ILegacyDatabaseMigration;
use Base3\CoreFoundation\Migration\Exception\MigrationException;

/** Immutable version 001 of the plugin's private match state table. */
final class BlindspotMigration001CreateMatches implements IDatabaseMigration, ILegacyDatabaseMigration {

	public function __construct(private readonly IDatabase $database) {}

	public static function getName(): string { return 'blindspotmigration001creatematches'; }

	public function getVersion(): string { return '001'; }

	public function getDescription(): string { return 'Create BLINDSPOT matches'; }

	public function up(): void {
		$this->database->connect();
		$this->database->nonQuery('CREATE TABLE IF NOT EXISTS blindspot_matches (' .
			'id CHAR(32) NOT NULL PRIMARY KEY, ' .
			'authority VARCHAR(128) NOT NULL, ' .
			'owner_id VARCHAR(190) NOT NULL, ' .
			'revision INT UNSIGNED NOT NULL DEFAULT 0, ' .
			'payload LONGTEXT NOT NULL, ' .
			'created_at DATETIME NOT NULL, ' .
			'updated_at DATETIME NOT NULL, ' .
			'INDEX blindspot_owner_idx (authority, owner_id, updated_at)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
	}

	public function down(): void {
		throw new MigrationException('Not implemented');
	}
}

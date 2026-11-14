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


namespace Base3\Blindspot\Test\Fixture;

use Base3\CoreFoundation\Database\Api\IDatabase;

/** Records SQL to verify database boundaries without requiring a host. */
final class RecordingDatabase implements IDatabase {

	public array $queries = [];
	public ?array $nextRow = null;
	public int $affected = 1;
	public array $nextRows = [];
	private bool $online = false;

	public function connect(): void { $this->online = true; }
	public function connected(): bool { return $this->online; }
	public function disconnect(): void { $this->online = false; }
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}
	public function nonQuery(string $query): void { $this->queries[] = $query; }
	public function scalarQuery(string $query): mixed { return null; }
	public function singleQuery(string $query): ?array { $this->queries[] = $query; return $this->nextRow; }
	public function &listQuery(string $query): array { $this->queries[] = $query; return $this->nextRows; }
	public function &multiQuery(string $query): array { $this->queries[] = $query; return $this->nextRows; }
	public function affectedRows(): int { return $this->affected; }
	public function insertId(): int|string { return 1; }
	public function escape(string $str): string { return addslashes($str); }
	public function isError(): bool { return false; }
	public function errorNumber(): int { return 0; }
	public function errorMessage(): string { return ''; }
}

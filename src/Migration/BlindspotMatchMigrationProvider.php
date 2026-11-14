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

use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Migration\Api\IDatabaseMigrationProvider;

/** Activates only when the selected BASE3 host binds the existing database service. */
final class BlindspotMatchMigrationProvider implements IDatabaseMigrationProvider {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string { return 'blindspotmatchmigrationprovider'; }

	public function isActive(): bool { return $this->container->has(IDatabase::class); }

	public function getMigrations(): array { return [BlindspotMigration001CreateMatches::class]; }
}

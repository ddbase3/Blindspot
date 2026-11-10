<?php declare(strict_types=1);

namespace Blindspot\Migration;

use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use Base3\Migration\Api\IDatabaseMigrationProvider;

/** Activates only when the selected BASE3 host binds the existing database service. */
final class BlindspotMatchMigrationProvider implements IDatabaseMigrationProvider {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string { return 'blindspotmatchmigrationprovider'; }

	public function isActive(): bool { return $this->container->has(IDatabase::class); }

	public function getMigrations(): array { return [BlindspotMigration001CreateMatches::class]; }
}

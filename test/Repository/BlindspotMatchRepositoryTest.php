<?php declare(strict_types=1);

namespace Blindspot\Test\Repository;

use Blindspot\Repository\BlindspotMatchRepository;
use Blindspot\Test\Fixture\RecordingDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlindspotMatchRepositoryTest extends TestCase {

	private const ID = '0123456789abcdef0123456789abcdef';
	private const ACTOR = ['authority' => 'ilias.demo', 'id' => '17'];

	public function testReadRequiresBothPartsOfTrustedIdentity(): void {
		$database = new RecordingDatabase();
		$database->nextRow = ['revision' => '3', 'payload' => json_encode(['id' => self::ID])];
		$match = (new BlindspotMatchRepository($database))->loadOwned(self::ID, self::ACTOR);
		self::assertSame(3, $match['revision']);
		self::assertStringContainsString("authority = 'ilias.demo'", end($database->queries));
		self::assertStringContainsString("owner_id = '17'", end($database->queries));
	}

	public function testMissingOrOtherOwnerDoesNotYieldMatchData(): void {
		$database = new RecordingDatabase();
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.match_not_found');
		(new BlindspotMatchRepository($database))->loadOwned(self::ID, self::ACTOR);
	}

	public function testCompareAndSwapRejectsConcurrentWrite(): void {
		$database = new RecordingDatabase();
		$database->affected = 0;
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.stale_state');
		try { (new BlindspotMatchRepository($database))->replace(self::ACTOR, ['id' => self::ID], 5); }
		finally {
			self::assertStringContainsString('AND revision = 5', end($database->queries));
			self::assertStringContainsString("owner_id = '17'", end($database->queries));
		}
	}

	public function testHistoryReturnsOnlyOwnerScopedCompletedMatchesAndAllTimeBest(): void {
		$database = new RecordingDatabase();
		$database->nextRow = ['completed' => '18', 'best' => '725'];
		$database->nextRows = [[
			'id' => self::ID,
			'payload' => json_encode(['id' => self::ID, 'phase' => 'finished',
				'topic' => 'Training', 'score' => 125, 'hits' => 2], JSON_THROW_ON_ERROR),
			'created_at' => '2026-11-10 10:00:00'
		]];
		$history = (new BlindspotMatchRepository($database))->historyOwned(self::ACTOR);
		self::assertSame(18, $history['completed']);
		self::assertSame(725, $history['best']);
		self::assertCount(1, $history['items']);
		self::assertSame('Training', $history['items'][0]['topic']);
		self::assertArrayNotHasKey('questions', $history['items'][0]);
		self::assertArrayNotHasKey('payload', $history['items'][0]);
		self::assertCount(2, $database->queries);
		foreach ($database->queries as $query) {
			self::assertStringContainsString("authority = 'ilias.demo'", $query);
			self::assertStringContainsString("owner_id = '17'", $query);
			self::assertStringContainsString("'$.phase'", $query);
			self::assertStringContainsString("'finished'", $query);
		}
		self::assertStringContainsString('LIMIT 10', $database->queries[1]);
	}

	public function testCorruptedHistoryIsNotDisclosed(): void {
		$database = new RecordingDatabase();
		$database->nextRows = [['id' => self::ID, 'payload' => json_encode(['id' => str_repeat('b', 32),
			'phase' => 'finished', 'topic' => 'Invalid', 'score' => 725, 'hits' => 5]), 'created_at' => '2026-11-10']];
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.invalid_state');
		(new BlindspotMatchRepository($database))->historyOwned(self::ACTOR);
	}

	public function testDeletingOwnedMatchesScopesAuthorityAndUser(): void {
		$database = new RecordingDatabase();
		(new BlindspotMatchRepository($database))->deleteAllOwned(self::ACTOR);
		self::assertStringContainsString('DELETE FROM blindspot_matches', end($database->queries));
		self::assertStringContainsString("authority = 'ilias.demo'", end($database->queries));
		self::assertStringContainsString("owner_id = '17'", end($database->queries));
	}
}

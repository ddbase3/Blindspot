<?php declare(strict_types=1);

namespace Blindspot\Repository;

use Base3\Database\Api\IDatabase;
use RuntimeException;

/** Stores one private, authoritative match document per row in the selected BASE3 database. */
final class BlindspotMatchRepository {

	public function __construct(private readonly IDatabase $database) {}

	/** @param array{authority:string,id:string} $actor */
	public function create(array $actor, array $state): void {
		$this->database->connect();
		$this->database->nonQuery('INSERT INTO blindspot_matches ' .
			'(id, authority, owner_id, revision, payload, created_at, updated_at) VALUES (' .
			$this->quote($state['id']) . ', ' . $this->quote($actor['authority']) . ', ' .
			$this->quote($actor['id']) . ', 0, ' .
			$this->quote(json_encode($state, JSON_THROW_ON_ERROR)) .
			', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
	}

	/** @param array{authority:string,id:string} $actor */
	public function latestOwned(array $actor): ?array {
		$this->database->connect();
		$row = $this->database->singleQuery('SELECT id FROM blindspot_matches WHERE authority = ' .
			$this->quote($actor['authority']) . ' AND owner_id = ' . $this->quote($actor['id']) .
			' ORDER BY updated_at DESC, id DESC LIMIT 1');
		return $row === null ? null : $this->loadOwned((string)$row['id'], $actor);
	}

	/** Recent completed matches and all-time best, both scoped to one trusted native actor. */
	public function historyOwned(array $actor): array {
		$this->database->connect();
		$filter = 'authority = ' . $this->quote($actor['authority']) .
			' AND owner_id = ' . $this->quote($actor['id']) .
			" AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.phase')) = 'finished'";
		$stats = $this->database->singleQuery('SELECT COUNT(*) AS completed, ' .
			"COALESCE(MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.score')) AS UNSIGNED)), 0) AS best " .
			'FROM blindspot_matches WHERE ' . $filter);
		$rows = $this->database->multiQuery('SELECT id, payload, created_at FROM blindspot_matches WHERE ' .
			$filter . ' ORDER BY created_at DESC, id DESC LIMIT 10');
		$items = [];
		foreach ($rows as $row) {
			$state = json_decode((string)$row['payload'], true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($state) || !is_string($state['id'] ?? null) ||
				$state['id'] !== $row['id'] || ($state['phase'] ?? null) !== 'finished' ||
				!is_int($state['score'] ?? null) || !is_int($state['hits'] ?? null) ||
				!is_string($state['topic'] ?? null)) {
				throw new RuntimeException('blindspot.invalid_state');
			}
			$items[] = [
				'id' => $state['id'],
				'topic' => $state['topic'],
				'score' => $state['score'],
				'hits' => $state['hits'],
				'created_at' => (string)$row['created_at']
			];
		}
		return [
			'completed' => (int)($stats['completed'] ?? 0),
			'best' => (int)($stats['best'] ?? 0),
			'items' => $items
		];
	}

	/** @param array{authority:string,id:string} $actor */
	public function loadOwned(string $id, array $actor): array {
		$this->assertId($id);
		$this->database->connect();
		$row = $this->database->singleQuery('SELECT revision, payload FROM blindspot_matches WHERE id = ' .
			$this->quote($id) . ' AND authority = ' . $this->quote($actor['authority']) .
			' AND owner_id = ' . $this->quote($actor['id']));
		if ($row === null) { throw new RuntimeException('blindspot.match_not_found'); }
		$state = json_decode((string)$row['payload'], true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($state) || ($state['id'] ?? null) !== $id) {
			throw new RuntimeException('blindspot.invalid_state');
		}
		$state['revision'] = (int)$row['revision'];
		return $state;
	}

	/** Atomic optimistic revision commit, scoped to trusted host authority and actor. */
	public function replace(array $actor, array $state, int $expectedRevision): void {
		$this->assertId($state['id']);
		$state['revision'] = $expectedRevision + 1;
		$this->database->connect();
		$this->database->nonQuery('UPDATE blindspot_matches SET revision = ' . ($expectedRevision + 1) .
			', payload = ' . $this->quote(json_encode($state, JSON_THROW_ON_ERROR)) .
			', updated_at = CURRENT_TIMESTAMP WHERE id = ' . $this->quote($state['id']) .
			' AND authority = ' . $this->quote($actor['authority']) .
			' AND owner_id = ' . $this->quote($actor['id']) .
			' AND revision = ' . $expectedRevision);
		if ($this->database->affectedRows() !== 1) { throw new RuntimeException('blindspot.stale_state'); }
	}

	/** Erases all matches of the current trusted native owner, including hidden solutions. */
	public function deleteAllOwned(array $actor): void {
		$this->database->connect();
		$this->database->nonQuery('DELETE FROM blindspot_matches WHERE authority = ' .
			$this->quote($actor['authority']) . ' AND owner_id = ' . $this->quote($actor['id']));
	}

	private function quote(string $value): string { return "'" . $this->database->escape($value) . "'"; }

	private function assertId(string $id): void {
		if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new RuntimeException('blindspot.match_not_found'); }
	}
}

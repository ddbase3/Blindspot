<?php declare(strict_types=1);

namespace Blindspot\Service;

use Blindspot\Game\BlindspotRules;
use Blindspot\Repository\BlindspotMatchRepository;
use InvalidArgumentException;
use RuntimeException;

/** Single owner of authorized game transitions, model calls and optimistic persistence. */
final class BlindspotGameService {

	public function __construct(
		private readonly BlindspotMatchRepository $matches,
		private readonly BlindspotAccessService $access,
		private readonly BlindspotAgentService $agent
	) {}

	public function start(): array {
		$actor = $this->access->requireActor();
		$id = bin2hex(random_bytes(16));
		try {
			$plan = BlindspotRules::selectTopics($this->agent->catalogTopics());
			$generated = $this->agent->challenge(1, $plan[0]);
			$generated['topic'] = $plan[0]['topic'];
			$state = BlindspotRules::create($id, $generated, $plan);
		} catch (InvalidArgumentException $error) {
			throw new RuntimeException('blindspot.agent_invalid_response', 0, $error);
		}
		if ($this->access->requireActor() !== $actor) { throw new RuntimeException('blindspot.access_denied'); }
		$this->matches->create($actor, $state);
		return BlindspotRules::publicState($state);
	}

	public function latest(): ?array {
		$state = $this->matches->latestOwned($this->access->requireActor());
		if ($state === null || (($state['format'] ?? null) !== 2 && ($state['phase'] ?? null) !== 'finished')) {
			return null;
		}
		return BlindspotRules::publicState($state);
	}

	public function snapshot(string $id): array {
		return BlindspotRules::publicState($this->matches->loadOwned($id, $this->access->requireActor()));
	}

	public function history(): array {
		return $this->matches->historyOwned($this->access->requireActor());
	}

	public function probe(string $id, int $revision, string $question): array {
		$question = BlindspotRules::validatePlayerText($question);
		$actor = $this->access->requireActor();
		$state = $this->matches->loadOwned($id, $actor);
		$this->requireRevision($state, $revision);
		BlindspotRules::requireDuel($state);
		if ($state['probes_remaining'] < 1) { throw new RuntimeException('blindspot.probes_exhausted'); }
		$reply = $this->agent->defend($state, $question);
		if ($this->access->requireActor() !== $actor) { throw new RuntimeException('blindspot.access_denied'); }
		$state = BlindspotRules::probe($state, $question, $reply);
		$this->matches->replace($actor, $state, $revision);
		$state['revision'] = $revision + 1;
		return BlindspotRules::publicState($state);
	}

	public function accuse(string $id, int $revision, string $accusation): array {
		$accusation = BlindspotRules::validatePlayerText($accusation, 12);
		$actor = $this->access->requireActor();
		$state = $this->matches->loadOwned($id, $actor);
		$this->requireRevision($state, $revision);
		BlindspotRules::requireDuel($state);
		$judgement = $this->agent->judge($state, $accusation);
		if ($this->access->requireActor() !== $actor) { throw new RuntimeException('blindspot.access_denied'); }
		$state = BlindspotRules::accuse($state, $accusation, $judgement['correct'], $judgement['feedback']);
		$this->matches->replace($actor, $state, $revision);
		$state['revision'] = $revision + 1;
		return BlindspotRules::publicState($state);
	}

	public function next(string $id, int $revision): array {
		$actor = $this->access->requireActor();
		$state = $this->matches->loadOwned($id, $actor);
		$this->requireRevision($state, $revision);
		if (($state['format'] ?? null) !== 2 || ($state['phase'] ?? null) !== 'reveal') {
			throw new RuntimeException('blindspot.invalid_phase');
		}
		if ($state['round'] === BlindspotRules::ROUNDS) {
			$state = BlindspotRules::next($state);
		} else {
			try {
				// Matches begun before topic planning receive the same plan on their next round.
				if (!isset($state['topic_plan'])) {
					$state['topic_plan'] = BlindspotRules::selectTopics($this->agent->catalogTopics());
				}
				$selection = $state['topic_plan'][$state['round']];
				$generated = $this->agent->challenge($state['round'] + 1, $selection);
				$generated['topic'] = $selection['topic'];
				$state = BlindspotRules::next($state, $generated);
			} catch (InvalidArgumentException $error) {
				throw new RuntimeException('blindspot.agent_invalid_response', 0, $error);
			}
		}
		if ($this->access->requireActor() !== $actor) { throw new RuntimeException('blindspot.access_denied'); }
		$this->matches->replace($actor, $state, $revision);
		$state['revision'] = $revision + 1;
		return BlindspotRules::publicState($state);
	}

	public function deleteOwnMatches(): void {
		$this->matches->deleteAllOwned($this->access->requireActor());
	}

	private function requireRevision(array $state, int $revision): void {
		if ($revision < 0 || $revision !== ($state['revision'] ?? null)) {
			throw new RuntimeException('blindspot.stale_state');
		}
	}
}

<?php declare(strict_types=1);

namespace Blindspot\Game;

use InvalidArgumentException;
use RuntimeException;

/** The single authoritative BLINDSPOT match state and scoring implementation. */
final class BlindspotRules {

	public const ROUNDS = 5;
	public const PROBES_PER_ROUND = 4;
	public const DECEPTION_TYPES = ['fact', 'term', 'condition', 'causality', 'inference'];
	private const FORMAT = 2;

	/** Select five distinct themes, preferring five different document labels. */
	public static function selectTopics(array $candidates): array {
		if (count($candidates) < self::ROUNDS || count($candidates) > 120) {
			throw new InvalidArgumentException('blindspot.invalid_challenges');
		}
		$groups = [];
		$seenTopics = [];
		foreach ($candidates as $candidate) {
			$theme = self::validateTopic($candidate);
			$topicKey = strtolower($theme['topic']);
			if (isset($seenTopics[$topicKey])) { continue; }
			$seenTopics[$topicKey] = true;
			$groups[$theme['document']][] = $theme;
		}
		if (count($seenTopics) < self::ROUNDS) {
			throw new InvalidArgumentException('blindspot.invalid_challenges');
		}
		$documents = array_keys($groups);
		self::randomize($documents);
		$plan = [];
		foreach ($documents as $document) {
			if (count($plan) === self::ROUNDS) { break; }
			$index = random_int(0, count($groups[$document]) - 1);
			$plan[] = $groups[$document][$index];
			array_splice($groups[$document], $index, 1);
		}
		$remaining = array_merge(...array_values($groups));
		self::randomize($remaining);
		while (count($plan) < self::ROUNDS) {
			$plan[] = array_pop($remaining);
		}
		return $plan;
	}

	/** Fisher-Yates with PHP's random_int; game randomness does not depend on the model. */
	private static function randomize(array &$values): void {
		for ($index = count($values) - 1; $index > 0; $index--) {
			$chosen = random_int(0, $index);
			[$values[$index], $values[$chosen]] = [$values[$chosen], $values[$index]];
		}
	}

	private static function validateTopic(mixed $candidate): array {
		if (!is_array($candidate)) {
			throw new InvalidArgumentException('blindspot.invalid_challenges');
		}
		$section = $candidate['section'] ?? '';
		if ($section !== '') {
			$section = self::requiredText($section, 200);
		}
		return [
			'document' => self::requiredText($candidate['document'] ?? null, 240),
			'topic' => self::requiredText($candidate['topic'] ?? null, 140),
			'section' => $section
		];
	}

	/** Build a new conversational match with five privately scheduled topics. */
	public static function create(string $id, array $generated, array $topicPlan): array {
		if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
			throw new InvalidArgumentException('blindspot.invalid_challenges');
		}
		$challenge = self::validateChallenge($generated);
		if (count($topicPlan) !== self::ROUNDS) {
			throw new InvalidArgumentException('blindspot.invalid_challenges');
		}
		$topicPlan = array_map(self::validateTopic(...), $topicPlan);
		if (count(array_unique(array_column($topicPlan, 'topic'))) !== self::ROUNDS) {
			throw new InvalidArgumentException('blindspot.invalid_challenges');
		}
		return [
			'format' => self::FORMAT,
			'id' => $id,
			'revision' => 0,
			'topic' => $challenge['topic'],
			'topic_plan' => $topicPlan,
			'round' => 1,
			'phase' => 'duel',
			'score' => 0,
			'streak' => 0,
			'hits' => 0,
			'challenge' => $challenge,
			'dialogue' => [],
			'probes_remaining' => self::PROBES_PER_ROUND,
			'verdict' => null,
			'answers' => []
		];
	}

	/** Accept the agent's structured single-thesis output; never trust it as a rule change. */
	public static function validateChallenge(array $generated): array {
		$source = $generated['source'] ?? '';
		if ($source !== '') {
			$source = self::requiredText($source, 300);
		}
		return [
			'topic' => self::requiredText($generated['topic'] ?? null, 120),
			'claim' => self::requiredText($generated['claim'] ?? null, 950),
			'flaw' => self::requiredText($generated['flaw'] ?? null, 1600),
			'correction' => self::requiredText($generated['correction'] ?? null, 2000),
			'source' => $source,
			'taunt' => self::requiredText($generated['taunt'] ?? null, 350)
		];
	}

	/** Verifies player input before it is sent to the configured model or stored. */
	public static function validatePlayerText(mixed $text, int $minCharacters = 3): string {
		if (!is_string($text) || preg_match('//u', $text) !== 1) {
			throw new InvalidArgumentException('blindspot.invalid_argument');
		}
		$text = trim($text);
		$characters = preg_match_all('/./us', $text);
		if ($characters === false || $characters < $minCharacters || $characters > 550 ||
			strlen($text) > 2400 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
			throw new InvalidArgumentException('blindspot.invalid_argument');
		}
		return $text;
	}

	/** Commit one real exchange, subtracting one interrogation opportunity. */
	public static function probe(array $state, string $question, string $reply): array {
		self::requireDuel($state);
		if ($state['probes_remaining'] < 1) { throw new RuntimeException('blindspot.probes_exhausted'); }
		$question = self::validatePlayerText($question);
		$reply = self::requiredText($reply, 1100);
		$state['dialogue'][] = ['question' => $question, 'reply' => $reply];
		$state['probes_remaining']--;
		return $state;
	}

	/** The model assesses substance, but only this method awards points and ends a round. */
	public static function accuse(array $state, string $accusation, bool $correct, string $feedback): array {
		self::requireDuel($state);
		$accusation = self::validatePlayerText($accusation, 12);
		$feedback = self::requiredText($feedback, 1600);
		$streak = $correct ? $state['streak'] + 1 : 0;
		$awarded = $correct ? 100 + 25 * min(3, $streak - 1) : 0;
		$state['score'] += $awarded;
		$state['hits'] += $correct ? 1 : 0;
		$state['streak'] = $streak;
		$state['verdict'] = [
			'accusation' => $accusation,
			'correct' => $correct,
			'feedback' => $feedback,
			'awarded' => $awarded
		];
		$state['answers'][] = [
			'round' => $state['round'],
			'claim' => $state['challenge']['claim'],
			'accusation' => $accusation,
			'correct' => $correct,
			'awarded' => $awarded,
			'correction' => $state['challenge']['correction'],
			'source' => $state['challenge']['source'],
			'error_type' => self::DECEPTION_TYPES[$state['round'] - 1],
			'probes_used' => self::PROBES_PER_ROUND - $state['probes_remaining']
		];
		$state['phase'] = 'reveal';
		return $state;
	}

	/** Start a freshly generated duel, or mark the fifth confrontation complete. */
	public static function next(array $state, ?array $generated = null): array {
		if (($state['format'] ?? null) !== self::FORMAT || ($state['phase'] ?? null) !== 'reveal') {
			throw new RuntimeException('blindspot.invalid_phase');
		}
		if ($state['round'] === self::ROUNDS) {
			if ($generated !== null) { throw new RuntimeException('blindspot.invalid_phase'); }
			$state['phase'] = 'finished';
			return $state;
		}
		if ($generated === null) { throw new RuntimeException('blindspot.invalid_challenges'); }
		$state['challenge'] = self::validateChallenge($generated);
		$state['topic'] = $state['challenge']['topic'];
		$state['round']++;
		$state['phase'] = 'duel';
		$state['dialogue'] = [];
		$state['probes_remaining'] = self::PROBES_PER_ROUND;
		$state['verdict'] = null;
		return $state;
	}

	/** Hide the flaw and correction until the accusation is committed. */
	public static function publicState(array $state): array {
		if (($state['format'] ?? null) !== self::FORMAT) {
			if (($state['phase'] ?? null) !== 'finished') {
				throw new RuntimeException('blindspot.legacy_match');
			}
			return [
				'id' => $state['id'], 'revision' => $state['revision'],
				'topic' => $state['topic'], 'round' => self::ROUNDS,
				'total' => self::ROUNDS, 'phase' => 'finished',
				'score' => $state['score'], 'hits' => $state['hits'],
				'streak' => $state['streak'] ?? 0, 'review' => []
			];
		}
		$public = [
			'id' => $state['id'],
			'revision' => $state['revision'],
			'topic' => $state['topic'],
			'round' => $state['round'],
			'total' => self::ROUNDS,
			'phase' => $state['phase'],
			'score' => $state['score'],
			'hits' => $state['hits'],
			'streak' => $state['streak']
		];
		if ($state['phase'] === 'finished') {
			$public['review'] = $state['answers'];
			return $public;
		}
		$challenge = $state['challenge'];
		$public['challenge'] = [
			'claim' => $challenge['claim'],
			'taunt' => $challenge['taunt'],
			'deception_type' => self::DECEPTION_TYPES[$state['round'] - 1],
			'dialogue' => $state['dialogue'],
			'probes_remaining' => $state['probes_remaining'],
			'probes_total' => self::PROBES_PER_ROUND
		];
		if ($state['phase'] === 'reveal') {
			$public['challenge']['verdict'] = $state['verdict'];
			$public['challenge']['flaw'] = $challenge['flaw'];
			$public['challenge']['correction'] = $challenge['correction'];
			$public['challenge']['source'] = $challenge['source'];
		}
		return $public;
	}

	public static function requireDuel(array $state): void {
		if (($state['format'] ?? null) !== self::FORMAT || ($state['phase'] ?? null) !== 'duel') {
			throw new RuntimeException('blindspot.invalid_phase');
		}
	}

	private static function requiredText(mixed $value, int $maxBytes): string {
		if (!is_string($value)) { throw new InvalidArgumentException('blindspot.invalid_challenges'); }
		$value = trim($value);
		if ($value === '' || strlen($value) > $maxBytes || preg_match('//u', $value) !== 1 ||
			preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
			throw new InvalidArgumentException('blindspot.invalid_challenges');
		}
		return $value;
	}
}

<?php declare(strict_types=1);

namespace Blindspot\Test\Fixture;

use Blindspot\Game\BlindspotRules;

/** One structured generative claim, with a private solution for deterministic tests. */
final class MatchData {

	public static function challenge(string $topic = 'IT Security', int $round = 1): array {
		return [
			'topic' => $topic,
			'claim' => 'The system is always secure if the network connection is encrypted. Round ' . $round . '.',
			'flaw' => 'Encryption does not establish the trustworthiness of the endpoint.',
			'correction' => 'Transport security and endpoint security are separate properties.',
			'source' => 'Sample training document.pdf, section 3.2',
			'taunt' => 'You cannot find a weakness in my reasoning.'
		];
	}

	/** Themes reported by the configured agent; no test fixture reads documents. */
	public static function topicCatalog(): array {
		return [
			['document' => 'Mechanik', 'topic' => 'Beschleunigung', 'section' => 'Bewegung'],
			['document' => 'Mechanik', 'topic' => 'Kraft und Masse', 'section' => 'Dynamik'],
			['document' => 'Elektrizitätslehre', 'topic' => 'Ohmsches Gesetz', 'section' => 'Stromkreise'],
			['document' => 'Elektrizitätslehre', 'topic' => 'Elektrische Leistung', 'section' => 'Energie'],
			['document' => 'Magnetismus', 'topic' => 'Induktion', 'section' => 'Magnetfelder'],
			['document' => 'Magnetismus', 'topic' => 'Elektromagnet', 'section' => 'Spule'],
			['document' => 'Optik', 'topic' => 'Totalreflexion', 'section' => 'Brechung'],
			['document' => 'Optik', 'topic' => 'Linsenabbildung', 'section' => 'Linsen'],
			['document' => 'Atomphysik', 'topic' => 'Massenzahl', 'section' => 'Kernaufbau'],
			['document' => 'Atomphysik', 'topic' => 'Halbwertszeit', 'section' => 'Radioaktivität']
		];
	}

	/** Deterministic five-subject fixture; production selection is random. */
	public static function topicPlan(): array {
		$catalog = self::topicCatalog();
		return [$catalog[0], $catalog[2], $catalog[4], $catalog[6], $catalog[8]];
	}

	public static function state(string $id = '0123456789abcdef0123456789abcdef'): array {
		return BlindspotRules::create($id, self::challenge(), self::topicPlan());
	}

	public static function judgement(bool $correct = true): array {
		return [
			'correct' => $correct,
			'feedback' => $correct ? 'Correct: encryption does not make the sender trustworthy.'
				: 'Your claim does not address the difference between the channel and the sender.'
		];
	}
}

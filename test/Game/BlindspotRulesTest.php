<?php declare(strict_types=1);

namespace Blindspot\Test\Game;

use Blindspot\Game\BlindspotRules;
use Blindspot\Test\Fixture\MatchData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlindspotRulesTest extends TestCase {

	private const ID = '0123456789abcdef0123456789abcdef';

	public function testCreatesSingleThesisWithoutExposingHiddenKey(): void {
		$state = MatchData::state();
		$public = BlindspotRules::publicState($state);
		self::assertSame('duel', $public['phase']);
		self::assertSame(4, $public['challenge']['probes_remaining']);
		self::assertSame(MatchData::challenge()['claim'], $public['challenge']['claim']);
		self::assertSame([], $public['challenge']['dialogue']);
		self::assertArrayNotHasKey('flaw', $public['challenge']);
		self::assertArrayNotHasKey('correction', $public['challenge']);
		self::assertArrayNotHasKey('source', $public['challenge']);
		self::assertArrayNotHasKey('answers', $public);
		self::assertArrayNotHasKey('questions', $public);
	}

	public function testTopicPlanSpreadsFiveRoundsAcrossFiveDocuments(): void {
		for ($trial = 0; $trial < 30; $trial++) {
			$selected = BlindspotRules::selectTopics(MatchData::topicCatalog());
			self::assertCount(5, $selected);
			self::assertCount(5, array_unique(array_column($selected, 'document')));
			self::assertCount(5, array_unique(array_column($selected, 'topic')));
			$match = BlindspotRules::create(self::ID, MatchData::challenge(), $selected);
			self::assertSame($selected, $match['topic_plan']);
			self::assertArrayNotHasKey('topic_plan', BlindspotRules::publicState($match));
		}
	}

	public function testTopicPlanUsesFiveDistinctTopicsWhenOnlyOneDocumentExists(): void {
		$singleDocument = [];
		foreach (['Kraft', 'Impuls', 'Beschleunigung', 'Energie', 'Reibung', 'Drehmoment'] as $topic) {
			$singleDocument[] = ['document' => 'Mechanik', 'topic' => $topic, 'section' => ''];
		}
		$selected = BlindspotRules::selectTopics($singleDocument);
		self::assertCount(5, $selected);
		self::assertCount(5, array_unique(array_column($selected, 'topic')));
		self::assertSame(['Mechanik'], array_unique(array_column($selected, 'document')));
	}

	public function testTopicPlanDoesNotRepeatDuplicateTopicNamesAcrossDocuments(): void {
		$candidates = MatchData::topicCatalog();
		$candidates[] = ['document' => 'Ergänzung', 'topic' => 'Halbwertszeit', 'section' => 'Andere Stelle'];
		$selected = BlindspotRules::selectTopics($candidates);
		self::assertCount(5, array_unique(array_column($selected, 'topic')));
	}

	public function testHundredDocumentLabelsCanSupplyFiveRoundTopics(): void {
		$candidates = [];
		for ($index = 1; $index <= 100; $index++) {
			$candidates[] = [
				'document' => 'Wissensdokument ' . $index,
				'topic' => 'Eigenständiges Fachthema ' . $index,
				'section' => ''
			];
		}
		$selected = BlindspotRules::selectTopics($candidates);
		self::assertCount(5, $selected);
		self::assertCount(5, array_unique(array_column($selected, 'document')));
	}

	public function testInsufficientDistinctTopicsAreRejected(): void {
		$candidates = array_fill(0, 8, ['document' => 'Atomphysik', 'topic' => 'Halbwertszeit', 'section' => '10.1']);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('blindspot.invalid_challenges');
		BlindspotRules::selectTopics($candidates);
	}

	public function testInterrogationIsServerRecordedAndLimitedToFourTurns(): void {
		$state = MatchData::state();
		for ($i = 0; $i < 4; $i++) {
			$state = BlindspotRules::probe($state, 'What is your assumption?', 'The network is encrypted.');
		}
		self::assertSame(0, $state['probes_remaining']);
		self::assertCount(4, BlindspotRules::publicState($state)['challenge']['dialogue']);
		self::assertArrayNotHasKey('flaw', BlindspotRules::publicState($state)['challenge']);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.probes_exhausted');
		BlindspotRules::probe($state, 'One more?', 'Not allowed.');
	}

	public function testAccusationRevealsTruthAndAwardsOnlyServerCalculatedPoints(): void {
		$state = BlindspotRules::probe(MatchData::state(), 'Is the sender trustworthy?', 'That is a separate matter.');
		$state = BlindspotRules::accuse($state, 'The claim confuses encryption with sender trust.', true,
			'You exposed the hidden flaw.');
		self::assertSame('reveal', $state['phase']);
		self::assertSame(100, $state['score']);
		self::assertSame(1, $state['hits']);
		$public = BlindspotRules::publicState($state);
		self::assertSame('Encryption does not establish the trustworthiness of the endpoint.',
			$public['challenge']['flaw']);
		self::assertSame('You exposed the hidden flaw.', $public['challenge']['verdict']['feedback']);
		self::assertSame(1, $state['answers'][0]['probes_used']);
		self::assertSame('The claim confuses encryption with sender trust.', $state['answers'][0]['accusation']);
	}

	public function testFiveGeneratedConfrontationsAndStreakPoints(): void {
		$state = MatchData::state();
		foreach ([true, false, true, true, true] as $index => $correct) {
			$state = BlindspotRules::accuse($state, 'Encryption is not a guarantee of endpoint integrity.',
				$correct, 'The answer was evaluated.');
			if ($index < 4) {
				$state = BlindspotRules::next($state, MatchData::challenge('IT Security', $index + 2));
				self::assertSame('duel', $state['phase']);
				self::assertSame($index + 2, $state['round']);
				self::assertArrayNotHasKey('flaw', BlindspotRules::publicState($state)['challenge']);
			} else {
				$state = BlindspotRules::next($state);
			}
		}
		self::assertSame('finished', $state['phase']);
		self::assertSame(475, $state['score']);
		self::assertSame(4, $state['hits']);
		$review = BlindspotRules::publicState($state)['review'];
		self::assertCount(5, $review);
		self::assertFalse($review[1]['correct']);
		self::assertSame([100, 0, 100, 125, 150], array_column($review, 'awarded'));
		self::assertArrayNotHasKey('flaw', BlindspotRules::publicState($state));
	}

	public function testNextConfrontationUsesItsOwnSelectedDocumentTopic(): void {
		$state = BlindspotRules::accuse(MatchData::state(), 'A reliable sender is not ensured by encryption.',
			true, 'Correct.');
		$next = MatchData::challenge('Optik', 2);
		$next['source'] = 'Optik: Licht und seine Eigenschaften, chapter 3.1 Reflexion';
		$state = BlindspotRules::next($state, $next);
		self::assertSame('Optik', $state['topic']);
		self::assertSame('Optik', BlindspotRules::publicState($state)['topic']);
		self::assertSame($next['source'], $state['challenge']['source']);
	}

	public function testCannotProbeOrAccuseAfterReveal(): void {
		$state = BlindspotRules::accuse(MatchData::state(), 'There is an endpoint trust issue.', true,
			'Correctly reasoned.');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.invalid_phase');
		BlindspotRules::probe($state, 'Another question?', 'Not permitted.');
	}

	public function testInvalidPlayerArgumentRejected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('blindspot.invalid_argument');
		BlindspotRules::validatePlayerText(str_repeat('x', 551));
	}

	public function testInvalidGeneratedClaimRejected(): void {
		$challenge = MatchData::challenge();
		$challenge['claim'] = '';
		$this->expectException(InvalidArgumentException::class);
		BlindspotRules::create(self::ID, $challenge, MatchData::topicPlan());
	}

	public function testReferenceLabelIsOptionalAndNotAStartGate(): void {
		$challenge = MatchData::challenge();
		$challenge['source'] = '';
		$state = BlindspotRules::create(self::ID, $challenge, MatchData::topicPlan());
		self::assertSame('', $state['challenge']['source']);
		$state = BlindspotRules::accuse($state, 'This argument confuses two different claims.', true, 'Correct.');
		self::assertSame('', BlindspotRules::publicState($state)['challenge']['source']);
	}

	public function testOldFinishedMatchRemainsReadableButUnfinishedQuizCannotContinue(): void {
		$old = ['id' => self::ID, 'revision' => 3, 'topic' => 'Old Quiz',
			'round' => 5, 'phase' => 'finished', 'score' => 350, 'hits' => 3];
		self::assertSame([], BlindspotRules::publicState($old)['review']);
		$old['phase'] = 'question';
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.legacy_match');
		BlindspotRules::publicState($old);
	}
}

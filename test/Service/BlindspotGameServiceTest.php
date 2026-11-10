<?php declare(strict_types=1);

namespace Blindspot\Test\Service;

use AssistantFoundation\Dto\AgentExecutionResult;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use Blindspot\Game\BlindspotRules;
use Blindspot\Repository\BlindspotMatchRepository;
use Blindspot\Service\BlindspotAccessService;
use Blindspot\Service\BlindspotAgentService;
use Blindspot\Service\BlindspotGameService;
use Blindspot\Test\Fixture\FakeAgentConfiguration;
use Blindspot\Test\Fixture\FakeAgentExecution;
use Blindspot\Test\Fixture\MatchData;
use Blindspot\Test\Fixture\RecordingDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlindspotGameServiceTest extends TestCase {

	private const ID = '0123456789abcdef0123456789abcdef';

	public function testStartPersistsOneChallengeAfterAgentExecution(): void {
		$database = new RecordingDatabase();
		$execution = new FakeAgentExecution($this->createAgentEnvelope([]));
		$execution->responses = [
			$this->createAgentEnvelope(['topics' => MatchData::topicCatalog()]),
			$this->createAgentEnvelope(MatchData::challenge())
		];
		$public = $this->createGameService($database, $execution)->start();
		self::assertSame('duel', $public['phase']);
		self::assertSame(2, $execution->calls);
		self::assertArrayNotHasKey('flaw', $public['challenge']);
		self::assertArrayNotHasKey('topic_plan', $public);
		self::assertSame(4, $public['challenge']['probes_remaining']);
		self::assertStringContainsString('INSERT INTO blindspot_matches', end($database->queries));
	}

	public function testInvalidGeneratedChallengeCannotBeSaved(): void {
		$database = new RecordingDatabase();
		$execution = new FakeAgentExecution($this->createAgentEnvelope([]));
		$execution->responses = [
			$this->createAgentEnvelope(['topics' => MatchData::topicCatalog()]),
			$this->createAgentEnvelope(['topic' => 'Missing argument'])
		];
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.agent_invalid_response');
		try { $this->createGameService($database, $execution)->start(); }
		finally { self::assertSame([], $database->queries); }
	}

	public function testGameAcceptsAgentChallengeWithoutOptionalSourceLabel(): void {
		$database = new RecordingDatabase();
		$challenge = MatchData::challenge();
		$challenge['source'] = '';
		$execution = new FakeAgentExecution($this->createAgentEnvelope([]));
		$execution->responses = [
			$this->createAgentEnvelope(['topics' => MatchData::topicCatalog()]),
			$this->createAgentEnvelope($challenge)
		];
		$public = $this->createGameService($database, $execution)->start();
		self::assertSame('duel', $public['phase']);
		self::assertSame(2, $execution->calls);
		self::assertStringContainsString('INSERT INTO blindspot_matches', end($database->queries));
	}

	public function testProbeCommitsOnlyValidatedDialogueWithRevisionControl(): void {
		$database = new RecordingDatabase();
		$database->nextRow = ['revision' => 0, 'payload' => json_encode(MatchData::state(), JSON_THROW_ON_ERROR)];
		$execution = new FakeAgentExecution($this->createAgentEnvelope(['reply' => 'Do you trust the sender?']));
		$public = $this->createGameService($database, $execution)->probe(self::ID, 0, 'What about the sender?');
		self::assertSame(1, $public['revision']);
		self::assertSame(3, $public['challenge']['probes_remaining']);
		self::assertSame('Do you trust the sender?', $public['challenge']['dialogue'][0]['reply']);
		self::assertArrayNotHasKey('flaw', $public['challenge']);
		self::assertSame(1, $execution->calls);
		self::assertStringContainsString('AND revision = 0', end($database->queries));
	}

	public function testAccusationIsJudgedByAgentButScoredByRules(): void {
		$database = new RecordingDatabase();
		$database->nextRow = ['revision' => 0, 'payload' => json_encode(MatchData::state(), JSON_THROW_ON_ERROR)];
		$execution = new FakeAgentExecution($this->createAgentEnvelope(MatchData::judgement()));
		$public = $this->createGameService($database, $execution)->accuse(self::ID, 0,
			'Encryption is not proof of trustworthy endpoints.');
		self::assertSame('reveal', $public['phase']);
		self::assertTrue($public['challenge']['verdict']['correct']);
		self::assertSame(100, $public['score']);
		self::assertSame(1, $execution->calls);
		self::assertStringContainsString('Encryption is not proof', end($database->queries));
	}

	public function testNextGeneratesOneNewArgumentInsteadOfFivePreparedQuestions(): void {
		$database = new RecordingDatabase();
		$state = BlindspotRules::accuse(MatchData::state(),
			'Encryption does not prove endpoint integrity.', true, 'Correct.');
		$database->nextRow = ['revision' => 0, 'payload' => json_encode($state, JSON_THROW_ON_ERROR)];
		$execution = new FakeAgentExecution($this->createAgentEnvelope(MatchData::challenge('IT Security', 2)));
		$public = $this->createGameService($database, $execution)->next(self::ID, 0);
		self::assertSame('duel', $public['phase']);
		self::assertSame(2, $public['round']);
		self::assertSame(4, $public['challenge']['probes_remaining']);
		self::assertSame(1, $execution->calls);
		$prompt = $execution->request->getInputs()['prompt'];
		self::assertStringContainsString('Eröffne Runde 2 von 5', $prompt);
		self::assertStringContainsString('Das vom Spiel ausgewählte Dokument', $prompt);
		self::assertStringContainsString('Wissensdokumente', $execution->request->getInputs()['system']);
		self::assertStringNotContainsString('Encryption does not prove endpoint integrity.', $prompt);
		self::assertStringNotContainsString('bereits gespielten Thesen', $prompt);
		self::assertArrayNotHasKey('conversation_id', $execution->request->getContext());
		self::assertSame(MatchData::topicPlan()[1]['topic'], $public['topic']);
	}

	public function testNewMatchSchedulesFiveDifferentDocumentTopicsWithoutExposingPlan(): void {
		$database = new RecordingDatabase();
		$execution = new FakeAgentExecution($this->createAgentEnvelope([]));
		$execution->responses = [
			$this->createAgentEnvelope(['topics' => MatchData::topicCatalog()]),
			$this->createAgentEnvelope(MatchData::challenge())
		];
		$public = $this->createGameService($database, $execution)->start();
		self::assertSame(2, $execution->calls);
		self::assertArrayNotHasKey('topic_plan', $public);
		self::assertStringContainsString('Themeninventar', $execution->requests[0]->getInputs()['system']);
		self::assertStringContainsString('Das vom Spiel ausgewählte Dokument', $execution->requests[1]->getInputs()['prompt']);
		self::assertStringNotContainsString('previous_claims', $execution->requests[1]->getInputs()['prompt']);
		self::assertArrayNotHasKey('conversation_id', $execution->requests[1]->getContext());
		self::assertStringContainsString('INSERT INTO blindspot_matches', end($database->queries));
	}

	public function testInvalidTopicCatalogDoesNotPersistAMatchOrGenerateAThesis(): void {
		$database = new RecordingDatabase();
		$execution = new FakeAgentExecution($this->createAgentEnvelope([
			'topics' => [['document' => 'Only', 'topic' => 'Halbwertszeit', 'section' => '10.1']]
		]));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.agent_invalid_response');
		try { $this->createGameService($database, $execution)->start(); }
		finally {
			self::assertSame(1, $execution->calls);
			self::assertSame([], $database->queries);
		}
	}

	public function testPreUpdateActiveMatchReceivesPlanAtNextRound(): void {
		$database = new RecordingDatabase();
		$state = BlindspotRules::accuse(MatchData::state(),
			'Encryption does not prove endpoint integrity.', true, 'Correct.');
		unset($state['topic_plan']);
		$database->nextRow = ['revision' => 0, 'payload' => json_encode($state, JSON_THROW_ON_ERROR)];
		$execution = new FakeAgentExecution($this->createAgentEnvelope([]));
		$execution->responses = [
			$this->createAgentEnvelope(['topics' => MatchData::topicCatalog()]),
			$this->createAgentEnvelope(MatchData::challenge('Optik', 2))
		];
		$public = $this->createGameService($database, $execution)->next(self::ID, 0);
		self::assertSame(2, $public['round']);
		self::assertSame(2, $execution->calls);
		self::assertStringContainsString('Themeninventar', $execution->requests[0]->getInputs()['system']);
		self::assertStringContainsString('Das vom Spiel ausgewählte Dokument',
			$execution->requests[1]->getInputs()['prompt']);
		self::assertArrayNotHasKey('topic_plan', $public);
	}

	public function testInvalidOrStaleInputNeverCallsTheModel(): void {
		$database = new RecordingDatabase();
		$database->nextRow = ['revision' => 1, 'payload' => json_encode(MatchData::state(), JSON_THROW_ON_ERROR)];
		$execution = new FakeAgentExecution($this->createAgentEnvelope([]));
		$game = $this->createGameService($database, $execution);
		try { $game->probe(self::ID, 0, 'Is this reliable?'); self::fail('Accepted a stale match'); }
		catch (RuntimeException $error) { self::assertSame('blindspot.stale_state', $error->getMessage()); }
		self::assertSame(0, $execution->calls);
		$this->expectException(\InvalidArgumentException::class);
		try { $game->accuse(self::ID, 1, 'No'); }
		finally { self::assertSame(0, $execution->calls); }
	}

	public function testDeniedActorAfterSlowAgentRunCannotSave(): void {
		$database = new RecordingDatabase();
		$execution = new FakeAgentExecution($this->createAgentEnvelope([]));
		$execution->responses = [
			$this->createAgentEnvelope(['topics' => MatchData::topicCatalog()]),
			$this->createAgentEnvelope(MatchData::challenge())
		];
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn(User::fromArray(['id' => 17]));
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$checks = 0;
		$authorization->method('decide')->willReturnCallback(static function() use (&$checks) {
			return ++$checks === 1 ? AuthorizationDecision::allow() : AuthorizationDecision::deny();
		});
		$game = new BlindspotGameService(new BlindspotMatchRepository($database),
			new BlindspotAccessService($users, $authorization),
			new BlindspotAgentService(new FakeAgentConfiguration(['enabled' => true]), $execution));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.access_denied');
		try { $game->start(); }
		finally { self::assertSame([], $database->queries); }
	}

	private function createGameService(RecordingDatabase $database, FakeAgentExecution $execution): BlindspotGameService {
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn(User::fromArray(['id' => 17]));
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willReturn(AuthorizationDecision::allow());
		return new BlindspotGameService(new BlindspotMatchRepository($database),
			new BlindspotAccessService($users, $authorization),
			new BlindspotAgentService(new FakeAgentConfiguration(['enabled' => true]), $execution));
	}

	private function createAgentEnvelope(array $payload): AgentExecutionResult {
		return new AgentExecutionResult(['assistant' => ['message' => [
			'content' => json_encode($payload, JSON_THROW_ON_ERROR)
		]]]);
	}
}

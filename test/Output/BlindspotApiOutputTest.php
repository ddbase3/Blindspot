<?php declare(strict_types=1);

namespace Blindspot\Test\Output;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Database\Api\IDatabase;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use Blindspot\Output\BlindspotApiOutput;
use Blindspot\Repository\BlindspotMatchRepository;
use Blindspot\Service\BlindspotAccessService;
use Blindspot\Service\BlindspotAgentService;
use Blindspot\Service\BlindspotGameService;
use Blindspot\Test\Fixture\RecordingDatabase;
use PHPUnit\Framework\TestCase;

final class BlindspotApiOutputTest extends TestCase {

	protected function setUp(): void { http_response_code(200); }

	public function testRejectsForeignOriginBeforeReadingBody(): void {
		$request = $this->createRequestMock('POST', 'cross-site');
		$request->expects(self::never())->method('getJsonBody');
		$body = json_decode($this->createApiOutput($request)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('invalid_origin', $body['error']['code']);
		self::assertSame(403, http_response_code());
	}

	public function testRequiresPost(): void {
		$request = $this->createRequestMock('GET', 'same-origin');
		$body = json_decode($this->createApiOutput($request)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('post_only', $body['error']['code']);
	}

	public function testResumeWithoutMatchReturnsNull(): void {
		$request = $this->createRequestMock('POST', 'same-origin');
		$request->method('getJsonBody')->willReturn(['action' => 'resume']);
		$database = $this->createMock(IDatabase::class);
		$database->method('singleQuery')->willReturn(null);
		$body = json_decode($this->createApiOutput($request, $database)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertTrue($body['ok']);
		self::assertNull($body['state']);
	}

	public function testHistoryStillUsesAuthorizedOwnerScopedRepository(): void {
		$request = $this->createRequestMock('POST', 'same-origin');
		$request->method('getJsonBody')->willReturn(['action' => 'history']);
		$database = new RecordingDatabase();
		$database->nextRow = ['completed' => '0', 'best' => '0'];
		$body = json_decode($this->createApiOutput($request, $database)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertTrue($body['ok']);
		self::assertSame(['completed' => 0, 'best' => 0, 'items' => []], $body['history']);
		self::assertStringContainsString("owner_id = '17'", $database->queries[1]);
	}

	public function testProbeRejectsNonTextBeforeDatabaseRead(): void {
		$request = $this->createRequestMock('POST', 'same-origin');
		$request->method('getJsonBody')->willReturn(['action' => 'probe', 'match_id' => str_repeat('a', 32),
			'revision' => 0, 'question' => ['not a string']]);
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::never())->method('singleQuery');
		$body = json_decode($this->createApiOutput($request, $database)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('invalid_argument', $body['error']['code']);
	}

	public function testAccusationRejectsInvalidRevisionBeforeDatabaseRead(): void {
		$request = $this->createRequestMock('POST', 'same-origin');
		$request->method('getJsonBody')->willReturn(['action' => 'accuse', 'match_id' => str_repeat('a', 32),
			'revision' => '0', 'accusation' => 'The endpoint is not trustworthy.']);
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::never())->method('singleQuery');
		$body = json_decode($this->createApiOutput($request, $database)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('stale_state', $body['error']['code']);
	}

	public function testOldMultipleChoiceActionIsNotAnAlternativeGameRoute(): void {
		$request = $this->createRequestMock('POST', 'same-origin');
		$request->method('getJsonBody')->willReturn(['action' => 'answer', 'match_id' => str_repeat('a', 32),
			'revision' => 0, 'selection' => 1]);
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::never())->method('singleQuery');
		$body = json_decode($this->createApiOutput($request, $database)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('invalid_action', $body['error']['code']);
	}

	private function createRequestMock(string $method, string $origin): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('server')->willReturnCallback(static function(string $key, mixed $default = null) use ($method, $origin): mixed {
			return match($key) {
				'REQUEST_METHOD' => $method,
				'HTTP_SEC_FETCH_SITE' => $origin,
				'CONTENT_TYPE' => 'application/json',
				default => $default
			};
		});
		return $request;
	}

	private function createApiOutput(IRequest $request, ?IDatabase $database = null): BlindspotApiOutput {
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn(User::fromArray(['id' => 17]));
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willReturn(AuthorizationDecision::allow());
		$access = new BlindspotAccessService($users, $authorization);
		$agent = new BlindspotAgentService($this->createMock(IAgentConfigurationService::class),
			$this->createMock(IAgentExecutionService::class));
		$game = new BlindspotGameService(new BlindspotMatchRepository($database ?? $this->createMock(IDatabase::class)),
			$access, $agent);
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->willReturn([
			'invalid_origin' => 'Invalid origin', 'post_only' => 'POST required',
			'invalid_action' => 'Unknown action', 'invalid_argument' => 'Invalid argument',
			'stale_state' => 'Stale revision', 'unexpected_error' => 'Unknown issue'
		]);
		return new BlindspotApiOutput($request, $view, $access, $game);
	}
}

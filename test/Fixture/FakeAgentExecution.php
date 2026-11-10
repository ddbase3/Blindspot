<?php declare(strict_types=1);

namespace Blindspot\Test\Fixture;

use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;

/** Records Foundation requests; responses may be queued for successive agent tasks. */
final class FakeAgentExecution implements IAgentExecutionService {

	public ?AgentExecutionRequest $request = null;
	public int $calls = 0;
	/** @var AgentExecutionRequest[] */
	public array $requests = [];
	/** @var AgentExecutionResult[] */
	public array $responses = [];

	public function __construct(public AgentExecutionResult $response) {}

	public static function getName(): string { return 'fakeagentexecution'; }

	public function execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null): AgentExecutionResult {
		$this->calls++;
		$this->request = $request;
		$this->requests[] = $request;
		return $this->responses === [] ? $this->response : array_shift($this->responses);
	}
}

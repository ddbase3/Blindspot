<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Blindspot for BASE3 Framework.
 *
 * Blindspot provides a conversational AI confrontation game for BASE3.
 * It owns match rules, game history and the interactive display.
 * Agents execute through the existing AssistantFoundation contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\Blindspot\Test\Fixture;

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;

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

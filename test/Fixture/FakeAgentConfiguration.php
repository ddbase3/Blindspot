<?php declare(strict_types=1);

namespace Blindspot\Test\Fixture;

use AssistantFoundation\Api\IAgentConfigurationService;

/** Deterministic test double. */
final class FakeAgentConfiguration implements IAgentConfigurationService {

	public function __construct(public ?array $settings = null) {}
	public function getAgents(string $settingsGroup = 'agent'): array {
		return $this->settings === null ? [] : ['blindspot.opponent' => $this->settings];
	}
	public function getAgent(string $id, string $settingsGroup = 'agent'): ?array {
		return $id === 'blindspot.opponent' && $settingsGroup === 'agent' ? $this->settings : null;
	}
	public function hasAgent(string $id, string $settingsGroup = 'agent'): bool {
		return $this->getAgent($id, $settingsGroup) !== null;
	}
	public function isFixed(string $id): bool { return $id === 'blindspot.opponent'; }
	public function saveAgent(string $id, array $settings): void { $this->settings = $settings; }
	public function deleteAgent(string $id): void { throw new \LogicException('Fixed agent'); }
	public function reload(): void {}
}

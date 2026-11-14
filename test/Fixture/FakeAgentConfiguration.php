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

use Base3\AssistantFoundation\Api\IAgentConfigurationService;

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

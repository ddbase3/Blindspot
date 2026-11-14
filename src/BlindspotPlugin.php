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


namespace Base3\Blindspot;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\Blindspot\Repository\BlindspotMatchRepository;
use Base3\Blindspot\Service\BlindspotAccessService;
use Base3\Blindspot\Service\BlindspotAgentService;
use Base3\Blindspot\Service\BlindspotGameService;

/** Binds only game-owned services. The fixed agent, feature, display and migration are class-map discovered. */
final class BlindspotPlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string { return 'blindspotplugin'; }

	public function init(): void {
		$this->container->set(BlindspotMatchRepository::class,
			fn(IContainer $c) => new BlindspotMatchRepository($c->get(IDatabase::class)),
			IContainer::SHARED | IContainer::NOOVERWRITE);
		$this->container->set(BlindspotAccessService::class,
			fn(IContainer $c) => new BlindspotAccessService(
				$c->get(IUsermanager::class), $c->get(IAuthorizationService::class)),
			IContainer::SHARED | IContainer::NOOVERWRITE);
		$this->container->set(BlindspotAgentService::class,
			fn(IContainer $c) => new BlindspotAgentService(
				$c->get(IAgentConfigurationService::class), $c->get(IAgentExecutionService::class)),
			IContainer::SHARED | IContainer::NOOVERWRITE);
		$this->container->set(BlindspotGameService::class,
			fn(IContainer $c) => new BlindspotGameService(
				$c->get(BlindspotMatchRepository::class),
				$c->get(BlindspotAccessService::class),
				$c->get(BlindspotAgentService::class)),
			IContainer::SHARED | IContainer::NOOVERWRITE);
	}
}

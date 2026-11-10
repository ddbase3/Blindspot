<?php declare(strict_types=1);

namespace Blindspot;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Database\Api\IDatabase;
use Base3\Usermanager\Api\IUsermanager;
use Blindspot\Repository\BlindspotMatchRepository;
use Blindspot\Service\BlindspotAccessService;
use Blindspot\Service\BlindspotAgentService;
use Blindspot\Service\BlindspotGameService;

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

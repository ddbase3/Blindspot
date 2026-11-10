<?php declare(strict_types=1);

namespace Blindspot\Test\Plugin;

use Base3\Api\IContainer;
use Blindspot\BlindspotPlugin;
use Blindspot\Repository\BlindspotMatchRepository;
use Blindspot\Service\BlindspotAccessService;
use Blindspot\Service\BlindspotAgentService;
use Blindspot\Service\BlindspotGameService;
use PHPUnit\Framework\TestCase;

final class BlindspotPluginTest extends TestCase {

	public function testPluginOnlyRegistersItsOwnServicesWithNoOverwrite(): void {
		$container = $this->createMock(IContainer::class);
		$names = [];
		$container->expects(self::exactly(4))->method('set')->willReturnCallback(
			static function(string $name, mixed $factory, int $flags) use (&$names, $container): IContainer {
				self::assertInstanceOf(\Closure::class, $factory);
				self::assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $flags);
				$names[] = $name;
				return $container;
			}
		);
		$plugin = new BlindspotPlugin($container);
		self::assertSame('blindspotplugin', $plugin::getName());
		$plugin->init();
		self::assertSame([BlindspotMatchRepository::class, BlindspotAccessService::class,
			BlindspotAgentService::class, BlindspotGameService::class], $names);
	}
}

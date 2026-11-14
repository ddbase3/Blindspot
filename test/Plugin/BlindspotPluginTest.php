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


namespace Base3\Blindspot\Test\Plugin;

use Base3\CoreFoundation\Api\IContainer;
use Base3\Blindspot\BlindspotPlugin;
use Base3\Blindspot\Repository\BlindspotMatchRepository;
use Base3\Blindspot\Service\BlindspotAccessService;
use Base3\Blindspot\Service\BlindspotAgentService;
use Base3\Blindspot\Service\BlindspotGameService;
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

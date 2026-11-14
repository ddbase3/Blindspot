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


namespace Base3\Blindspot\Test\Service;

use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\User;
use Base3\Blindspot\Service\BlindspotAccessService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlindspotAccessServiceTest extends TestCase {

	public function testAuthorizedHostActorAndFeatureDecision(): void {
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn(User::fromArray(['id' => 17]));
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host/instance');
		$authorization->expects(self::once())->method('decide')->with(self::callback(
			static function(AuthorizationRequest $request): bool {
				self::assertSame('host/instance', $request->authority);
				return true;
			}
		))->willReturn(AuthorizationDecision::allow());
		self::assertSame(['authority' => 'host/instance', 'id' => '17'],
			(new BlindspotAccessService($users, $authorization))->requireActor());
	}

	public function testIndeterminateNeverAllows(): void {
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn(User::fromArray(['id' => 17]));
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willReturn(AuthorizationDecision::indeterminate());
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.access_denied');
		(new BlindspotAccessService($users, $authorization))->requireActor();
	}

	public function testMissingNativeIdentityDeniedBeforeAuthorization(): void {
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn(null);
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->expects(self::never())->method('decide');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('blindspot.unauthenticated');
		(new BlindspotAccessService($users, $authorization))->requireActor();
	}
}

<?php declare(strict_types=1);

namespace Blindspot\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use Blindspot\Display\BlindspotDisplay;
use Blindspot\Service\BlindspotAccessService;
use PHPUnit\Framework\TestCase;

final class BlindspotDisplayTest extends TestCase {

	public function testRendersWithHostResolvedApiAssetsAndValidMatchId(): void {
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->willReturn(['access_denied' => 'Denied']);
		$assigned = [];
		$view->method('assign')->willReturnCallback(static function(string $name, mixed $value) use (&$assigned): void {
			$assigned[$name] = $value;
		});
		$view->expects(self::once())->method('setTemplate')->with('Display/BlindspotDisplay.php');
		$view->method('loadTemplate')->willReturn('<section>Blindspot</section>');
		$assets = $this->createMock(IAssetResolver::class);
		$assets->expects(self::exactly(2))->method('resolve')->willReturnCallback(
			static fn(string $path): string => '/assets/' . basename($path));
		$links = $this->createMock(ILinkTargetService::class);
		$links->expects(self::once())->method('getLink')->with(['name' => 'blindspotapioutput', 'out' => 'json'])
			->willReturn('/blindspot.json');
		$display = new BlindspotDisplay($view, $assets, $links, $this->access(true));
		$display->setData(['match_id' => str_repeat('a', 32)]);
		self::assertSame('<section>Blindspot</section>', $display->getOutput());
		self::assertSame('/blindspot.json', $assigned['blindspot_config']['api']);
		self::assertSame(str_repeat('a', 32), $assigned['blindspot_config']['matchId']);
		self::assertSame('/assets/blindspot.css', $assigned['blindspot_css']);
		self::assertSame('/assets/blindspot.js', $assigned['blindspot_js']);
	}

	public function testUntrustedMatchIdCannotBeInjectedIntoTemplateConfig(): void {
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->willReturn(['access_denied' => 'Denied']);
		$assigned = [];
		$view->method('assign')->willReturnCallback(static function(string $name, mixed $value) use (&$assigned): void {
			$assigned[$name] = $value;
		});
		$view->method('loadTemplate')->willReturn('ok');
		$display = new BlindspotDisplay($view, $this->createMock(IAssetResolver::class),
			$this->createMock(ILinkTargetService::class), $this->access(true));
		$display->setData(['match_id' => '<script>']);
		self::assertSame('ok', $display->getOutput());
		self::assertSame('', $assigned['blindspot_config']['matchId']);
	}

	public function testDeniedActorCannotReceiveGameAssets(): void {
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->willReturn(['access_denied' => '<script>denied</script>']);
		$view->expects(self::never())->method('setTemplate');
		$assets = $this->createMock(IAssetResolver::class);
		$assets->expects(self::never())->method('resolve');
		$display = new BlindspotDisplay($view, $assets, $this->createMock(ILinkTargetService::class),
			$this->access(false));
		self::assertSame('<p role="alert">&lt;script&gt;denied&lt;/script&gt;</p>', $display->getOutput());
	}

	private function access(bool $allow): BlindspotAccessService {
		$users = $this->createMock(IUsermanager::class);
		$users->method('getUser')->willReturn(User::fromArray(['id' => 17]));
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willReturn($allow ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		return new BlindspotAccessService($users, $authorization);
	}
}

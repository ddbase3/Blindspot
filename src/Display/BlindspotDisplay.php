<?php declare(strict_types=1);

namespace Blindspot\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\LinkTarget\Api\ILinkTargetService;
use Blindspot\Service\BlindspotAccessService;
use RuntimeException;
use Throwable;

/** Portable BASE3 IDisplay; the API link and asset URLs are resolved by the active host. */
final class BlindspotDisplay implements IDisplay {

	private array $data = [];

	public function __construct(
		private readonly IMvcView $view,
		private readonly IAssetResolver $assets,
		private readonly ILinkTargetService $links,
		private readonly BlindspotAccessService $access
	) {}

	public static function getName(): string { return 'blindspotdisplay'; }

	public function setData($data) { $this->data = is_array($data) ? $data : []; }

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out !== 'html') { return ''; }
		$this->view->setPath(DIR_PLUGIN . 'Blindspot');
		$this->view->loadBricks('Display');
		$text = $this->view->getBricks('blindspotdisplay');
		if (!is_array($text)) { throw new RuntimeException('blindspot.translations_unavailable'); }
		try {
			$this->access->requireActor();
		} catch (Throwable $error) {
			return '<p role="alert">' . htmlspecialchars($text['access_denied'], ENT_QUOTES, 'UTF-8') . '</p>';
		}
		$id = is_string($this->data['match_id'] ?? null) ? $this->data['match_id'] : '';
		if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { $id = ''; }
		$this->view->setTemplate('Display/BlindspotDisplay.php');
		$this->view->assign('blindspot_config', [
			'api' => $this->links->getLink(['name' => 'blindspotapioutput', 'out' => 'json']),
			'matchId' => $id,
			'text' => $text
		]);
		$this->view->assign('blindspot_css', $this->assets->resolve('plugin/Blindspot/assets/blindspot/blindspot.css'));
		$this->view->assign('blindspot_js', $this->assets->resolve('plugin/Blindspot/assets/blindspot/blindspot.js'));
		return $this->view->loadTemplate();
	}
}

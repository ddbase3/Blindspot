<?php declare(strict_types=1);

namespace Blindspot\Authorization;

use Base3\Api\IMvcView;
use Base3\Authorization\Api\IFeature;
use Base3\Usermanager\Permission;
use RuntimeException;

/** Describes the playable feature for the existing BASE3 authorization authority. */
final class BlindspotPlayFeature implements IFeature {

	public function __construct(private readonly IMvcView $view) {}

	public static function getName(): string { return 'blindspotplayfeature'; }

	public function getLabel(): string { return 'BLINDSPOT'; }

	public function getDescription(): string {
		$this->view->setPath(DIR_PLUGIN . 'Blindspot');
		$this->view->loadBricks('Display');
		$text = $this->view->getBricks('blindspotdisplay');
		if (!is_array($text) || !isset($text['feature_description'])) {
			throw new RuntimeException('blindspot.translations_unavailable');
		}
		return $text['feature_description'];
	}

	public function getPermissions(): array { return [Permission::for('feature', self::getName())]; }

	public function getDefaultAudience(): array { return ['group' => 'authenticated']; }
}

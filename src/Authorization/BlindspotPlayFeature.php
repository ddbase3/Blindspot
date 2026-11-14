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


namespace Base3\Blindspot\Authorization;

use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Usermanager\Permission;
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

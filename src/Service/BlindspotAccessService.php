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


namespace Base3\Blindspot\Service;

use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\Blindspot\Authorization\BlindspotPlayFeature;
use RuntimeException;

/** Uses the trusted native actor and the single configured host authorization authority. */
final class BlindspotAccessService {

	public function __construct(
		private readonly IUsermanager $users,
		private readonly IAuthorizationService $authorization
	) {}

	/** @return array{authority:string,id:string} */
	public function requireActor(): array {
		$user = $this->users->getUser();
		$id = $user === null ? '' : trim((string)$user->id);
		if ($id === '') { throw new RuntimeException('blindspot.unauthenticated'); }
		$authority = $this->authorization->getDefaultAuthority();
		$decision = $this->authorization->decide(new AuthorizationRequest(
			$authority, Permission::for('feature', BlindspotPlayFeature::getName())
		));
		if (!$decision->isAllowed()) { throw new RuntimeException('blindspot.access_denied'); }
		return ['authority' => $authority, 'id' => $id];
	}
}

<?php declare(strict_types=1);

namespace Blindspot\Service;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use Blindspot\Authorization\BlindspotPlayFeature;
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

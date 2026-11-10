<?php declare(strict_types=1);

namespace Blindspot\Output;

use Base3\Api\IMvcView;
use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Blindspot\Service\BlindspotAccessService;
use Blindspot\Service\BlindspotGameService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** One BASE3 JSON endpoint for all match actions; no alternate routing path. */
final class BlindspotApiOutput implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly BlindspotAccessService $access,
		private readonly BlindspotGameService $game
	) {}

	public static function getName(): string { return 'blindspotapioutput'; }

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out !== 'json') { return ''; }
		header('Content-Type: application/json; charset=UTF-8');
		header('Cache-Control: no-store, private');
		$this->view->setPath(DIR_PLUGIN . 'Blindspot');
		$this->view->loadBricks('Display');
		$text = $this->view->getBricks('blindspotdisplay');
		if (!is_array($text)) { throw new RuntimeException('blindspot.translations_unavailable'); }
		try {
			$this->access->requireActor();
			if (strtoupper((string)$this->request->server('REQUEST_METHOD', '')) !== 'POST') {
				throw new InvalidArgumentException('blindspot.post_only');
			}
			$this->requireSameOriginJson();
			$body = $this->request->getJsonBody();
			$action = $body['action'] ?? null;
			if (!is_string($action)) { throw new InvalidArgumentException('blindspot.invalid_action'); }
			if ($action === 'start') {
				$payload = ['state' => $this->game->start()];
			} elseif ($action === 'resume') {
				$payload = ['state' => $this->game->latest()];
			} elseif ($action === 'history') {
				$payload = ['history' => $this->game->history()];
			} elseif ($action === 'delete_all') {
				$this->game->deleteOwnMatches();
				$payload = ['state' => null];
			} else {
				$id = $body['match_id'] ?? null;
				if (!is_string($id)) { throw new InvalidArgumentException('blindspot.match_not_found'); }
				$payload = match($action) {
					'snapshot' => ['state' => $this->game->snapshot($id)],
					'probe' => ['state' => $this->game->probe($id, $this->requireRevision($body),
						$this->requireMessage($body, 'question'))],
					'accuse' => ['state' => $this->game->accuse($id, $this->requireRevision($body),
						$this->requireMessage($body, 'accusation'))],
					'next' => ['state' => $this->game->next($id, $this->requireRevision($body))],
					default => throw new InvalidArgumentException('blindspot.invalid_action')
				};
			}
			return json_encode(['ok' => true] + $payload, JSON_THROW_ON_ERROR);
		} catch (Throwable $error) {
			$raw = $error->getMessage();
			$key = str_starts_with($raw, 'blindspot.') ? substr($raw, 10) : 'unexpected_error';
			if (!array_key_exists($key, $text)) { $key = 'unexpected_error'; }
			$status = in_array($key, ['unauthenticated', 'access_denied', 'invalid_origin'], true) ? 403 :
				(in_array($key, ['stale_state', 'invalid_phase', 'probes_exhausted', 'legacy_match'], true) ? 409 : 400);
			if ($key === 'invalid_content_type') { $status = 415; }
			if ($key === 'agent_not_enabled') { $status = 503; }
			if ($key === 'agent_invalid_response') { $status = 502; }
			if (in_array($key, ['unexpected_error', 'invalid_state'], true)) { $status = 500; }
			http_response_code($status);
			return json_encode(['ok' => false, 'error' => ['code' => $key, 'message' => $text[$key]]], JSON_THROW_ON_ERROR);
		}
	}

	private function requireSameOriginJson(): void {
		if ((string)$this->request->server('HTTP_SEC_FETCH_SITE', '') !== 'same-origin') {
			throw new RuntimeException('blindspot.invalid_origin');
		}
		$contentType = strtolower(trim(explode(';', (string)$this->request->server('CONTENT_TYPE', ''))[0]));
		if ($contentType !== 'application/json') {
			throw new InvalidArgumentException('blindspot.invalid_content_type');
		}
	}

	private function requireRevision(array $body): int {
		if (!is_int($body['revision'] ?? null) || $body['revision'] < 0) {
			throw new InvalidArgumentException('blindspot.stale_state');
		}
		return $body['revision'];
	}

	private function requireMessage(array $body, string $key): string {
		if (!is_string($body[$key] ?? null)) {
			throw new InvalidArgumentException('blindspot.invalid_argument');
		}
		return $body[$key];
	}
}

<?php
	$config = $this->_['blindspot_config'];
	$t = $config['text'];
	$h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	$rootId = 'blindspot_' . bin2hex(random_bytes(6));
	$serialized = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
		JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>
<link rel="stylesheet" href="<?php echo $h($this->_['blindspot_css']); ?>">
<section class="bs-root" id="<?php echo $h($rootId); ?>" data-bs-root aria-label="<?php echo $h($t['game_title']); ?>">
	<div class="bs-shell">
		<header class="bs-header">
			<div class="bs-identity">
				<div class="bs-brand-mark" aria-hidden="true"><span></span></div>
				<div><strong>BLIND<span>SPOT</span></strong><small><?php echo $h($t['tagline']); ?></small></div>
			</div>
			<div class="bs-header-right">
				<div class="bs-header-score"><span><?php echo $h($t['score']); ?></span><strong data-bs-score>0</strong><span><?php echo $h($t['points_short']); ?></span></div>
				<div class="bs-display-controls" role="group" aria-label="<?php echo $h($t['display_controls']); ?>">
					<button class="bs-icon-button" type="button" data-bs-motion aria-pressed="false" title="<?php echo $h($t['reduce_motion']); ?>"><span aria-hidden="true">◌</span> <span data-bs-motion-label><?php echo $h($t['reduce_motion']); ?></span></button>
					<button class="bs-icon-button bs-fullscreen-button" type="button" data-bs-fullscreen aria-pressed="false" aria-label="<?php echo $h($t['enter_fullscreen']); ?>" title="<?php echo $h($t['enter_fullscreen']); ?>"><span aria-hidden="true">⛶</span> <span data-bs-fullscreen-label><?php echo $h($t['enter_fullscreen']); ?></span></button>
				</div>
			</div>
		</header>
		<div class="bs-layout">
			<div class="bs-stage">
				<div class="bs-stars" aria-hidden="true"></div>
				<div class="bs-stage-top"><span class="bs-live"><span></span><?php echo $h($t['channel']); ?></span><span class="bs-stage-serial">B3 / BLINDSPOT</span></div>
				<div class="bs-opponent">
					<div class="bs-orb" aria-hidden="true"><div class="bs-orb-ring"></div><div class="bs-orb-core"><div class="bs-orb-eye"></div></div></div>
					<div class="bs-opponent-label"><strong>BLINDSPOT</strong><span><?php echo $h($t['opponent']); ?></span></div>
				</div>
				<div class="bs-broadcast" role="status" aria-live="polite" data-bs-broadcast><?php echo $h($t['opponent_welcome']); ?></div>
				<div class="bs-agent-loading" data-bs-agent-loading hidden role="status" aria-live="polite" aria-atomic="true">
					<div class="bs-agent-loading-card">
						<div class="bs-agent-loading-kicker"><span class="bs-agent-loading-led" aria-hidden="true"></span><?php echo $h($t['loading_heading']); ?></div>
						<div class="bs-agent-loading-reactor" aria-hidden="true"><span></span><span></span><span></span><i></i></div>
						<p class="bs-agent-loading-message" data-bs-agent-loading-message><?php echo $h($t['loading_game']); ?></p>
						<p class="bs-agent-loading-hint"><?php echo $h($t['loading_hint']); ?></p>
						<div class="bs-agent-loading-signal" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
					</div>
				</div>
				<div class="bs-welcome" data-bs-welcome>
					<p class="bs-kicker"><?php echo $h($t['intro_kicker']); ?></p>
					<h2><?php echo $h($t['intro_heading']); ?></h2>
					<p><?php echo $h($t['intro_text']); ?></p>
					<button class="bs-button bs-button-primary" type="button" data-bs-start><?php echo $h($t['intro_action']); ?><span aria-hidden="true"> ↗</span></button>
					<p class="bs-footnote"><?php echo $h($t['intro_small']); ?></p>
				</div>
				<div class="bs-duel" data-bs-duel hidden>
					<div class="bs-duel-heading">
						<p class="bs-kicker" data-bs-round-name></p>
						<h2 tabindex="-1" data-bs-title><?php echo $h($t['duel_heading']); ?></h2>
						<p class="bs-duel-instructions"><?php echo $h($t['duel_help']); ?></p>
					</div>
					<div class="bs-claim" role="group" aria-label="<?php echo $h($t['claim_label']); ?>">
						<span class="bs-claim-badge"><?php echo $h($t['claim_tag']); ?></span>
						<blockquote data-bs-claim></blockquote>
					</div>
					<div class="bs-dialogue-panel">
						<div class="bs-dialogue-top"><h3><?php echo $h($t['interrogation_title']); ?></h3><span data-bs-probes-count></span></div>
						<ol class="bs-dialogue" data-bs-dialogue aria-label="<?php echo $h($t['dialogue_label']); ?>"></ol>
						<p class="bs-dialogue-empty" data-bs-dialogue-empty><?php echo $h($t['dialogue_empty']); ?></p>
						<div class="bs-question-form" data-bs-probe-panel>
							<label for="<?php echo $h($rootId . '_question'); ?>"><?php echo $h($t['probe_label']); ?></label>
							<textarea id="<?php echo $h($rootId . '_question'); ?>" data-bs-probe-input maxlength="550" rows="2" placeholder="<?php echo $h($t['probe_placeholder']); ?>"></textarea>
							<button type="button" class="bs-button bs-button-secondary" data-bs-probe-button><?php echo $h($t['probe_button']); ?><span aria-hidden="true"> ↗</span></button>
						</div>
					</div>
					<div class="bs-accusation" data-bs-accusation-panel>
						<p class="bs-kicker"><?php echo $h($t['accusation_kicker']); ?></p>
						<h3><?php echo $h($t['accusation_title']); ?></h3>
						<p><?php echo $h($t['accusation_help']); ?></p>
						<label for="<?php echo $h($rootId . '_accusation'); ?>"><?php echo $h($t['accusation_label']); ?></label>
						<textarea id="<?php echo $h($rootId . '_accusation'); ?>" data-bs-accusation-input maxlength="550" rows="3" placeholder="<?php echo $h($t['accusation_placeholder']); ?>"></textarea>
						<button type="button" class="bs-button bs-button-primary" data-bs-accuse-button><?php echo $h($t['accuse_button']); ?> <span aria-hidden="true">⚡</span></button>
					</div>
					<div class="bs-reveal" data-bs-reveal hidden>
						<h3 tabindex="-1" data-bs-reveal-title></h3>
						<p class="bs-verdict-feedback" data-bs-verdict-feedback></p>
						<div class="bs-verdict-detail"><strong><?php echo $h($t['flaw_label']); ?></strong><p data-bs-flaw></p></div>
						<div class="bs-verdict-detail"><strong><?php echo $h($t['correction_label']); ?></strong><p data-bs-correction></p></div>
						<p class="bs-verdict-source" data-bs-source-wrap hidden><strong><?php echo $h($t['source_label']); ?></strong> <span data-bs-source></span></p>
						<small><?php echo $h($t['source_note']); ?></small>
						<button class="bs-button bs-button-primary" type="button" data-bs-next><?php echo $h($t['next_round']); ?><span aria-hidden="true"> ↗</span></button>
					</div>
				</div>
				<div class="bs-finale" data-bs-finale hidden>
					<div class="bs-end-symbol" aria-hidden="true">✦</div>
					<p class="bs-kicker"><?php echo $h($t['end_kicker']); ?></p>
					<h2 tabindex="-1" data-bs-finale-title></h2>
					<p data-bs-finale-summary></p>
					<p class="bs-finale-topic"><span><?php echo $h($t['topic_label']); ?></span> <strong data-bs-finale-topic></strong></p>
					<div class="bs-review"><h3><?php echo $h($t['review_title']); ?></h3><p><?php echo $h($t['review_description']); ?></p><ol class="bs-review-list" data-bs-review-list></ol><p data-bs-review-empty hidden><?php echo $h($t['review_empty']); ?></p></div>
					<button class="bs-button bs-button-primary" type="button" data-bs-restart><?php echo $h($t['play_again']); ?><span aria-hidden="true"> ↗</span></button>
				</div>
				<div class="bs-notification" data-bs-error role="alert" hidden></div>
			</div>
			<aside class="bs-console" aria-label="<?php echo $h($t['console_label']); ?>">
				<div class="bs-console-header"><span><?php echo $h($t['console_title']); ?></span><span class="bs-led" aria-hidden="true"></span></div>
				<div class="bs-panel">
					<div class="bs-panel-heading"><span><?php echo $h($t['progress']); ?></span><strong data-bs-progress-label>0 / 5</strong></div>
					<div class="bs-progress-track" aria-hidden="true"><div data-bs-progress></div></div>
					<div class="bs-steps" aria-hidden="true"><span data-bs-step="1"></span><span data-bs-step="2"></span><span data-bs-step="3"></span><span data-bs-step="4"></span><span data-bs-step="5"></span></div>
				</div>
				<div class="bs-stats"><div><small><?php echo $h($t['hits']); ?></small><strong data-bs-hits>0</strong></div><div><small><?php echo $h($t['streak']); ?></small><strong data-bs-streak>0</strong></div></div>
				<div class="bs-panel"><div class="bs-panel-heading"><span><?php echo $h($t['opponent_integrity']); ?></span><strong data-bs-integrity-label>100%</strong></div><div class="bs-integrity-track" aria-hidden="true"><div data-bs-integrity></div></div><small><?php echo $h($t['integrity_note']); ?></small></div>
				<div class="bs-panel bs-history" data-bs-history-panel aria-busy="true">
					<div class="bs-panel-heading"><span><?php echo $h($t['history_title']); ?></span><div><button type="button" data-bs-history-latest><?php echo $h($t['history_latest']); ?></button><button type="button" data-bs-history-refresh><?php echo $h($t['history_refresh']); ?></button></div></div>
					<div class="bs-history-stats"><div><small><?php echo $h($t['history_best']); ?></small><strong data-bs-best>0</strong></div><div><small><?php echo $h($t['history_completed']); ?></small><strong data-bs-completed>0</strong></div></div>
					<ol class="bs-history-list" data-bs-history-list></ol><p class="bs-history-empty" data-bs-history-empty><?php echo $h($t['history_empty']); ?></p>
				</div>
				<div class="bs-panel bs-rules"><div class="bs-panel-heading"><span><?php echo $h($t['rules_heading']); ?></span></div><p><?php echo $h($t['rules_description']); ?></p><ol><li><?php echo $h($t['rule_one']); ?></li><li><?php echo $h($t['rule_two']); ?></li><li><?php echo $h($t['rule_three']); ?></li></ol></div>
				<p class="bs-action-note" data-bs-action-note role="status" aria-live="polite"><?php echo $h($t['ready_note']); ?></p>
				<div class="bs-console-footer"><button type="button" data-bs-new><?php echo $h($t['new_game']); ?></button><button type="button" data-bs-delete><?php echo $h($t['delete_game']); ?></button></div>
			</aside>
		</div>
		<footer class="bs-footer"><span><?php echo $h($t['footer']); ?></span><span data-bs-status><?php echo $h($t['status_ready']); ?></span></footer>
	</div>
	<script type="application/json" data-bs-config><?php echo $serialized; ?></script>
</section>
<script src="<?php echo $h($this->_['blindspot_js']); ?>" defer></script>

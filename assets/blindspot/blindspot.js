(function () {
	'use strict';

	function mount(root) {
		if (root.dataset.bsMounted === '1') { return; }
		root.dataset.bsMounted = '1';
		var config = JSON.parse(root.querySelector('[data-bs-config]').textContent);
		var t = config.text;
		var find = function (name) { return root.querySelector('[data-bs-' + name + ']'); };
		var state = null;
		var matchId = config.matchId;
		var busy = false;
		var historyToken = 0;
		var motionOff = false;
		var pendingAction = '';
		var question = find('probe-input');
		var accusation = find('accusation-input');
		var probeButton = find('probe-button');
		var accuseButton = find('accuse-button');
		var error = find('error');

		function text(pattern, values) {
			return pattern.replace(/\[\[([a-z_]+)\]\]/g, function (whole, name) {
				return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : whole;
			});
		}

		function element(tag, cssClass, value) {
			var node = document.createElement(tag);
			if (cssClass) { node.className = cssClass; }
			if (value !== undefined) { node.textContent = value; }
			return node;
		}

		function setError(message) {
			error.hidden = !message;
			error.textContent = message || '';
		}

		async function request(action, extra) {
			var response = await fetch(config.api, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(Object.assign({ action: action, match_id: matchId }, extra || {}))
			});
			var payload = await response.json();
			if (!response.ok || !payload.ok) {
				throw new Error(payload.error && payload.error.message ? payload.error.message : t.unexpected_error);
			}
			return payload;
		}

		function isDuel() { return !!state && state.phase === 'duel'; }

		function updateControls() {
			find('start').disabled = busy;
			find('restart').disabled = busy;
			find('new').disabled = busy;
			find('delete').disabled = busy;
			find('next').disabled = busy;
			question.disabled = busy || !isDuel() || state.challenge.probes_remaining === 0;
			accusation.disabled = busy || !isDuel();
			probeButton.disabled = question.disabled || question.value.trim().length < 3;
			accuseButton.disabled = accusation.disabled || accusation.value.trim().length < 12;
			find('probe-panel').hidden = !isDuel() || state.challenge.probes_remaining === 0;
			find('accusation-panel').hidden = !isDuel();
			find('action-note').textContent = busy ? (pendingAction === 'start' ? t.loading_game : pendingAction === 'next' ? t.loading_next : pendingAction === 'accuse' ? t.loading_verdict : t.loading_probe) : !state ? t.ready_note
				: state.phase === 'duel' ? (state.challenge.probes_remaining === 0 ? t.probes_over_note : t.duel_note)
				: state.phase === 'reveal' ? t.reveal_note : t.ready_note;
			var agentBusy = busy && (pendingAction === 'start' || pendingAction === 'next' ||
				pendingAction === 'probe' || pendingAction === 'accuse');
			find('agent-loading').hidden = !agentBusy;
			if (agentBusy) {
				find('agent-loading-message').textContent = pendingAction === 'start' ? t.loading_game
					: pendingAction === 'next' ? t.loading_next : pendingAction === 'accuse'
						? t.loading_verdict : t.loading_probe;
			}
			root.classList.toggle('bs-busy', busy);
			// The live loading status stays outside the busy content regions.
			find('welcome').setAttribute('aria-busy', String(busy));
			find('duel').setAttribute('aria-busy', String(busy));
			find('finale').setAttribute('aria-busy', String(busy));
		}

		function renderDialogue(dialogue) {
			var list = find('dialogue');
			list.replaceChildren();
			find('dialogue-empty').hidden = dialogue.length > 0;
			dialogue.forEach(function (turn) {
				var item = element('li', 'bs-exchange');
				var player = element('div', 'bs-chat-message bs-chat-player');
				player.appendChild(element('strong', '', t.player_label));
				player.appendChild(element('p', '', turn.question));
				var opponent = element('div', 'bs-chat-message bs-chat-opponent');
				opponent.appendChild(element('strong', '', t.opponent_label));
				opponent.appendChild(element('p', '', turn.reply));
				item.appendChild(player);
				item.appendChild(opponent);
				list.appendChild(item);
			});
		}

		function renderReview() {
			var list = find('review-list');
			list.replaceChildren();
			var entries = state.review || [];
			find('review-empty').hidden = entries.length > 0;
			entries.forEach(function (entry) {
				var li = element('li', 'bs-review-item ' + (entry.correct ? 'bs-review-hit' : 'bs-review-miss'));
				var details = element('details');
				var summary = element('summary', '', text(entry.correct ? t.review_hit : t.review_miss, { points: entry.awarded }));
				summary.prepend(element('span', 'bs-review-round', String(entry.round).padStart(2, '0')));
				details.appendChild(summary);
				[
					[t.review_claim, entry.claim],
					[t.review_accusation, entry.accusation],
					[t.review_correction, entry.correction]
				].forEach(function (field) {
					var paragraph = element('p', 'bs-review-detail');
					paragraph.appendChild(element('strong', '', field[0] + ' '));
					paragraph.appendChild(document.createTextNode(field[1] || ''));
					details.appendChild(paragraph);
				});
				if (entry.source) {
					details.appendChild(element('p', 'bs-review-detail', t.source_label + ' ' + entry.source));
				}
				details.appendChild(element('small', 'bs-review-probes', text(t.review_probes, {
					count: entry.probes_used || 0
				})));
				li.appendChild(details);
				list.appendChild(li);
			});
		}

		function render() {
			var empty = !state;
			var finished = !!state && state.phase === 'finished';
			var revealed = !!state && state.phase === 'reveal';
			find('welcome').hidden = !empty;
			find('duel').hidden = empty || finished;
			find('finale').hidden = !finished;
			find('score').textContent = state ? String(state.score) : '0';
			find('hits').textContent = state ? String(state.hits) : '0';
			find('streak').textContent = state ? String(state.streak) : '0';
			find('progress-label').textContent = state ? state.round + ' / ' + state.total : '0 / 5';
			find('progress').style.width = (state ? 100 * (finished ? state.total : state.round - (revealed ? 0 : 1)) / state.total : 0) + '%';
			var integrity = state ? Math.max(0, 100 - 20 * state.hits) : 100;
			find('integrity').style.width = integrity + '%';
			find('integrity-label').textContent = integrity + '%';
			root.querySelectorAll('[data-bs-step]').forEach(function (step) {
				var index = Number(step.dataset.bsStep);
				step.classList.toggle('bs-step-past', !!state && (finished || index < state.round));
				step.classList.toggle('bs-step-current', !!state && !finished && index === state.round);
			});
			root.classList.toggle('bs-hit', revealed && !!state.challenge.verdict.correct);
			root.classList.toggle('bs-miss', revealed && !state.challenge.verdict.correct);
			root.classList.toggle('bs-finished', finished);
			if (empty) {
				find('broadcast').textContent = t.opponent_welcome;
				find('status').textContent = t.status_ready;
			} else if (finished) {
				find('broadcast').textContent = state.hits === state.total ? t.perfect_title : t.opponent_welcome;
				find('finale-title').textContent = state.hits === state.total ? t.perfect_title
					: state.hits >= 3 ? t.win_title : t.lose_title;
				find('finale-summary').textContent = text(t.final_summary, {
					hits: state.hits, total: state.total, score: state.score
				});
				find('finale-topic').textContent = state.topic;
				renderReview();
				find('status').textContent = t.status_finished;
			} else {
				var challenge = state.challenge;
				find('round-name').textContent = text(t.round_name, { round: state.round, total: state.total });
				find('broadcast').textContent = challenge.taunt;
				find('claim').textContent = challenge.claim;
				find('probes-count').textContent = text(t.probes_count, {
					left: challenge.probes_remaining, total: challenge.probes_total
				});
				renderDialogue(challenge.dialogue);
				find('reveal').hidden = !revealed;
				if (revealed) {
					find('reveal-title').textContent = challenge.verdict.correct
						? text(t.correct_result, { points: challenge.verdict.awarded }) : t.incorrect_result;
					find('verdict-feedback').textContent = challenge.verdict.feedback;
					find('flaw').textContent = challenge.flaw;
					find('correction').textContent = challenge.correction;
					find('source-wrap').hidden = !challenge.source;
					find('source').textContent = challenge.source || '';
					find('next').textContent = state.round === state.total ? t.finish_game : t.next_round;
				}
				find('status').textContent = revealed ? t.status_revealed : t.status_challenging;
			}
			updateControls();
		}

		function renderHistory(history) {
			find('best').textContent = String(history.best);
			find('completed').textContent = String(history.completed);
			var list = find('history-list');
			list.replaceChildren();
			find('history-empty').hidden = history.items.length > 0;
			history.items.forEach(function (item) {
				var li = element('li', 'bs-history-item');
				var button = element('button', 'bs-history-open');
				button.type = 'button';
				button.appendChild(element('strong', '', item.topic));
				button.appendChild(element('span', '', String(item.created_at).slice(0, 16)));
				button.appendChild(element('span', '', text(t.history_score, {
					points: item.score, hits: item.hits
				})));
				button.setAttribute('aria-label', text(t.history_open, {
					topic: item.topic, points: item.score, hits: item.hits
				}));
				button.addEventListener('click', function () {
					if (state && state.phase !== 'finished' && !window.confirm(t.history_leave_confirm)) { return; }
					perform('snapshot', { match_id: item.id }, 'finale');
				});
				li.appendChild(button);
				list.appendChild(li);
			});
		}

		async function loadHistory() {
			var token = ++historyToken;
			find('history-panel').setAttribute('aria-busy', 'true');
			find('history-refresh').disabled = true;
			try {
				var payload = await request('history');
				if (token === historyToken) { renderHistory(payload.history); }
			} catch (problem) {
				if (token === historyToken) { setError(problem instanceof Error ? problem.message : t.history_unavailable); }
			} finally {
				if (token === historyToken) {
					find('history-panel').setAttribute('aria-busy', 'false');
					find('history-refresh').disabled = false;
				}
			}
		}

		async function perform(action, extra, focusTarget) {
			if (busy) { return; }
			busy = true;
			pendingAction = action;
			setError('');
			var loadingText = action === 'start' ? t.loading_game : action === 'next' ? t.loading_next
				: action === 'accuse' ? t.loading_verdict : t.loading_probe;
			find('action-note').textContent = loadingText;
			if (action === 'start') { find('broadcast').textContent = loadingText; }
			updateControls();
			try {
				var result = await request(action, extra);
				state = result.state || null;
				matchId = state ? state.id : '';
				if (action === 'probe' || action === 'next' || action === 'start') { question.value = ''; }
				if (action === 'accuse' || action === 'next' || action === 'start') { accusation.value = ''; }
				render();
				if (action === 'delete_all') {
					++historyToken;
					renderHistory({ best: 0, completed: 0, items: [] });
					loadHistory();
				} else if (state && state.phase === 'finished' && action === 'next') {
					loadHistory();
				}
			} catch (problem) {
				setError(problem instanceof Error ? problem.message : t.unexpected_error);
			} finally {
				busy = false;
				pendingAction = '';
				render();
				if (focusTarget && state) {
					var destination = state.phase === 'finished' ? find('finale-title')
						: state.phase === 'reveal' ? find('reveal-title') : action === 'probe'
							? (state.challenge.probes_remaining > 0 ? question : accusation) : find('title');
					if (destination && !destination.closest('[hidden]') && !destination.disabled) {
						destination.focus({ preventScroll: true });
					}
				}
			}
		}

		function updateFullscreen() {
			var fullscreen = find('fullscreen');
			var active = document.fullscreenElement === root;
			fullscreen.setAttribute('aria-pressed', String(active));
			fullscreen.setAttribute('aria-label', active ? t.exit_fullscreen : t.enter_fullscreen);
			fullscreen.title = active ? t.exit_fullscreen : t.enter_fullscreen;
			find('fullscreen-label').textContent = active ? t.exit_fullscreen : t.enter_fullscreen;
		}

		find('start').addEventListener('click', function () { perform('start', {}, 'title'); });
		find('restart').addEventListener('click', function () { perform('start', {}, 'title'); });
		find('new').addEventListener('click', function () {
			if (state && state.phase !== 'finished' && !window.confirm(t.new_game_confirm)) { return; }
			perform('start', {}, 'title');
		});
		probeButton.addEventListener('click', function () {
			if (isDuel() && !busy && question.value.trim()) {
				perform('probe', { revision: state.revision, question: question.value.trim() }, 'probe');
			}
		});
		accuseButton.addEventListener('click', function () {
			if (isDuel() && !busy && accusation.value.trim().length >= 12) {
				perform('accuse', { revision: state.revision, accusation: accusation.value.trim() }, 'reveal');
			}
		});
		question.addEventListener('input', updateControls);
		accusation.addEventListener('input', updateControls);
		find('next').addEventListener('click', function () {
			if (state && state.phase === 'reveal') { perform('next', { revision: state.revision }, 'title'); }
		});
		find('delete').addEventListener('click', function () {
			if (window.confirm(t.delete_confirm)) { perform('delete_all'); }
		});
		find('history-refresh').addEventListener('click', loadHistory);
		find('history-latest').addEventListener('click', function () { perform('resume', {}, 'title'); });
		find('motion').addEventListener('click', function () {
			motionOff = !motionOff;
			root.classList.toggle('bs-motion-off', motionOff);
			find('motion').setAttribute('aria-pressed', String(motionOff));
			find('motion-label').textContent = motionOff ? t.enable_motion : t.reduce_motion;
		});
		find('fullscreen').hidden = !(typeof root.requestFullscreen === 'function' && typeof document.exitFullscreen === 'function');
		find('fullscreen').addEventListener('click', async function () {
			try {
				if (document.fullscreenElement === root) { await document.exitFullscreen(); }
				else { await root.requestFullscreen(); }
			} catch (problem) { setError(t.fullscreen_unavailable); }
		});
		document.addEventListener('fullscreenchange', updateFullscreen);
		updateFullscreen();
		loadHistory();
		if (matchId) { perform('snapshot', { match_id: matchId }, 'title'); }
		else { perform('resume', {}, 'title'); }
	}

	function initialize() { document.querySelectorAll('[data-bs-root]').forEach(mount); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initialize); }
	else { initialize(); }
}());

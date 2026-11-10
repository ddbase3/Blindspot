'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const pluginRoot = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(pluginRoot, 'assets/blindspot/blindspot.js'), 'utf8');
const labels = {};
for (const [, key, value] of fs.readFileSync(path.join(pluginRoot, 'lang/Display/en.ini'), 'utf8')
	.matchAll(/^([a-z_]+)\s*=\s*("(?:[^"\\]|\\.)*")\s*$/gm)) {
	labels[key] = JSON.parse(value);
}

class FakeElement {
	constructor(name = '') {
		this.name = name;
		this.dataset = {};
		this.children = [];
		this.listeners = {};
		this.attributes = {};
		this.style = {};
		this.hidden = false;
		this.disabled = false;
		this.textContent = '';
		this.value = '';
		this.className = '';
		this.classes = new Set();
		this.classList = { toggle: (name, enabled) => {
			if (enabled === undefined) { enabled = !this.classes.has(name); }
			if (enabled) { this.classes.add(name); } else { this.classes.delete(name); }
		} };
	}
	addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); }
	dispatch(name) { for (const handler of this.listeners[name] || []) { handler(); } }
	setAttribute(name, value) { this.attributes[name] = String(value); }
	getAttribute(name) { return this.attributes[name]; }
	appendChild(element) { this.children.push(element); return element; }
	prepend(element) { this.children.unshift(element); }
	replaceChildren(...elements) { this.children = elements; }
	closest() { return null; }
	focus() { this.focused = true; }
}

function fixture(configuration = {}) {
	const elements = {};
	const find = name => elements[name] ||= new FakeElement(name);
	const root = new FakeElement('root');
	root.querySelector = selector => find(selector.match(/^\[data-bs-([^\]]+)\]$/)[1]);
	root.querySelectorAll = selector => selector === '[data-bs-step]'
		? [1, 2, 3, 4, 5].map(value => {
			const el = find('step-' + value);
			el.dataset.bsStep = String(value);
			return el;
		}) : [];
	find('config').textContent = JSON.stringify({ api: '/game', matchId: '', text: labels });

	const document = {
		readyState: 'complete',
		fullscreenElement: null,
		listeners: {},
		querySelectorAll: selector => selector === '[data-bs-root]' ? [root] : [],
		createElement: tag => new FakeElement(tag),
		createTextNode: value => ({ textContent: value }),
		addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); },
		dispatch(name) { for (const handler of this.listeners[name] || []) { handler(); } },
		async exitFullscreen() { this.fullscreenElement = null; this.dispatch('fullscreenchange'); }
	};
	root.requestFullscreen = async () => { document.fullscreenElement = root; document.dispatch('fullscreenchange'); };
	const pending = [];
	const fetch = async (_, options) => {
		const args = JSON.parse(options.body);
		if (args.action === 'history') {
			return { ok: true, json: async () => ({ ok: true, history: configuration.history || { best: 0, completed: 0, items: [] } }) };
		}
		if (args.action === 'resume') {
			return { ok: true, json: async () => ({ ok: true, state: null }) };
		}
		return new Promise((resolve, reject) => pending.push({
			action: args.action,
			args,
			succeed(value) { resolve({ ok: true, json: async () => ({ ok: true, state: value }) }); },
			fail(error) { reject(error); }
		}));
	};
	vm.runInNewContext(source, { document, fetch, window: { confirm: () => true }, console, Error });
	return { root, find, pending, document };
}

const flush = () => new Promise(resolve => setImmediate(resolve));
const id = '0123456789abcdef0123456789abcdef';
function duel(round = 1) {
	return {
		id, revision: round, topic: 'Totalreflexion', round, total: 5, phase: 'duel',
		score: 0, hits: 0, streak: 0,
		challenge: { claim: 'A plausible but false statement', taunt: 'Prove me wrong',
			deception_type: 'fact', dialogue: [], probes_remaining: 4, probes_total: 4 }
	};
}

const expectedLoading = {
	start: 'loading_game', probe: 'loading_probe', accuse: 'loading_verdict', next: 'loading_next'
};

test('every agent action shows a clear, action-specific status and clears it on success or failure', async () => {
	const { root, find, pending } = fixture();
	await flush();
	assert.equal(find('agent-loading').hidden, true, 'normal state is not obscured');

	find('start').dispatch('click');
	assert.equal(pending.at(-1).action, 'start');
	assert.equal(find('agent-loading').hidden, false);
	assert.equal(find('agent-loading-message').textContent, labels[expectedLoading.start]);
	assert.equal(find('duel').getAttribute('aria-busy'), 'true');
	assert.equal(find('start').disabled, true);
	pending.at(-1).succeed(duel());
	await flush();
	assert.equal(find('agent-loading').hidden, true);
	assert.equal(find('duel').getAttribute('aria-busy'), 'false');

	find('probe-input').value = 'What is your evidence for this?';
	find('probe-input').dispatch('input');
	find('probe-button').dispatch('click');
	assert.equal(pending.at(-1).action, 'probe');
	assert.equal(find('agent-loading').hidden, false);
	assert.equal(find('agent-loading-message').textContent, labels[expectedLoading.probe]);
	const defended = duel();
	defended.challenge.probes_remaining = 3;
	defended.challenge.dialogue = [{ question: 'What is your evidence for this?', reply: 'A defense.' }];
	pending.at(-1).succeed(defended);
	await flush();
	assert.equal(find('agent-loading').hidden, true);

	find('accusation-input').value = 'Your thesis ignores the second medium.';
	find('accusation-input').dispatch('input');
	find('accuse-button').dispatch('click');
	assert.equal(pending.at(-1).action, 'accuse');
	assert.equal(find('agent-loading').hidden, false);
	assert.equal(find('agent-loading-message').textContent, labels[expectedLoading.accuse]);
	const revealed = duel();
	revealed.phase = 'reveal';
	revealed.challenge.verdict = { correct: true, awarded: 100, feedback: 'Correct' };
	revealed.challenge.flaw = 'The second medium is missing';
	revealed.challenge.correction = 'Both media matter';
	revealed.challenge.source = 'Optics § 4.5';
	pending.at(-1).succeed(revealed);
	await flush();
	assert.equal(find('agent-loading').hidden, true);

	find('next').dispatch('click');
	assert.equal(pending.at(-1).action, 'next');
	assert.equal(find('agent-loading').hidden, false);
	assert.equal(find('agent-loading-message').textContent, labels[expectedLoading.next]);
	pending.at(-1).succeed(duel(2));
	await flush();
	assert.equal(find('agent-loading').hidden, true);

	find('probe-input').value = 'Can you explain your assumption?';
	find('probe-input').dispatch('input');
	find('probe-button').dispatch('click');
	assert.equal(find('agent-loading').hidden, false);
	pending.at(-1).fail(new Error('Network error'));
	await flush();
	assert.equal(find('agent-loading').hidden, true, 'error must never trap the overlay');
	assert.equal(find('error').textContent, 'Network error');
	assert.equal(find('error').hidden, false);
	assert.equal(find('duel').getAttribute('aria-busy'), 'false');
});

test('fullscreen control stays available, toggles correctly and keeps native Escape support', async () => {
	const { root, find, document } = fixture();
	await flush();
	assert.equal(find('fullscreen').hidden, false);
	assert.equal(find('fullscreen').getAttribute('aria-label'), labels.enter_fullscreen);
	find('fullscreen').dispatch('click');
	await flush();
	assert.equal(document.fullscreenElement, root);
	assert.equal(find('fullscreen').getAttribute('aria-pressed'), 'true');
	assert.equal(find('fullscreen').getAttribute('aria-label'), labels.exit_fullscreen);
	await document.exitFullscreen();
	assert.equal(find('fullscreen').getAttribute('aria-pressed'), 'false');
	assert.equal(find('fullscreen-label').textContent, labels.enter_fullscreen);
});


test('rendered result review and history include actual awarded points and used question counts', async () => {
	const history = { best: 475, completed: 1, items: [
		{ id, topic: 'Mechanik', score: 475, hits: 4, created_at: '2026-11-10 22:43:00' }
	] };
	const { find, pending } = fixture({ history });
	await flush();
	const historyButton = find('history-list').children[0].children[0];
	assert.equal(historyButton.children[2].textContent, '475 PTS · 4/5 hits');
	assert.match(historyButton.getAttribute('aria-label'), /475 points and 4 hits/);

	find('start').dispatch('click');
	const finished = duel(5);
	finished.phase = 'finished';
	finished.score = 475;
	finished.hits = 4;
	finished.streak = 3;
	finished.topic = 'Mechanik';
	finished.review = [
		{ round: 1, correct: true, awarded: 100, claim: 'False claim',
			accusation: 'Correct objection', correction: 'Correct claim',
			source: 'Section 1', probes_used: 2 },
		{ round: 2, correct: false, awarded: 0, claim: 'Second claim',
			accusation: 'Incorrect objection', correction: 'Second correction',
			source: 'Section 2', probes_used: 0 }
	];
	pending.at(-1).succeed(finished);
	await flush();
	assert.equal(find('score').textContent, '475');
	assert.equal(find('finale-summary').textContent, '4 of 5 claims defeated. 475 points.');
	const review = find('review-list').children;
	assert.equal(review[0].children[0].children[0].textContent, 'Exposed · +100 points');
	assert.equal(review[0].children[0].children.at(-1).textContent, '2 questions used');
	assert.equal(review[1].children[0].children[0].textContent, 'Not exposed · 0 points');
	assert.equal(review[1].children[0].children.at(-1).textContent, '0 questions used');
});

test('round and result prompts retain their numbers after applying translations', async () => {
	const { find, pending } = fixture();
	await flush();
	find('start').dispatch('click');
	const revealed = duel();
	revealed.score = 100;
	revealed.hits = 1;
	revealed.phase = 'reveal';
	revealed.challenge.probes_remaining = 2;
	revealed.challenge.verdict = { correct: true, awarded: 100, feedback: 'Correct!' };
	revealed.challenge.flaw = 'The flaw';
	revealed.challenge.correction = 'The correction';
	revealed.challenge.source = 'Mechanik, 7.1';
	pending.at(-1).succeed(revealed);
	await flush();
	assert.equal(find('round-name').textContent, 'CONFRONTATION 1 / 5');
	assert.equal(find('probes-count').textContent, '2 / 4 QUESTIONS REMAIN');
	assert.equal(find('reveal-title').textContent, 'DIRECT HIT! +100 POINTS');
});

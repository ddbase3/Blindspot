<?php declare(strict_types=1);

namespace Blindspot\Test\Display;

use PHPUnit\Framework\TestCase;

/** Catalog and template contract shared by PHPUnit 11.5 and 12.5. */
final class BlindspotLocalizationAndUiTest extends TestCase {

	private const LOCALES = ['ar', 'bg', 'de', 'en', 'es', 'fr', 'hi', 'it', 'pl', 'pt', 'ru', 'zh'];

	public function testEveryMissionBayLanguageHasTheSameNonemptyMessagesAndParameters(): void {
		$folder = dirname(__DIR__, 2) . '/lang/Display/';
		$files = glob($folder . '*.ini');
		self::assertIsArray($files);
		$actual = array_map(static fn(string $file): string => basename($file, '.ini'), $files);
		sort($actual);
		self::assertSame(self::LOCALES, $actual);
		$reference = $this->load($folder . 'en.ini');
		self::assertCount(122, $reference);
		$referenceKeys = array_keys($reference);
		sort($referenceKeys);
		foreach (self::LOCALES as $locale) {
			$catalog = $this->load($folder . $locale . '.ini');
			$keys = array_keys($catalog);
			sort($keys);
			self::assertSame($referenceKeys, $keys, $locale . ' key mismatch');
			foreach ($catalog as $key => $value) {
				self::assertNotSame('', trim($value), $locale . '.' . $key . ' empty');
				self::assertSame($this->parameters($reference[$key]), $this->parameters($value),
					$locale . '.' . $key . ' parameter mismatch');
			}
		}
	}

	public function testRuntimeNumberTokensSurviveNormalIniParsing(): void {
		$folder = dirname(__DIR__, 2) . '/lang/Display/';
		foreach (self::LOCALES as $locale) {
			$parsed = parse_ini_file($folder . $locale . '.ini', true, INI_SCANNER_NORMAL);
			self::assertIsArray($parsed);
			$labels = $parsed['blindspotdisplay'];
			foreach (['review_hit', 'review_probes', 'history_score', 'history_open',
				'final_summary', 'correct_result', 'round_name', 'probes_count'] as $key) {
				self::assertStringContainsString('[[', $labels[$key], $locale . '.' . $key);
				self::assertStringNotContainsString('{', $labels[$key], $locale . '.' . $key);
			}
		}
	}

	public function testEveryTemplateAndJavaScriptTranslationIsDefined(): void {
		$root = dirname(__DIR__, 2);
		$template = file_get_contents($root . '/tpl/Display/BlindspotDisplay.php');
		$script = file_get_contents($root . '/assets/blindspot/blindspot.js');
		self::assertIsString($template);
		self::assertIsString($script);
		preg_match_all('/\$t\[\'([a-z_]+)\'\]/', $template, $templateMatches);
		preg_match_all('/\bt\.([a-z_]+)/', $script, $scriptMatches);
		$catalog = $this->load($root . '/lang/Display/en.ini');
		foreach (array_unique(array_merge($templateMatches[1], $scriptMatches[1])) as $key) {
			self::assertArrayHasKey($key, $catalog, 'Missing UI key: ' . $key);
		}
	}

	public function testLoadingIndicatorAndTopRightFullscreenUseOneExistingGameUi(): void {
		$root = dirname(__DIR__, 2);
		$template = file_get_contents($root . '/tpl/Display/BlindspotDisplay.php');
		$script = file_get_contents($root . '/assets/blindspot/blindspot.js');
		$styles = file_get_contents($root . '/assets/blindspot/blindspot.css');
		self::assertIsString($template);
		self::assertIsString($script);
		self::assertIsString($styles);
		$header = strstr($template, '</header>', true);
		self::assertIsString($header);
		self::assertStringContainsString('data-bs-fullscreen', $header);
		self::assertStringContainsString('aria-pressed="false"', $header);
		self::assertLessThan(strpos($header, 'data-bs-fullscreen'), strpos($header, 'data-bs-score'),
			'The fullscreen control must be at the far right of the header.');
		self::assertStringContainsString('data-bs-agent-loading hidden role="status" aria-live="polite" aria-atomic="true"', $template);
		self::assertStringContainsString('data-bs-agent-loading-message', $template);
		foreach (['start', 'probe', 'accuse', 'next'] as $action) {
			self::assertStringContainsString("pendingAction === '" . $action . "'", $script);
		}
		self::assertStringContainsString("find('agent-loading').hidden = !agentBusy;", $script);
		self::assertStringContainsString("document.addEventListener('fullscreenchange', updateFullscreen)", $script);
		self::assertStringContainsString('prefers-reduced-motion: reduce', $styles);
		self::assertStringContainsString('.bs-root.bs-motion-off', $styles);
		self::assertStringContainsString('bs-agent-loading', $styles);
	}

	/** @return array<string,string> */
	private function load(string $file): array {
		$data = parse_ini_file($file, true, INI_SCANNER_RAW);
		self::assertIsArray($data, basename($file) . ' must be valid INI');
		self::assertArrayHasKey('blindspotdisplay', $data);
		$catalog = $data['blindspotdisplay'];
		self::assertIsArray($catalog);
		foreach ($catalog as $value) { self::assertIsString($value); }
		return $catalog;
	}

	/** @return array<int,string> */
	private function parameters(string $value): array {
		preg_match_all('/\[\[[a-z_]+\]\]/', $value, $matches);
		$parameters = $matches[0];
		sort($parameters);
		return $parameters;
	}
}

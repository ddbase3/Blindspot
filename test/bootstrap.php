<?php declare(strict_types=1);

/** Test-only source discovery for the actual BASE3 module location in standalone or embedded hosts. */
$moduleRoot = dirname(__DIR__);
$modulesRoot = dirname($moduleRoot);
$standaloneRoot = dirname($modulesRoot);
$embeddedRoot = dirname($standaloneRoot);

foreach ([$standaloneRoot, $embeddedRoot] as $root) {
	$vendor = $root . '/vendor/autoload.php';
	if (is_file($vendor)) { require_once $vendor; break; }
}

$framework = is_dir($modulesRoot . '/Base3Framework/src')
	? $modulesRoot . '/Base3Framework/src/' : $standaloneRoot . '/Base3Framework/src/';
$assistant = is_dir($modulesRoot . '/AssistantFoundation/src')
	? $modulesRoot . '/AssistantFoundation/src/' : $standaloneRoot . '/plugin/AssistantFoundation/src/';

$prefixes = [
	'Blindspot\\Test\\' => __DIR__ . '/',
	'Blindspot\\' => $moduleRoot . '/src/',
	'AssistantFoundation\\' => $assistant,
	'Base3\\' => $framework
];
spl_autoload_register(static function(string $class) use ($prefixes): void {
	foreach ($prefixes as $prefix => $path) {
		if (!str_starts_with($class, $prefix)) { continue; }
		$file = $path . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
		if (is_file($file)) { require_once $file; }
		return;
	}
});

if (!defined('DIR_PLUGIN')) { define('DIR_PLUGIN', $modulesRoot . '/'); }

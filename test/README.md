# BLINDSPOT test suite

The same PHPUnit tests target the **shared public APIs of PHPUnit 11.5 and 12.5** without conditional code, version branches, renamed framework methods or TestCase-final method overrides. PHPUnit 12.5 requires PHP 8.3 or newer. Test fixture classes each have one type per file and use TAB indentation.

From the ILIAS root:

```sh
php components/Base3/Blindspot/test/run.php
php vendor/bin/phpunit --configuration components/Base3/Blindspot/test/phpunit.xml
node --check components/Base3/Blindspot/assets/blindspot/blindspot.js
node --test components/Base3/Blindspot/test/Ui/blindspot-ui.test.js
```

If different PHPUnit versions are available separately, run the same suite with both:

```sh
php /path/to/phpunit-11.5.phar --configuration components/Base3/Blindspot/test/phpunit.xml
php /path/to/phpunit-12.5.phar --configuration components/Base3/Blindspot/test/phpunit.xml
```

The dependency-contract smoke test requires installed BASE3/AssistantFoundation sources, but no MySQL or live model. Tests cover the single configured agent's four distinct execution-time system instructions and natural-language opening, defense and judgement calls using the existing `AgentExecutionRequest` contract and the same canonical `agent_id` / `agent_config_group = agent` owner identity as MissionBay Agent Administration; valid and invalid structured model responses; an optional non-verifying source label; no document-specific request fields, file checks or document-unavailable error path; no extra memory/tools; no match-scoped conversation ID or prior-round prompts; one-time agent-produced theme inventory and server-side selection of five different topics with cross-document diversity, without any plugin-side file operations; unchanged stored configuration; secrecy of the hidden flaw (including omission from defense requests); real interrogation exchanges; the four-probe limit; free-text validation; accepted and rejected accusations; server-side streak scoring; five-round progression; native-actor authorization; revocation following a slow model call; optimistic revision commits; private history and deletion; and the original migration.

A successful contract test does **not** verify that a particular model actually uses the PDFs or can follow JSON instructions, that the model judges arguments reliably, that the provider respects retention limits, or that a browser/embedded installation works. Production acceptance requires end-to-end UI, keyboard and screen-reader tests, model evaluation on the operator's documents, realistic multiple users, and failure/revocation/retry cases.

The `BlindspotLocalizationAndUiTest` validates the exact 12-language catalog set, required translated strings, identical parameter placeholders, references from the PHP template and JavaScript, and the accessible loading/fullscreen markup. The Node integration tests exercise all four agent-wait states, normal completion, network failure cleanup, and native fullscreen/Escape state changes without adding a production dependency. Tests use no `output()` helper and do not override the final PHPUnit `TestCase::output()` method.

The configured agent is unchanged in this release. A new match requires one additional model call for topic inventory, and actual PDF coverage must be verified on the deployed agent; test doubles only prove the game selection and request contract. The UI is available in all twelve host languages, but generated game texts follow the execution-time BLINDSPOT system instruction (currently German), not the saved Agent Administration prompt; those texts are not translated by the game.

The numeric-label regression checks verify translated points, interrogation counts, final scores and history hit counts in actual rendered UI elements, including zero used questions, to prevent blank numeric placeholders. All 12 catalogs use the same `[[name]]` markers.

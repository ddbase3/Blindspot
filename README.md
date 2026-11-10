# BLINDSPOT for BASE3

BLINDSPOT is a five-confrontation browser game where the player challenges an AI opponent through **free conversation**. The AI proposes a plausible but factually or logically false thesis. The player may interrogate the opponent with four original questions per round and then submit a written accusation. The configured agent evaluates the substantive argument. The existing game service alone owns the score and match transitions.

A tutor or administrator chooses any number of knowledge PDFs in the **existing MissionBay agent administration**. At match start the same configured agent produces a topic inventory from its already attached documents. BLINDSPOT chooses five distinct theme labels, preferably from five different documents, and gives the current label to the agent in each round. No PDF parser, alternate knowledge manager, new agent tool, memory store or game router is required.

## Existing architecture retained

- `BlindspotAgentDefinition`: the existing fixed `IAgentDefinition` for `blindspot.opponent`, with its existing default prompt for standalone Agent Administration use and unchanged agent identity. Stored editable agent settings remain owned by `IAgentConfigurationService`.
- `BlindspotAgentService`: uses the established `AgentArena` invocation pattern: load the effective agent settings, create `AgentExecutionRequest($settings, ['system' => ..., 'prompt' => $naturalLanguageRequest, 'mode' => 'manual_agent'], $context)` and invoke the existing `IAgentExecutionService`. The game supplies a different execution-time `system` instruction for inventory, challenge, defense and judgement, and interprets the model's JSON game output without implementing any AI or document access.
- `BlindspotRules`: the authoritative owner of selecting five distinct themes from agent-produced labels, exactly five rounds, four questions per round, the hidden flaw, interrogation transcript, scores and public projections.
- `BlindspotGameService`: the existing owner of authorized actions, immediate host-authorization rechecks after model execution, and optimistic revision commits.
- `BlindspotMatchRepository`: the existing `blindspot_matches` table, private matches, history, best scores, owner-scoped reads and deletion. No schema migration is required.
- `BlindspotApiOutput` and `BlindspotDisplay`: the original BASE3 JSON output, display, asset resolver and translated UI.

### Match progression

1. Start: the existing agent first inventories concrete themes from its attached documents, returning document/theme/section labels as JSON. BLINDSPOT validates the labels, samples five different themes with `random_int`, and prefers different document labels. The game keeps this five-theme plan private in the existing match JSON. A second normal agent call creates one demonstrably false thesis strictly for the selected first-round theme. The hidden flaw and correction stay on the server.
2. Interrogate: up to **four** arbitrary player questions. A separate execution-time defense system prompt governs the opponent. Each `defend` call receives the visible claim and the earlier questions and responses from the current confrontation, but not the private flaw or correction. These are saved to the existing match payload and shown in the browser. The opponent must challenge the player without revealing the hidden flaw or providing tutoring hints, leading questions, decisive concepts, relevant variables or other partial corrections. Requests for help are met with an invitation to present an independent counterargument.
3. Accuse: the player independently explains why the thesis is false. A separate neutral execution-time judgement system prompt evaluates the actual argument rather than playing the opponent. The `judge` task returns a strict `correct` boolean and argument-specific feedback. The game server awards **100 points**, with a streak bonus capped at **75**, only for an accepted argument. An incorrect accusation awards zero.
4. Reveal: the player sees feedback, hidden flaw, correction and any source label actually supplied. The agent must provide a genuine location in the selected document and is forbidden to invent citations.
5. Next: one new challenge is generated for the next privately scheduled document/theme label. No claims or dialogues from earlier rounds, and no match-scoped `conversation_id`, are sent to the agent. The five chosen theme labels do not repeat within a match, and distinct document labels are prioritized. The model still owns interpreting its attachments and may fail to inventory a document or respect an assigned theme; the game does not verify the PDF bytes. After round five, the same stored answers form the finished learning review. Three successful accusations win the duel.

There are at least eleven model executions for a full five-round game (one topic inventory, five challenges, five judgements) and at most thirty-one (four interrogations per round). Operators should assess model costs, rate limits and latency before widespread deployment. Failed or invalid model output does not overwrite a valid match. Optimistic revisions reject two simultaneous commits, but do not prevent both competing HTTP requests from consuming model tokens.

### Agent and knowledge boundaries

BLINDSPOT does not own document operations. The **existing configured agent** receives ordinary German game instructions through `IAgentExecutionService`, just as AgentArena sends its AI turn instructions. Only the execution-time challenge system prompt instructs the agent how to select and interpret its attached documents. The defense and judgement systems use the private solution and dialogue supplied as game data. The game does not request document loading, select files, validate citations or implement a document-unavailable branch. The agent may return an optional plain-text `source` label for the learning review; it is not independently checked by BLINDSPOT. A missing citation label does not prevent a game from starting.

The game validates the structural shape of the model's answer, while its authoritative game owner alone awards points and advances rounds. This does not independently verify factual correctness or the model's semantic grading. Tutors should check actual challenges from their configured agent as part of acceptance.

## Installation

From the ILIAS installation root:

```sh
unzip -o Blindspot-Phase-System-Prompts-ILIAS.zip
```

This update ZIP overlays the existing `components/Base3/Blindspot/` installation, including the latest round-context fix. It uses the natural-language agent-turn invocation pattern and passes the canonical MissionBay agent-owner context (`agent_id` and `agent_config_group = agent`) so MissionBay can attach the files already assigned to the configured agent. It changes no core or MissionBay files. Standalone BASE3 uses the same plugin below `plugin/Blindspot/` through the ordinary host module layout. Existing `base3.json` dependencies on `Base3Framework` and `AssistantFoundation` remain unchanged.

**Existing agent:** Keep the working `blindspot.opponent` configuration, knowledge PDFs, model and runtime unchanged. For game calls, `BlindspotAgentService` passes its own phase-specific `inputs[system]`, which MissionBay connects to the Assistant node. The stored `system_prompt` remains an administrator-editable default for direct Agent Administration interactions; it is not the game system message. There is no requirement to edit it, create another agent, register tools, enable chat history or re-upload documents. Keep `user_prompt` empty. See [docs/agent-configuration.md](docs/agent-configuration.md).

## Existing data and user experience

Existing personal best scores and completed match history remain readable from the same match table. Older finished multiple-choice matches can be viewed as historical results without generating fictitious conversational reviews. Old **unfinished** quiz matches cannot continue in the new rules and must be replaced with a new duel. No alternate quiz mode or compatibility scoring pipeline remains.

The responsive dark UI includes a visual AI opponent, free-form interrogations, a separate accusation area, visible remaining questions, a five-round progress indicator and an accessible result review. The native fullscreen button is at the **top right**, following the AgentArena control pattern; Escape exits fullscreen. Every model-driven interaction (new game including topic inventory, question, accusation, next thesis) displays a conspicuous space-themed activity panel with a descriptive localized status until success or error. It displays no fictitious percentage or countdown, makes no additional request, and respects both reduced-motion preferences and the visible animation switch. The game has no response timer.

For numeric values in review and history labels, the UI uses stable `[[name]]` markers in the existing translation bricks, so scores, hit counts and used questions are filled by the browser without depending on brace-based host template substitution. The authoritative point and question counters remain unchanged.

The plugin provides **complete UI catalogs in all 12 MissionBay languages**: `ar`, `bg`, `de`, `en`, `es`, `fr`, `hi`, `it`, `pl`, `pt`, `ru`, `zh`. Every localized label, error, history entry, status announcement and accessible control uses the owning plugin's `lang/Display/` file and the existing `IMvcView` brick selection. The execution-time game system prompts request German model-generated text; this change does not reconfigure the stored agent or promise multilingual generated theses.

Each game action checks the trusted host identity and requires an explicit positive `IAuthorizationService` decision. Source checking, version 11.5 and 12.5 PHPUnit tests, local smoke tests and actual host/browser checks are described in [test/README.md](test/README.md). This source package alone does not establish compatibility with the deployed model or live MySQL, ILIAS, accessibility, or concurrency behavior.

Read [PRIVACY.md](PRIVACY.md) for actual storage and provider boundaries.

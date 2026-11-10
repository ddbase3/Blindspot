# BLINDSPOT agent configuration

BLINDSPOT uses the already configured fixed MissionBay agent `blindspot.opponent` through `IAgentExecutionService`. The agent retains its existing model, credentials, runtime, tools configuration and all knowledge attachments. The existing agent enumerates document themes in one model response at match start; BLINDSPOT selects five theme labels from that response, but never reads, uploads, enumerates or checks attached files itself.

## Keep the existing configured agent

The agent stays fixed under ID `blindspot.opponent`. Keep its configured `agent_runtime=missionbay`, enabled flag, model, credentials, attached documents and tools unchanged. No administrator action is required to enable task-specific system instructions.

**Important distinction:** `IAgentConfigurationService::getAgent()` returns the effective stored agent configuration. A stored `system_prompt` overrides the *definition default* when viewing or directly testing the agent in Agent Administration. BLINDSPOT, however, supplies a fresh `inputs['system']` for **each game call**. MissionBay's `AgentFlowCompiler` connects this input to the Assistant node; the saved prompt is not substituted for it. BLINDSPOT neither writes the settings nor changes the configured agent's identity.

Four phase-specific instructions live in the existing `BlindspotAgentService`, not in new agents or runtime components:

- **Topic inventory:** ask the configured agent to enumerate distinct subject themes with document labels and optional section hints from its existing attachments. This is one additional execution at match start, not a document API. BLINDSPOT validates the returned game-data strings and samples five distinct themes using `random_int`, covering five different document labels whenever the returned inventory permits. The selected plan is private match state.
- **Challenge:** generate one demonstrably false thesis strictly for the document/theme assigned to the current round, returning a private correction and optional agent-generated citation as JSON. A text-position seed is no longer used.
- **Defense:** respond to the current player's question in character using the claim and current dialogue only. The private flaw and correction are NOT supplied in this phase, and the system instruction expressly forbids revealing the solution or giving directional hints, leading questions, decisive technical terms or partial corrections, including in response to requests for help or to change roles. The opponent should demand independent arguments instead of coaching the player.
- **Judgement:** neutrally assess whether the player's actual argument identifies the flaw, rejecting purely verbal or logically equivalent restatements. Never award game points.

The pre-existing `BlindspotAgentDefinition` default is retained for direct Agent Administration tests. It is not the execution-time game instruction. All four tasks use the single configured agent and the same existing `IAgentExecutionService`.

## Game task contract

| Game action | Natural-language application request | Expected agent JSON output |
| --- | --- | --- |
| Start a new match: inventory | Pass the inventory-specific `system` asking the agent to enumerate themes across attached documents | `topics` containing document/theme/section labels |
| Open a new duel | Pass the challenge-specific `system`, current round, error type and one randomly selected document/theme/section label; no previous-round context | `topic`, `claim`, private `flaw`, `correction`, optional text `source`, `taunt` |
| Respond to a question | Pass a defense-specific `system`; include only the visible claim, current dialogue and question, never the hidden resolution | `reply` |
| Judge an accusation | Pass a neutral judgement-specific `system`; include the current claim, hidden resolution, dialogue and accusation | Boolean `correct` and `feedback` |

As in MissionBay Agent Administration, BLINDSPOT passes the configured agent identity in the execution context as `agent_id = blindspot.opponent` and `agent_config_group = agent`. MissionBay needs both fields to resolve the agent-owned file references. The game does not access, enumerate, upload or verify attached files. It only processes the document and topic *labels returned as model output*. BLINDSPOT supplies the effective agent settings, a task-specific `system` and a natural-language `prompt` to `AgentExecutionRequest`, then calls `IAgentExecutionService::execute()`. The game consumes the result from `getOutput()` and applies its game rules. The game does not implement a model, agent runtime or document operations. The game's `BlindspotRules` validates model-output game-data shape and text constraints and selects five distinct theme labels. It does not inspect document contents. It does not inspect file attachments, require document metadata, fetch PDF text, verify evidence, or implement a second knowledge route. The error `blindspot.document_unavailable` and document-specific request field have been removed.

No separate chat history or memory is required for the game. The new-round request contains no claims from previous rounds, and execution does not pass a match-scoped `conversation_id`. Questions and answers needed for the current duel are stored in the private game match and passed to the agent only for questions and judgement within that duel. MissionBay remains the owner of any separately configured runtime memory. BLINDSPOT stores a private five-theme plan in the existing match JSON and sends only the current theme label to the agent. This prevents repeats within a match among the selected labels, but cannot verify that the agent inventoried every attachment or follows the selected theme faithfully. Pre-update active matches receive a plan when advancing to the next round.

## Runtime and testing

The authoritative agent ID remains `blindspot.opponent`. Keep `agent_runtime=missionbay`, `enabled=true` and the already selected chat model. The plugin defines no BLINDSPOT-specific agent tools. The server owns scores, turns, match transitions and permission checks.

The supplied unit and smoke tests verify the contract between game tasks, response parsing and the authoritative rules. They do not verify whether a live model can see or cite the configured documents. For that, test a new game using the actual configured agent and review the cited passage against the document in Agent Administration. No separate document-debugging infrastructure is introduced.

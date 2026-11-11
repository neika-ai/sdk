# Changelog

## v0.3.0

**Breaking: the backend calls `/neika-chatbot/v1/...` and the widget tag is `<neika-chatbot-widget>`.** Needs a Neika backend from 2026-11-11 or later.

- Serve the four endpoints and pairing under the new paths:

  ```diff
  -/chatbot/v1/tools
  +/neika-chatbot/v1/tools
  -/chatbot/pair
  +/neika-chatbot/pair
  ```

- `WidgetSnippet::render()` prints `<neika-chatbot-widget>`, and an empty CDN URL means `https://neika.b-cdn.net/widget/neika-chatbot.js`. A cached page with the old `<ai-chat-widget>` keeps working for 30 days.

## v0.2.1

- **Tell tools who is chatting.** `IdentityTokenIssuer::issue()` signs a `ChatIdentity` with your ingest
  secret, `WidgetSnippet::render()` takes it as `identityToken`, and tools read it with
  `ToolCallContext::getIdentity()`.
- `getIdentity(YourClass::class)` returns your own class, filled from the subject and the claims.
- `ToolDefinition::withRequiredIdentity()` hides a tool from chats without an identity.
- `WidgetSnippet::render()` takes `identityTokenUrl`: your route that returns a new token, so a
  long-open page stays signed in without a reload.
- `POST /chatbot/v1/tools/{name}` may carry `context.identity`. `GET /chatbot/v1/tools` sends
  `requiresIdentity: true` for marked tools.
- New dependency: `firebase/php-jwt` `^6.0 || ^7.0`.
- Needs a Neika backend that knows identity tokens. An older one starts the chat without identity.

## v0.2.0

- **Mark arguments that carry personal data with `#[Sensitive]`.** A model hosted outside the EU sees a
  placeholder for them; your tool still gets the real value.
- `ArgumentsSchemaGenerator::listSensitiveArguments()` lists the marked properties, and
  `ToolDefinition::withSensitiveArguments()` adds them to the definition.
- `GET /chatbot/v1/tools` sends `sensitiveArguments` for every tool, `[]` when none are marked.
- Clearer messages on the ingest batch-size, pairing-response, choice-loader and `#[ToolChoice]` type
  exceptions. Update any code that matches their text.

## v0.1.0

First release.

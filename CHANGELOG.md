# Changelog

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

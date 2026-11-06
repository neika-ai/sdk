# Neika SDK

Framework-agnostic PHP core for the Neika chatbot. PHP 8.1+, no framework required. MIT.

Symfony users: use [`neika-ai/symfony-bundle`](https://github.com/neika-ai/symfony-bundle).
Sylius users: use [`neika-ai/sylius-plugin`](https://github.com/neika-ai/sylius-plugin).

## Features

- [Tools and data sources](#examples) — one class each; the JSON Schema comes from your validator constraints
- [Personal data in tools](#personal-data-in-tools) — a model hosted outside the EU reads placeholders; the customer sees real values
- [Standalone setup](#standalone-setup-no-framework) — the four `/chatbot/v1` endpoints in plain PHP
- [Push catalog changes](#outbound-push-catalog-changes) — the chatbot re-indexes just the entries you changed
- [Chat click tracking](#chat-click-tracking) — chat link visits, orders and revenue in the dashboard
- [Pairing](#pairing) — the shop admin clicks **Connect**; nobody copies a secret
- [Widget embed](#widget-embed) — one call prints the chat widget

## Install

```bash
composer require neika-ai/sdk
```

## Examples

A tool — one class, one arguments DTO:

```php
use Neika\Sdk\Contract\ChatbotToolInterface;
use Neika\Sdk\DTO\ContentItem;
use Neika\Sdk\DTO\ToolCallContext;
use Neika\Sdk\DTO\ToolDefinition;
use Neika\Sdk\DTO\ToolResult;

class GreetTool implements ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition('greet', 'app.chatbot.greet.description');
    }

    public function getArgumentsClass(): string
    {
        return GreetArguments::class;
    }

    public function execute(object $arguments, ToolCallContext $context): ToolResult
    {
        return new ToolResult([new ContentItem('Hello ' . $arguments->name)]);
    }
}
```

Argument DTOs carry `symfony/validator` constraints; `ArgumentsSchemaGenerator` turns them into a
JSON Schema (with runtime-loaded choice enums):

```php
use Symfony\Component\Validator\Constraints as Assert;

class GreetArguments
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $name = '',
    ) {
    }
}

// $generator->generate(GreetArguments::class)
// => ['type' => 'object',
//     'properties' => ['name' => ['type' => 'string', 'maxLength' => 64]],
//     'required' => ['name'], 'additionalProperties' => false]
```

Registries wrap a plain iterable of tools/sources and key them by `getDefinition()->name`:

```php
$tool = $toolRegistry->get('greet');   // null when unknown
foreach ($toolRegistry->all() as $tool) { /* ... */ }
```

Names must be unique — on a duplicate, `get()` returns the first match. The Symfony bundle fails
the container build on a duplicate; standalone, keep them distinct yourself.

Also here: `ChatbotDataSourceInterface` (bulk documents), `ToolChoiceLoaderInterface` +
`#[ToolChoice]` (DB-backed enums), the `ChatbotLocaleContextInterface` port the host app implements,
result DTOs (`ToolResult`, `ToolDefinition`, `SourceDocument`, …) and helpers (`CursorCodec`,
`LocaleMatcher`, `HtmlToText`).

## Personal data in tools

**Never put personal data in the text the model reads.** Write a slot, send the value beside it:

```php
use Neika\Sdk\DTO\ContentItem;
use Neika\Sdk\DTO\PrivateValue;
use Neika\Sdk\DTO\ToolResult;

return new ToolResult(
    [new ContentItem('Order {{customer_order_number}} for {{customer_full_name}} shipped.')],
    privateValues: [
        new PrivateValue('customer_order_number', (string) $order->getNumber()),
        new PrivateValue('customer_full_name', $customer->getFullName()),
    ],
);
```

**Name it so the model knows what it is.** `customer_full_name`, not `name`. Lowercase letters, digits and
underscores, starting with a letter, up to 64 characters. A value with any other name is skipped.

With a translated message, pass the slot as the parameter:

```php
$this->translator->trans('order.summary', ['%name%' => '{{customer_full_name}}'], locale: $context->locale);
```

The customer sees `Jan Novák`. A model hosted outside the EU sees `{{customer_full_name_1}}`, and can pass it to
another tool, which receives the real value.

| Send | Where |
|---|---|
| Names, emails, phones, addresses, tracking codes, order numbers | `privateValues` |
| Order state, totals, item counts, product names | The text |
| Anything only the customer needs to see | `blocks` |

**No value, no slot.** If a customer has no tracking code, leave that sentence out. A slot with no value reaches the
model as plain text. If its name ends in a number, like `{{address_1}}`, it's removed from the reply.

**Form fields need nothing.** `text` and `email` values are hidden from that model for you, and your tool still gets
the real ones. Set `placeholder` on the field for a clearer name than its field name:

```php
new FormField('orderNumber', 'order.number', FormFieldType::Text, true, placeholder: 'customer_order_number');
```

> Needs a chatbot backend that reads `privateValues`. An older one sends `{{customer_full_name}}` to the model as
> plain text.

## Standalone setup (no framework)

The SDK owns the logic, not the transport. You wire the registries once, then map four HTTP routes
to them.

Wire the pieces — each registry takes a plain list of services, no container:

```php
use Neika\Sdk\Registry\ToolRegistry;
use Neika\Sdk\Registry\DataSourceRegistry;
use Neika\Sdk\Registry\ToolChoiceLoaderRegistry;
use Neika\Sdk\Schema\ArgumentsSchemaGenerator;
use Symfony\Component\Validator\Validation;

$tools   = [new GreetTool()];   // ChatbotToolInterface, keyed at runtime by getDefinition()->name
$sources = [];                  // ChatbotDataSourceInterface
$loaders = [];                  // ToolChoiceLoaderInterface, matched by class

$toolRegistry   = new ToolRegistry($tools);
$sourceRegistry = new DataSourceRegistry($sources);
$schema         = new ArgumentsSchemaGenerator(new ToolChoiceLoaderRegistry($loaders));
$validator      = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
```

`GET /chatbot/v1/tools` — tool list with input schemas:

```php
$out = [];
foreach ($toolRegistry->all() as $tool) {
    $out[] = $tool->getDefinition()
        ->withInputSchema($schema->generate($tool->getArgumentsClass()))
        ->jsonSerialize();
}
echo json_encode(['tools' => $out]);
```

`POST /chatbot/v1/tools/{name}` — body `{ "arguments": {...}, "context": {...} }`:

```php
use Neika\Sdk\DTO\ToolCallContext;
use Neika\Sdk\DTO\Violation;
use Neika\Sdk\Exception\ToolNotFoundException;
use Neika\Sdk\Exception\ArgumentsValidationException;

$tool = $toolRegistry->get($name) ?? throw new ToolNotFoundException($name);
$body = json_decode($rawRequestBody, true);

$arguments = $serializer->denormalize($body['arguments'] ?? [], $tool->getArgumentsClass());

$violations = [];
foreach ($validator->validate($arguments) as $violation) {
    $violations[] = new Violation($violation->getPropertyPath(), (string) $violation->getMessage());
}
if ($violations !== []) {
    throw new ArgumentsValidationException($violations);
}

$context = new ToolCallContext(
    $body['context']['conversationId'],
    $body['context']['locale'],
    $body['context']['channelCode'] ?? null,
);
echo json_encode($tool->execute($arguments, $context)->jsonSerialize());
```

`GET /chatbot/v1/sources` and `GET /chatbot/v1/sources/{name}`:

```php
use Neika\Sdk\DTO\SourceQuery;
use Neika\Sdk\Exception\SourceNotFoundException;

// list
$out = [];
foreach ($sourceRegistry->all() as $source) {
    $out[] = $source->getDefinition()->jsonSerialize();
}
echo json_encode(['sources' => $out]);

// read: ?locale=cs_CZ&channel=&cursor=&ids[]=
$source = $sourceRegistry->get($name) ?? throw new SourceNotFoundException($name);
$query = new SourceQuery(
    locale: $_GET['locale'] ?? '',
    channel: $_GET['channel'] ?? null,
    cursor: $_GET['cursor'] ?? null,
    ids: $_GET['ids'] ?? null,
);
echo json_encode($source->getDocuments($query)->jsonSerialize());
```

You provide, around the SDK: **auth**, **routing**, and **argument deserialization** (any
deserializer; the bundles use `symfony/serializer`). Locale
matching against a channel's served locales is optional — use `LocaleMatcher` and implement
`ChatbotLocaleContextInterface` if you have channels.

## HTTP contract (what the chatbot backend expects)

The paths are fixed: the backend calls `/chatbot/v1/...` on your host. Serve the endpoints at exactly
these paths — only the origin (scheme + host) is yours to configure on the backend.

| Method | Path | Request | Response |
|---|---|---|---|
| GET | `/chatbot/v1/tools` | `Accept-Language` (optional) | `{ "tools": [ {name, description, inputSchema, ui?} ] }` |
| POST | `/chatbot/v1/tools/{name}` | `{ "arguments": {...}, "context": { "conversationId", "locale", "channelCode"? } }` | `{ "content": [{type,text}], "blocks": [], "isError": bool, "privateValues"?: [{key,value}] }` |
| GET | `/chatbot/v1/sources` | — | `{ "sources": [ {name, description, locales} ] }` |
| GET | `/chatbot/v1/sources/{name}` | `?locale=&channel=&cursor=&ids[]=` (`ids[]` max 500; then `cursor` ignored, `nextCursor` null) | `{ "documents": [...], "nextCursor": string\|null }` |

- **Auth** — the backend sends `Authorization: Bearer <api-secret>`. The SDK does no auth; verify
  the header yourself (`hash_equals`) or let the Symfony bundle's firewall do it.
- **Errors** — every failure returns `{ "error": { "code", "message", "violations" } }`. Throw a
  `ChatbotApiException` subclass; `getErrorCode()` gives the `code`, `getStatusCode()` the HTTP
  status (`tool_not_found`/`source_not_found` 404, `validation_failed` 422 with `violations`,
  `invalid_cursor`/`invalid_locale` 400).

## Outbound: push catalog changes

Tell the backend which catalog entries changed so it re-indexes them —
`POST {backend}/api/v1/catalog/changes`, auth `Bearer {siteKey}.{ingestSecret}`.

The client speaks PSR-18, so plug in any HTTP client (Guzzle, Symfony's `Psr18Client`, …) and PSR-17
factories:

```php
use Neika\Sdk\DTO\CatalogChange;
use Neika\Sdk\DTO\SiteCredentials;
use Neika\Sdk\Enum\CatalogSourceName;
use Neika\Sdk\Ingest\CatalogIngestClient;

$client = new CatalogIngestClient(
    $psr18Client,     // Psr\Http\Client\ClientInterface
    $psr17Factory,    // Psr\Http\Message\RequestFactoryInterface
    $psr17Factory,    // Psr\Http\Message\StreamFactoryInterface
    'https://neika.ai',
);

$credentials = new SiteCredentials($siteKey, $ingestSecret);
$change = new CatalogChange(CatalogSourceName::Products, 'cs_CZ', ['CLIPPER-01', 'CLIPPER-02']);
$result = $client->send($credentials, $change);

if ($result->isThrottled()) {
    // nothing was queued — send the same ids again after $result->retryAfterSeconds
}
foreach ($result->jobs as $job) {
    // $job->externalId, $job->jobId, $job->status (CatalogJobStatus), $job->violation
}
```

**Credentials go in per call, not in the constructor.** Build `SiteCredentials` from wherever you
store them, right before the call → a re-paired secret works on the next call, also in long-running
workers.

- `source` is `products`, `categories`, or `cms_pages` (`CatalogSourceName`).
- **Max 500 ids per call.** More than that throws — chunk them yourself.
- `202` → `accepted`, with a per-id job list: `queued`, or `rejected` with a `violation`. The other ids
  of the batch are still queued.
- `jobId` is the same for every send of one id (per source and locale). Sending an id again is always safe.
- `429` (over 30 calls a second from your IP) or `503` (backend can't queue right now) → `isThrottled()`,
  nothing was queued. Wait `retryAfterSeconds` (1 s when the backend sends no `Retry-After`) and send the
  same ids again.
- Auth/validation failures throw `CatalogIngestException` (`getStatusCode()`, `getBackendErrorCode()`).

## Chat click tracking

Links the chat sends to your site carry a `neikaclid` query parameter. Report the landing and the
orders it leads to, and the dashboard shows chat link visits, orders and revenue. Same auth and
setup as the catalog client.

```php
use Neika\Sdk\DTO\ChatOrder;
use Neika\Sdk\DTO\SiteCredentials;
use Neika\Sdk\Exception\TelemetryException;
use Neika\Sdk\Telemetry\ClickId;
use Neika\Sdk\Telemetry\TelemetryClient;

$telemetry = new TelemetryClient($psr18Client, $psr17Factory, $psr17Factory, 'https://neika.ai');
$credentials = new SiteCredentials($siteKey, $ingestSecret);

try {
    // landing page view: a real browser GET, after the response succeeded
    $clickId = (new ClickId())->find($_GET);
    if ($clickId !== null) {
        $telemetry->reportLinkVisit($credentials, $clickId, 'https://shop.example/product/clipper');
    }

    // order placed: keep the click id (e.g. in the session) until checkout
    $telemetry->reportOrder($credentials, new ChatOrder($clickId, $order->getNumber(), 123450, 'CZK'));
} catch (TelemetryException $exception) {
    $logger->warning($exception->getMessage());   // never fail the page or the order over it
}
```

- `POST {backend}/api/v1/catalog/link-visits` → `{clickId, pageUrl}`. Send the page URL without query or fragment.
- `POST {backend}/api/v1/catalog/chat-orders` → `{clickId, orderNumber, revenue, currency}`.
- **`revenue` is in ISO 4217 minor units**: CZK 1234.50 → `123450`, JPY 500 → `500`. `currency` is the ISO code.
- Skip bots, link previews and prefetch requests (`Sec-Purpose: prefetch`).
- `202` → done. Anything else, `429` included, throws `TelemetryException` (`getStatusCode()`, `getBackendErrorCode()`). Log it and move on.

## Pairing

**The shop admin clicks Connect in the Neika dashboard; your shop gets its credentials.** The
browser lands on `{your base URL}/chatbot/pair?code=…`. Serve that path:

```php
use Neika\Sdk\Exception\PairingException;
use Neika\Sdk\Pairing\HostMatcher;
use Neika\Sdk\Pairing\PairingClient;

$pairing = new PairingClient($psr18Client, $psr17Factory, $psr17Factory, 'https://neika.ai');

$apiSecret = bin2hex(random_bytes(32));
$ingestSecret = bin2hex(random_bytes(32));

try {
    $result = $pairing->complete($_GET['code'] ?? '', $apiSecret, $ingestSecret);
} catch (PairingException $exception) {
    http_response_code(400);
    echo htmlspecialchars($exception->getErrorCode());
    return;
}

$isOwnHost = (new HostMatcher())->isSameHost($result->baseUrl, 'shop.example');
if (!$isOwnHost) {
    http_response_code(400);
    return;
}

$store->save($result->siteKey, $apiSecret, $ingestSecret, $result->channelCodes);
header('Location: ' . $result->returnUrl, true, 302);
```

- `POST {backend}/api/v1/tool-server-pairings/complete` → `{code, apiSecret, ingestSecret}`.
- `200` → `PairingResult`: `siteKey`, `baseUrl` (the connection's address), `channelCodes`, `returnUrl`,
  `verifiedDomains` (the website's verified domains).
- **Compare the host of `baseUrl` with a host you configured**, never the request's `Host` header.
  `HostMatcher::isSameHost()` ignores case, a trailing dot and IDN spelling; `www.` counts.
  Mismatch → someone else's code; save nothing.
- Serving the channels on several hosts? Check each host against `verifiedDomains` with
  `HostMatcher::isCoveredByDomain($host, $domain)` → same domain or its subdomain, `www.` stripped.
  Any host left over → save nothing.
- Secrets: 32–128 chars; `bin2hex(random_bytes(32))` gives 64.
- `apiSecret` → the backend sends it as `Authorization: Bearer <apiSecret>` on every `/chatbot/v1` call.
- `ingestSecret` + `siteKey` → your `SiteCredentials`.
- Redirect to `returnUrl` → the dashboard tests the connection with the new secret.
- Send it to your configured https backend only, never to a URL from the query.

> The code works once and expires 10 minutes after the admin clicked Connect. A refused code is used
> up too → the admin clicks Connect again.

Failures throw `PairingException`; `getErrorCode()` is the backend's `error.code` or one of these:

| Code | When |
|---|---|
| `pairing_not_found` | Code unknown, used or expired |
| `validation_failed` | Bad body, e.g. a secret outside 32–128 chars |
| `rate_limited` | Too many unknown codes from your IP; wait 10 minutes |
| `transport_failed` | Backend unreachable (`PairingException::TRANSPORT_FAILED`) |
| `invalid_response` | Non-200 without an error code, or a 200 missing a field (`PairingException::INVALID_RESPONSE`) |

## Widget embed

Render the chat widget markup for any page:

```php
use Neika\Sdk\Widget\WidgetSnippet;

echo (new WidgetSnippet())->render(
    'https://neika.ai',  // backend origin
    $siteKey,               // public site key
    $cdnUrl,                // optional; '' → https://neika.b-cdn.net/widget/v1/chat.js
    $locale,                // optional; '' → the browser detects it
    defer: true,            // optional; false → plain <script src="…"> without defer
);
```

Emits the loader `<script>` and the `<ai-chat-widget>` element; all attribute values are escaped.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT — see [LICENSE](LICENSE).

# Neika SDK

Framework-agnostic PHP core for the Neika chatbot. PHP 8.1+, MIT.

Symfony users: use [`neika-ai/symfony-bundle`](https://github.com/neika-ai/symfony-bundle).
Sylius users: use [`neika-ai/sylius-plugin`](https://github.com/neika-ai/sylius-plugin).

## Features

- [Tools and data sources](#tools-and-data-sources) — one class each; the JSON Schema comes from your validator constraints
- [Personal data in tools](#personal-data-in-tools) — a model hosted outside the EU reads placeholders; the customer sees real values
- [Who is chatting](#who-is-chatting) — a tool answers for the signed-in person; neither the model nor the visitor can change who that is
- [Standalone setup](#standalone-setup) — the four `/chatbot/v1` endpoints in plain PHP
- [Push catalog changes](#push-catalog-changes) — the chatbot re-indexes just the entries you changed
- [Chat click tracking](#chat-click-tracking) — chat link visits, orders and revenue in the dashboard
- [Pairing](#pairing) — the shop admin clicks **Connect**; nobody copies a secret
- [Widget embed](#widget-embed) — one call prints the chat widget

## Install

```bash
composer require neika-ai/sdk
```

## Tools and data sources

A tool is one class plus one arguments DTO:

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

`ArgumentsSchemaGenerator` turns the DTO's `symfony/validator` constraints into a JSON Schema:

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

For choices loaded at runtime (from the DB, say), put `#[ToolChoice(loader: RegionLoader::class)]` on the
property and implement `ToolChoiceLoaderInterface`. The schema lists the loaded values as its `enum`.

A data source implements `ChatbotDataSourceInterface` and returns a `DocumentPage` of `SourceDocument`s.
Page through it with `CursorCodec`; `HtmlToText` turns your HTML into plain text.

**Names must be unique.** The registries look tools and sources up by `getDefinition()->name`, and the
first match wins. The Symfony bundle fails the container build on a duplicate. Standalone, keep them
distinct yourself.

## Personal data in tools

**Never put personal data in the text the model reads.** Write a slot and send the value next to it:

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

**Name the slot so the model knows what it is.** `customer_full_name`, not `name`. Use lowercase
letters, digits and underscores, start with a letter, and stay within 64 characters. A value with any
other name is skipped.

With a translated message, pass the slot as the parameter:

```php
$this->translator->trans('order.summary', ['%name%' => '{{customer_full_name}}'], locale: $context->locale);
```

The customer sees `Jan Novák`. A model hosted outside the EU sees `{{customer_full_name_1}}`. It can
pass that to another tool, and that tool gets the real value.

| Send | Where |
|---|---|
| Names, emails, phones, addresses, tracking codes, order numbers | `privateValues` |
| Order state, totals, item counts, product names | The text |
| Anything only the customer needs to see | `blocks` |

**No value, no slot.** If a customer has no tracking code, leave that sentence out. A slot with no value
reaches the model as plain text. If its name ends in a number, like `{{address_1}}`, it's removed from
the reply.

**Form fields need nothing.** That model never sees `text` and `email` values, and your tool still gets
the real ones. Set `placeholder` on the field to give the slot a clearer name than the field name:

```php
new FormField('orderNumber', 'order.number', FormFieldType::Text, true, placeholder: 'customer_order_number');
```

**Mark arguments that carry personal data.** Put `#[Sensitive]` on the property. That model sees a
placeholder, and your tool still gets the real value:

```php
use Neika\Sdk\Validator\Sensitive;

#[Assert\NotBlank]
#[Assert\Email]
#[Sensitive]
public string $email = '',
```

Unmarked arguments reach the model as typed. Mark contact data only: emails, phones, names, addresses,
customer and order numbers.

## Who is chatting

**Sign the person's identity on your server, and every tool call carries it.** Use it for "my orders",
"packages in my storage unit" and anything else that depends on who asks.

1. The page that shows the widget:

```php
use Neika\Sdk\DTO\ChatIdentity;
use Neika\Sdk\DTO\SiteCredentials;
use Neika\Sdk\Identity\IdentityTokenIssuer;
use Neika\Sdk\Widget\WidgetSnippet;

$token = (new IdentityTokenIssuer())->issue(
    new SiteCredentials($siteKey, $ingestSecret),
    new ChatIdentity('admin-42', ['storageUnit' => 'B2']),
);

echo (new WidgetSnippet())->render('https://neika.ai', $siteKey, identityToken: $token);
```

> Send every page that prints a token with `Cache-Control: private, no-store`. A cached page gives one
> person's identity to the next visitor.

2. Your tool:

```php
public function getDefinition(): ToolDefinition
{
    return (new ToolDefinition('list_packages', 'app.chatbot.packages.description'))->withRequiredIdentity();
}

public function execute(object $arguments, ToolCallContext $context): ToolResult
{
    $identity = $context->getIdentity();
    $orders = $this->orders->findToPack($identity->subject, $identity->claims['storageUnit']);
    // ...
}
```

**Want your own class instead of an array?** Pass it to `getIdentity()`:

```php
class PackerIdentity
{
    public function __construct(
        public readonly string $subject,
        public readonly string $storageUnit,
        public readonly array $roles = [],
    ) {
    }
}

$packer = $context->getIdentity(PackerIdentity::class);
$orders = $this->orders->findToPack($packer->subject, $packer->storageUnit);
```

- `subject` and each claim go to the constructor parameter of the same name. Nested data arrives as arrays.
- A claim with no parameter, a missing required parameter or a wrong type throws `IdentityMappingException`.

**Never take the person's id as a tool argument.** The model writes the arguments. The identity comes
from your signature.

| Part | Rule |
|---|---|
| `subject` | Your id for the person, up to 255 characters. Use an id, not an email. |
| `claims` | Any JSON data you want the tool to have, keyed by name. Nested arrays are fine. Up to 4096 bytes as JSON. Key order isn't kept. No claim named `subject`. |
| Lifetime | 30 minutes. Third argument of `issue()` sets it: 300 to 7200 seconds. Anything else throws `InvalidArgumentException`. |
| `withRequiredIdentity()` | Chats without an identity never see the tool. After you deploy it, refresh the tool list in the Neika dashboard; until then the tool is offered and refuses. |
| `getIdentity()` | Throws `IdentityRequiredException` (`identity_required`, 403) when the call has no identity. |
| `hasIdentity()` | For a tool that works for everyone and shows more to a signed-in person. |

- **Sign with the ingest secret your site got from [pairing](#pairing).** Only the site paired with that
  secret gets the identity in its tool calls. An ingest secret added by hand in the dashboard can't sign.
- **One person, one chat.** On a shared browser each `subject` gets its own conversation.
- The identity stays with the conversation. A token for another `subject` can't be attached to it.

When the token runs out:

| Page | What happens |
|---|---|
| Chat already open | Tools marked `withRequiredIdentity()` answer that the sign-in expired. Other tools get no identity. |
| New chat | It doesn't start; the visitor sees "try again". |
| After a reload | The page prints a new token and the open chat picks it up on the next message. |

### Keep long-open pages signed in

Optional. Serve a route that returns a new token, and pass its URL:

```php
// POST /chat-token, behind your own login
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['token' => $issuer->issue($credentials, $identity)]);
```

```php
echo (new WidgetSnippet())->render(
    'https://neika.ai',
    $siteKey,
    identityToken: $token,
    identityTokenUrl: '/chat-token',
);
```

- The widget sends a `POST` there, with the visitor's cookies, when it starts a chat or sends a message
  and its token has under 30 seconds left. No reload.
- Keep the route on the page's own origin. Return the same `subject` the page was rendered for.
- Signed out? Return `401`. The widget asks again a minute later.

Not covered:

- The dashboard's test chat has no identity, so try these tools on your own page.

## Standalone setup

Without a framework, you bring auth, routing and argument deserialization. The SDK does the rest.

1. Wire the registries. Each takes a plain list of services:

```php
use Neika\Sdk\Registry\DataSourceRegistry;
use Neika\Sdk\Registry\ToolChoiceLoaderRegistry;
use Neika\Sdk\Registry\ToolRegistry;
use Neika\Sdk\Schema\ArgumentsSchemaGenerator;
use Neika\Sdk\Validator\ToolChoiceValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

$toolRegistry   = new ToolRegistry([new GreetTool()]);
$sourceRegistry = new DataSourceRegistry([]);         // ChatbotDataSourceInterface
$choiceLoaders  = new ToolChoiceLoaderRegistry([]);   // ToolChoiceLoaderInterface
$schema         = new ArgumentsSchemaGenerator($choiceLoaders);
$validator      = Validation::createValidatorBuilder()
    ->enableAttributeMapping()
    ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
        ToolChoiceValidator::class => new ToolChoiceValidator($choiceLoaders),
    ]))
    ->getValidator();
```

> Without that validator factory, validating a `#[ToolChoice]` argument throws `ArgumentCountError`.

2. `GET /chatbot/v1/tools` lists the tools with their input schemas:

```php
$out = [];
foreach ($toolRegistry->all() as $tool) {
    $argumentsClass = $tool->getArgumentsClass();
    $out[] = $tool->getDefinition()
        ->withInputSchema($schema->generate($argumentsClass))
        ->withSensitiveArguments($schema->listSensitiveArguments($argumentsClass))
        ->jsonSerialize();
}
echo json_encode(['tools' => $out]);
```

3. `POST /chatbot/v1/tools/{name}` runs a tool. Deserialize the arguments with any deserializer; the
   bundles use `symfony/serializer`:

```php
use Neika\Sdk\DTO\ChatIdentity;
use Neika\Sdk\DTO\ToolCallContext;
use Neika\Sdk\DTO\Violation;
use Neika\Sdk\Exception\ArgumentsValidationException;
use Neika\Sdk\Exception\ToolNotFoundException;

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

$identity = $body['context']['identity'] ?? null;

$context = new ToolCallContext(
    $body['context']['conversationId'],
    $body['context']['locale'],
    $body['context']['channelCode'] ?? null,
    $identity === null ? null : new ChatIdentity($identity['subject'], $identity['claims'] ?? []),
);
echo json_encode($tool->execute($arguments, $context)->jsonSerialize());
```

4. `GET /chatbot/v1/sources` and `GET /chatbot/v1/sources/{name}` list and read the data sources:

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

Have channels? Implement `ChatbotLocaleContextInterface` and match the requested locale against a
channel's locales with `LocaleMatcher`.

## HTTP contract

**The paths are fixed.** The backend calls `/chatbot/v1/...` on your host; you only set the origin
(scheme and host) on the backend.

| Method | Path | Request | Response |
|---|---|---|---|
| GET | `/chatbot/v1/tools` | `Accept-Language` (optional) | `{ "tools": [ {name, description, inputSchema, sensitiveArguments, requiresIdentity?, ui?} ] }` |
| POST | `/chatbot/v1/tools/{name}` | `{ "arguments": {...}, "context": { "conversationId", "locale", "channelCode"?, "identity"?: {subject, claims} } }` | `{ "content": [{type,text}], "blocks": [], "isError": bool, "privateValues"?: [{key,value}] }` |
| GET | `/chatbot/v1/sources` | — | `{ "sources": [ {name, description, locales} ] }` |
| GET | `/chatbot/v1/sources/{name}` | `?locale=&channel=&cursor=&ids[]=` | `{ "documents": [...], "nextCursor": string\|null }` |

- `ids[]` takes up to 500 ids. With `ids[]`, the cursor is ignored and `nextCursor` is `null`.
- **Check the auth header yourself.** The backend sends `Authorization: Bearer <apiSecret>`. Compare it
  with `hash_equals()`, or let the Symfony bundle's firewall do it.
- Every failure returns `{ "error": { "code", "message", "violations" } }`. Throw a `ChatbotApiException`
  subclass: `getErrorCode()` gives the `code`, `getStatusCode()` the HTTP status.

| Exception | Code | Status |
|---|---|---|
| `ToolNotFoundException` | `tool_not_found` | 404 |
| `SourceNotFoundException` | `source_not_found` | 404 |
| `ArgumentsValidationException` | `validation_failed`, with `violations` | 422 |
| `InvalidCursorException` | `invalid_cursor` | 400 |
| `InvalidLocaleException` | `invalid_locale` | 400 |
| `IdentityRequiredException` | `identity_required` | 403 |

## Push catalog changes

Tell the backend which catalog entries changed, and it re-indexes them. The client speaks PSR-18, so
plug in any HTTP client (Guzzle, Symfony's `Psr18Client`) and PSR-17 factories:

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
    // nothing was queued: send the same ids again after $result->retryAfterSeconds
}
foreach ($result->jobs as $job) {
    // $job->externalId, $job->jobId, $job->status (CatalogJobStatus), $job->violation
}
```

**Build `SiteCredentials` right before each call.** The client doesn't keep them, so a re-paired secret
works on the next call, in long-running workers too.

- It sends `POST {backend}/api/v1/catalog/changes` with `Authorization: Bearer {siteKey}.{ingestSecret}`.
- `source` is `products`, `categories` or `cms_pages` (`CatalogSourceName`).
- **Send 1 to 500 ids per call.** Anything else throws `InvalidArgumentException`, so chunk them yourself.
- `202` → `accepted`, with one job per id: `queued`, or `rejected` with a `violation`. The other ids
  are still queued.
- `jobId` stays the same for every send of one id, per source and locale. Sending an id again is safe.
- `429` (over 30 calls a second from your IP) or `503` (the backend can't queue right now) →
  `isThrottled()`, and nothing was queued. Wait `retryAfterSeconds` and send the same ids again. With
  no `Retry-After` header, it's 1 second.
- Auth and validation failures throw `CatalogIngestException` (`getStatusCode()`, `getBackendErrorCode()`).

## Chat click tracking

Links the chat sends to your site carry a `neikaclid` query parameter. Report the landing and the
orders it leads to, and the dashboard shows chat link visits, orders and revenue. Auth and setup match
the catalog client.

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
        $session->set('neikaclid', $clickId);
        $telemetry->reportLinkVisit($credentials, $clickId, 'https://shop.example/product/clipper');
    }

    // order placed: report it only when the session kept a click id
    $orderClickId = $session->get('neikaclid');
    if ($orderClickId !== null) {
        $telemetry->reportOrder($credentials, new ChatOrder($orderClickId, $order->getNumber(), 123450, 'CZK'));
    }
} catch (TelemetryException $exception) {
    $logger->warning($exception->getMessage());   // never fail the page or the order over it
}
```

- `POST {backend}/api/v1/catalog/link-visits` sends `{clickId, pageUrl}`. Send the page URL without
  query or fragment.
- `POST {backend}/api/v1/catalog/chat-orders` sends `{clickId, orderNumber, revenue, currency}`.
- **`revenue` is in ISO 4217 minor units**: CZK 1234.50 → `123450`, JPY 500 → `500`. `currency` is the
  ISO code.
- Skip bots, link previews and prefetch requests (`Sec-Purpose: prefetch`).
- `202` means done. Anything else, `429` included, throws `TelemetryException` (`getStatusCode()`,
  `getBackendErrorCode()`). Log it and move on.

## Pairing

**The shop admin clicks Connect in the Neika dashboard, and your shop gets its credentials.** The
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

- It sends `POST {backend}/api/v1/tool-server-pairings/complete` with `{code, apiSecret, ingestSecret}`.
  Send it to your configured https backend only, never to a URL from the query.
- `200` → `PairingResult`: `siteKey`, `baseUrl` (the connection's address), `channelCodes`, `returnUrl`
  and `verifiedDomains` (the website's verified domains).
- **Compare the host of `baseUrl` with a host you configured**, never with the request's `Host` header.
  `HostMatcher::isSameHost()` ignores case, a trailing dot and IDN spelling, but not `www.`. On a
  mismatch the code is someone else's: save nothing.
- Serving the channels on several hosts? Check each host against `verifiedDomains` with
  `HostMatcher::isCoveredByDomain($host, $domain)`. It accepts the domain and its subdomains, with
  `www.` stripped. If any host isn't covered, save nothing.
- Secrets are 32 to 128 characters; `bin2hex(random_bytes(32))` gives 64.
- `apiSecret` is the bearer token the backend sends on every `/chatbot/v1` call.
- `ingestSecret` and `siteKey` make your `SiteCredentials`.
- Redirect to `returnUrl`, and the dashboard tests the connection with the new secret.

> The code works once and expires 10 minutes after the admin clicked Connect. A refused code is used
> up too, so the admin clicks Connect again.

Failures throw `PairingException`. `getErrorCode()` is the backend's `error.code` or one of these:

| Code | When |
|---|---|
| `pairing_not_found` | The code is unknown, used or expired. |
| `validation_failed` | The body is bad, e.g. a secret outside 32–128 characters. |
| `rate_limited` | Too many unknown codes came from your IP. Wait 10 minutes. |
| `transport_failed` | The backend is unreachable (`PairingException::TRANSPORT_FAILED`). |
| `invalid_response` | A non-200 came without an error code, or a 200 is missing a field (`PairingException::INVALID_RESPONSE`). |

## Widget embed

```php
use Neika\Sdk\Widget\WidgetSnippet;

echo (new WidgetSnippet())->render(
    'https://neika.ai',  // backend origin
    $siteKey,            // public site key
    $cdnUrl,             // optional; '' → https://neika.b-cdn.net/widget/v1/chat.js
    $locale,             // optional; '' → the browser detects it
    defer: true,         // optional; false → plain <script src="…"> without defer
);
```

`identityToken` and `identityTokenUrl`: see [Who is chatting](#who-is-chatting).

It prints the loader `<script>` and the `<ai-chat-widget>` element, with every attribute value escaped.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT, see [LICENSE](LICENSE).

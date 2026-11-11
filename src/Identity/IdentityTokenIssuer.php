<?php

declare(strict_types=1);

namespace Neika\Sdk\Identity;

use Firebase\JWT\JWT;
use Neika\Sdk\DTO\ChatIdentity;
use Neika\Sdk\DTO\SiteCredentials;

class IdentityTokenIssuer
{
    public const MIN_TTL_SECONDS = 300;

    public const MAX_TTL_SECONDS = 7200;

    public const DEFAULT_TTL_SECONDS = 1800;

    private const ALGORITHM = 'HS256';

    private const MIN_SECRET_LENGTH = 32;

    private const MAX_SUBJECT_LENGTH = 255;

    private const MAX_CLAIMS_BYTES = 4096;

    private const ENCODED_NUL_CHARACTER = '\u0000';

    public function issue(
        SiteCredentials $credentials,
        ChatIdentity $identity,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): string {
        $this->assertSecret($credentials);
        $this->assertTtl($ttlSeconds);
        $this->assertSubject($identity->subject);
        $this->assertClaims($identity->claims);

        $issuedAt = time();
        $payload = [
            'sub' => $identity->subject,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttlSeconds,
            'claims' => (object) $identity->claims,
        ];

        return JWT::encode($payload, $credentials->ingestSecret, self::ALGORITHM);
    }

    private function assertSecret(SiteCredentials $credentials): void
    {
        $isLongEnough = strlen($credentials->ingestSecret) >= self::MIN_SECRET_LENGTH;
        if (!$isLongEnough) {
            throw new \InvalidArgumentException(sprintf('The ingest secret must be at least %d characters to sign an identity token.', self::MIN_SECRET_LENGTH));
        }
    }

    private function assertTtl(int $ttlSeconds): void
    {
        $isTooShort = $ttlSeconds < self::MIN_TTL_SECONDS;
        $isTooLong = $ttlSeconds > self::MAX_TTL_SECONDS;
        if ($isTooShort || $isTooLong) {
            throw new \InvalidArgumentException(sprintf('The identity token lifetime must be between %d and %d seconds, got %d.', self::MIN_TTL_SECONDS, self::MAX_TTL_SECONDS, $ttlSeconds));
        }
    }

    private function assertSubject(string $subject): void
    {
        $isBlank = trim($subject) === '';
        $isTooLong = mb_strlen($subject) > self::MAX_SUBJECT_LENGTH;
        if ($isBlank || $isTooLong) {
            throw new \InvalidArgumentException(sprintf('The identity subject must be 1 to %d characters.', self::MAX_SUBJECT_LENGTH));
        }

        $hasNulCharacter = str_contains($subject, "\0");
        if ($hasNulCharacter) {
            throw new \InvalidArgumentException('The identity subject cannot contain a NUL character.');
        }
    }

    /**
     * @param array<array-key, mixed> $claims
     */
    private function assertClaims(array $claims): void
    {
        $hasClaims = $claims !== [];
        $isList = array_is_list($claims);
        if ($hasClaims && $isList) {
            throw new \InvalidArgumentException('Identity claims must be keyed by name, not a list.');
        }

        $hasSubjectClaim = array_key_exists('subject', $claims);
        if ($hasSubjectClaim) {
            throw new \InvalidArgumentException('A claim cannot be named "subject"; that is the identity\'s own field.');
        }

        try {
            $encoded = json_encode($claims, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(sprintf('Identity claims must be JSON data, got "%s".', $exception->getMessage()), 0, $exception);
        }

        $hasNulCharacter = str_contains($encoded, self::ENCODED_NUL_CHARACTER);
        if ($hasNulCharacter) {
            throw new \InvalidArgumentException('Identity claims cannot contain a NUL character.');
        }

        $isTooLarge = strlen($encoded) > self::MAX_CLAIMS_BYTES;
        if ($isTooLarge) {
            throw new \InvalidArgumentException(sprintf('Identity claims take %d bytes as JSON, the limit is %d.', strlen($encoded), self::MAX_CLAIMS_BYTES));
        }
    }
}

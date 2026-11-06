<?php

declare(strict_types=1);

namespace Neika\Sdk\Http;

class BackendRejectionMessage
{
    private const MAX_LISTED_VIOLATIONS = 5;

    /**
     * @param array<array-key, mixed> $error
     */
    public function build(string $backendName, int $statusCode, array $error): string
    {
        return sprintf(
            'The %s backend rejected the request with HTTP %d%s%s.',
            $backendName,
            $statusCode,
            $this->formatErrorCode($error['code'] ?? null),
            $this->formatViolations($error['violations'] ?? null),
        );
    }

    private function formatErrorCode(mixed $rawErrorCode): string
    {
        $isUsableErrorCode = is_string($rawErrorCode) && $rawErrorCode !== '';
        if (!$isUsableErrorCode) {
            return '';
        }

        return sprintf(', error code "%s"', $rawErrorCode);
    }

    private function formatViolations(mixed $rawViolations): string
    {
        $violationTexts = $this->getViolationTexts($rawViolations);
        if ($violationTexts === []) {
            return '';
        }

        $listedViolationTexts = array_slice($violationTexts, 0, self::MAX_LISTED_VIOLATIONS);
        $unlistedCount = count($violationTexts) - count($listedViolationTexts);
        $unlistedSuffix = $unlistedCount > 0 ? sprintf(' and %d more', $unlistedCount) : '';

        return sprintf(': %s%s', implode('; ', $listedViolationTexts), $unlistedSuffix);
    }

    /**
     * @return list<string>
     */
    private function getViolationTexts(mixed $rawViolations): array
    {
        $isList = is_array($rawViolations);
        if (!$isList) {
            return [];
        }

        $violationTexts = [];
        foreach ($rawViolations as $rawViolation) {
            $violationText = $this->findViolationText($rawViolation);
            if ($violationText !== null) {
                $violationTexts[] = $violationText;
            }
        }

        return $violationTexts;
    }

    private function findViolationText(mixed $rawViolation): ?string
    {
        $isViolation = is_array($rawViolation);
        if (!$isViolation) {
            return null;
        }

        $path = $rawViolation['path'] ?? '';
        $message = $rawViolation['message'] ?? null;
        $isReadable = is_string($path) && is_string($message);
        if (!$isReadable) {
            return null;
        }

        return sprintf('"%s" %s', $path, rtrim($message, '.'));
    }
}

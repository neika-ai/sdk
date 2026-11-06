<?php

declare(strict_types=1);

namespace Neika\Sdk\Text;

class HtmlToText
{
    public function convert(string $html): string
    {
        $withoutHiddenBlocks = $this->replacePattern('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html);
        $withoutUnclosedHiddenBlocks = $this->replacePattern(
            '#<(script|style|noscript)\b[^>]*>.*$#is',
            ' ',
            $withoutHiddenBlocks,
        );
        $withLineBreaks = $this->replacePattern('#<br\s*/?>#i', "\n", $withoutUnclosedHiddenBlocks);
        $withBullets = $this->replacePattern('#<li\b[^>]*>#i', "\n- ", $withLineBreaks);
        $withHeadingSpacing = $this->replacePattern('#<h[1-6]\b[^>]*>#i', "\n\n", $withBullets);
        $withBlockBreaks = $this->replacePattern(
            '#</(p|div|ul|ol|h[1-6]|tr|table|blockquote|section|article)>#i',
            "\n",
            $withHeadingSpacing,
        );

        $stripped = strip_tags($withBlockBreaks);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $withoutNonBreakingSpaces = str_replace("\u{A0}", ' ', $decoded);

        $trimmedLines = array_map(
            fn (string $line): string => trim($this->replacePattern('/[ \t]+/', ' ', $line)),
            explode("\n", $withoutNonBreakingSpaces),
        );
        $joined = implode("\n", $trimmedLines);
        $collapsed = $this->replacePattern("/\n{3,}/", "\n\n", $joined);

        return trim($collapsed);
    }

    private function replacePattern(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);
        if ($result === null) {
            throw new \RuntimeException(sprintf(
                'HTML to text conversion failed on pattern "%s": %s.',
                $pattern,
                preg_last_error_msg(),
            ));
        }

        return $result;
    }
}

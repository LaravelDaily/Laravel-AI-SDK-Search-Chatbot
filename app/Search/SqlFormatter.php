<?php

namespace App\Search;

/**
 * Breaks executed SQL into readable lines for display: one clause per line,
 * one condition per line, and long column lists wrapped at commas.
 */
final class SqlFormatter
{
    private const int MAX_LINE_LENGTH = 80;

    private const string INDENT = '  ';

    private const string COMMA_BREAK = "\x00";

    private const string TOKEN_PATTERN = <<<'REGEX'
        /('(?:[^'\\]|\\.|'')*')|\s+(from|where|group by|having|order by|limit|offset|(?:inner |left |right )?join)\s+|\s+(and|or)\s+|\b(between)\b|(, )/i
        REGEX;

    public static function format(string $sql): string
    {
        $insideBetween = false;

        $marked = preg_replace_callback(self::TOKEN_PATTERN, function (array $match) use (&$insideBetween): string {
            if (filled($match[1] ?? null)) {
                return $match[1];
            }

            if (filled($match[2] ?? null)) {
                return "\n".strtolower($match[2]).' ';
            }

            if (filled($match[3] ?? null)) {
                if ($insideBetween && strtolower($match[3]) === 'and') {
                    $insideBetween = false;

                    return $match[0];
                }

                return "\n".self::INDENT.strtolower($match[3]).' ';
            }

            if (filled($match[4] ?? null)) {
                $insideBetween = true;

                return $match[4];
            }

            return ','.self::COMMA_BREAK;
        }, trim($sql));

        return collect(explode("\n", $marked))
            ->flatMap(fn (string $line): array => self::wrap($line))
            ->implode("\n");
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $line): array
    {
        $lines = [];
        $current = '';

        foreach (explode(self::COMMA_BREAK, $line) as $piece) {
            $candidate = $current === '' ? $piece : $current.' '.$piece;

            if ($current !== '' && mb_strlen($candidate) > self::MAX_LINE_LENGTH) {
                $lines[] = $current;
                $candidate = self::INDENT.$piece;
            }

            $current = $candidate;
        }

        $lines[] = $current;

        return $lines;
    }
}

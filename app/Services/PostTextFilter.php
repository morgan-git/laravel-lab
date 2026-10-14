<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Provider-independent checks for whether a post's text is worth showing:
 * is it mostly non-Latin script, or does it look like non-English text.
 *
 * Each provider passes in its own spam patterns, because those differ by
 * platform ("thanks for the upvotes" on Imgur, "follow for follow" on
 * Tumblr). This class is stateless on purpose, so the container can build
 * it with no configuration and a provider can fall back to
 * `new PostTextFilter()` in tests.
 */
class PostTextFilter
{
    private const array ENGLISH_STOPWORDS = [
        'the', 'and', 'is', 'of', 'to', 'a', 'in', 'for', 'with',
        'on', 'this', 'that', 'it', 'was', 'are', 'my', 'i',
        'you', 'we', 'have', 'has', 'be', 'at', 'from', 'or',
    ];

    /**
     * Below this share of A-Z letters (out of all letters), text counts as
     * non-Latin script.
     */
    private const float MIN_LATIN_LETTER_RATIO = 0.4;

    /**
     * Below this share of common English words, text long enough to judge
     * counts as non-English.
     */
    private const float MIN_ENGLISH_STOPWORD_RATIO = 0.08;

    /**
     * Shorter texts are too small to judge by stopword share.
     */
    private const int MIN_WORDS_FOR_LANGUAGE_CHECK = 6;

    /**
     * @param  list<string>  $ignoredPatterns  Provider-specific regexes; a match means skip.
     */
    public function shouldSkip(string $text, array $ignoredPatterns = []): bool
    {
        if ($text === '') {
            return false;
        }

        foreach ($ignoredPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        if ($this->looksNonLatinScript($text)) {
            return true;
        }

        return $this->looksNonEnglish($text);
    }

    public function looksNonLatinScript(string $text): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text);

        if ($letters === '' || $letters === null) {
            return false;
        }

        $latinLetters = preg_replace('/[^A-Za-z]/', '', $letters);

        return (mb_strlen((string) $latinLetters) / mb_strlen($letters)) < self::MIN_LATIN_LETTER_RATIO;
    }

    public function looksNonEnglish(string $text): bool
    {
        $words = preg_split('/\s+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < self::MIN_WORDS_FOR_LANGUAGE_CHECK) {
            return false;
        }

        $matches = count(array_intersect($words, self::ENGLISH_STOPWORDS));

        return ($matches / count($words)) < self::MIN_ENGLISH_STOPWORD_RATIO;
    }
}

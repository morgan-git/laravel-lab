<?php

use App\Services\PostTextFilter;

beforeEach(function () {
    $this->filter = new PostTextFilter;
});

it('does not treat plain English text as non-Latin script', function () {
    expect($this->filter->looksNonLatinScript('Grilled salmon with lemon'))->toBeFalse();
});

it('does not treat accented Latin text as non-Latin script', function () {
    expect($this->filter->looksNonLatinScript('Creme brulee'))->toBeFalse()
        ->and($this->filter->looksNonLatinScript('Crème brûlée'))->toBeFalse();
});

it('flags text written in a non-Latin script', function () {
    expect($this->filter->looksNonLatinScript('おいしい料理のレシピ'))->toBeTrue();
});

it('ignores text with no letters when checking for non-Latin script', function () {
    expect($this->filter->looksNonLatinScript(''))->toBeFalse()
        ->and($this->filter->looksNonLatinScript('123 😀'))->toBeFalse();
});

it('does not flag English text as non-English', function () {
    expect($this->filter->looksNonEnglish('This is the best pasta recipe in the world'))->toBeFalse();
});

it('flags longer Latin-script text in another language as non-English', function () {
    expect($this->filter->looksNonEnglish('Receta de pasta con tomate y albahaca fresca'))->toBeTrue();
});

it('does not judge the language of text shorter than six words', function () {
    expect($this->filter->looksNonEnglish('Pasta alla carbonara'))->toBeFalse();
});

it('never skips empty text', function () {
    expect($this->filter->shouldSkip(''))->toBeFalse()
        ->and($this->filter->shouldSkip('', ['/anything/']))->toBeFalse();
});

it('skips text that matches a pattern the provider passes in', function () {
    $patterns = ['/thanks?(?:\s+\w+){0,4}?\s+for\s+(the\s+)?(likes?|follows?|reblogs?)/i'];

    expect($this->filter->shouldSkip('Thanks so much for the likes!', $patterns))->toBeTrue();
});

it('applies no spam patterns unless the provider supplies them', function () {
    expect($this->filter->shouldSkip('Thanks so much for the likes'))->toBeFalse();
});

it('skips non-Latin script and non-English text even with no patterns', function () {
    expect($this->filter->shouldSkip('おいしい料理のレシピ'))->toBeTrue()
        ->and($this->filter->shouldSkip('Receta de pasta con tomate y albahaca fresca'))->toBeTrue();
});

it('keeps ordinary English text', function () {
    expect($this->filter->shouldSkip('This is the best pasta recipe in the world', ['/follow for follow/i']))->toBeFalse();
});

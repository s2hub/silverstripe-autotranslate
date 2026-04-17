<?php

namespace S2Hub\AutoTranslate\Tests\Translator;

use S2Hub\AutoTranslate\Translator\Translatable;
use SilverStripe\Dev\TestOnly;

class MockTranslator implements Translatable, TestOnly
{

    /**
     * @inheritDoc
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        //assume we have a json string
        $translations = json_decode($text, true);
        foreach ($translations as $key => $value) {
            $translations[$key] = $value . ' (translated to ' . $targetLocale . ')';
        }

        return json_encode($translations);
    }
}

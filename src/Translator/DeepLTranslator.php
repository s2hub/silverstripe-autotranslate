<?php

namespace Netwerkstatt\FluentExIm\Translator;

use Exception;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use \DeepL\DeepLClient;

class DeepLTranslator implements Translatable
{
    use Extensible;
    use Configurable;
    use Injectable;
    use APITranslator;

    private DeepLClient $client;

    private static $sourceLocales = null;
    private static $targetLocales = null;

    public function __construct(string|null $apiKey = null)
    {
        if ($apiKey === null) {
            $apiKey = $this->getAPIKey("DEEPL_API_KEY");
        }

        $this->client = new DeepLClient($apiKey);

        if (self::$sourceLocales === null) {
            self::$sourceLocales = static::config()->get("source_locales");
        }

        if (self::$targetLocales === null) {
            self::$targetLocales = static::config()->get("target_locales");
        }
    }

    #[\Override]
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        try {
            return $this->client->translateText(
                $text,
                self::$sourceLocales[$sourceLocale],
                self::$targetLocales[$targetLocale]
            );
        }
        catch (Exception $exception) {
            throw new RuntimeException('Translation failed: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}

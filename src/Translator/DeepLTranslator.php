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
            $usage = $this->client->getUsage();
            if ($usage->anyLimitReached()) {
                throw new RuntimeException('Translation failed: DeepL API character limit reached');
            }
            $json = json_decode($text, true);
            foreach ($json as $key => $value) {
                if (is_string($value)) {
                    $json[$key] = $this->client->translateText(
                        $value,
                        self::$sourceLocales[$sourceLocale],
                        self::$targetLocales[$targetLocale],
                        [
                            'tag_handling' => 'html',
                            'tag_handling_version' => 'v2',
                        ]
                    )->text;
                }
            }
            return json_encode($json);
        }
        catch (Exception $exception) {
            throw new RuntimeException('Translation failed: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}

<?php

namespace Netwerkstatt\FluentExIm\Translator;

use Exception;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use \DeepL\DeepLClient;
use SilverStripe\Core\Config\Config;

class DeepLTranslator implements Translatable
{
    use Extensible;
    use Configurable;
    use Injectable;
    use APITranslator;

    private DeepLClient $client;

    private static $locales = null;

    public function __construct(string $apiKey = null)
    {
        if($apiKey == null) {
            $apiKey = $this->getAPIKey("DEEPL_API_KEY");
        }
        
        $this->client = new DeepLClient($apiKey);

        if(Self::$locales == null) {
            Self::$locales = static::config()->get("locale_map");
        }
    }

    protected function getDeepLLocale($slocale) {
        return Self::$locales[$slocale];
    }

    #[\Override]
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        try {
            return $this->client->translateText(
                $text,
                $this->getDeepLLocale($sourceLocale),
                $this->getDeepLLocale($targetLocale)
            );
        }
        catch (Exception $exception) {
            throw new RuntimeException('Translation failed: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
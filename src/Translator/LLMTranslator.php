<?php

namespace S2Hub\AutoTranslate\Translator;

use OpenAI;
use OpenAI\Client;
use Exception;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Environment;
use TractorCow\Fluent\Model\Locale;

class LLMTranslator implements Translatable
{
    use Extensible;
    use Configurable;
    use Injectable;
    use APITranslator;

    /**
     * @config
     */
    private static string $default_profile = 'openai';

    /**
     * @config
     * @deprecated Use profiles[openai][model] instead. Kept for backwards compatibility.
     */
    private static string $gpt_model = 'gpt-4o-mini';

    /**
     * @config
     */
    private static array $profiles = [
        'openai' => [
            'base_uri' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'api_key_env' => 'CHATGPT_API_KEY',
            'headers' => [],
        ],
    ];

    /**
     * @config
     */
    private static string $gpt_command = 'You are a professional translator. Translate the following text to %s language. Please keep the json format intact.';

    private Client $client;
    private string $profileName;

    public function __construct(?string $apiKey = null, ?string $profile = null)
    {
        $this->profileName = $profile ?? self::config()->get('default_profile');
        $profileConfig = self::config()->get('profiles')[$this->profileName] ?? null;

        if (!$profileConfig) {
            throw new RuntimeException("LLM Profile '{$this->profileName}' not configured");
        }

        $envKey = $profileConfig['api_key_env'];
        $actualKey = $apiKey ?? Environment::getEnv($envKey);

        if (!$actualKey) {
            throw new RuntimeException("No API Key found for profile '{$this->profileName}' (environment variable: {$envKey})");
        }

        $client = OpenAI::factory()
            ->withApiKey($actualKey);

        if (!empty($profileConfig['base_uri'])) {
            $client->withBaseUri($profileConfig['base_uri']);
        }

        if (!empty($profileConfig['headers'] && is_array($profileConfig['headers']))) {
            foreach($profileConfig['headers'] as $name => $value) {
                $client->withHttpHeader($name, $value);
            }
        }

        $this->client = $client->make();
    }

    /**
     * Retrieves available models from the API.
     *
     * @return array
     * @throws RuntimeException
     */
    public function getModels(): array
    {
        try {
            return $this->client->models()->list()->toArray();
        } catch (Exception $exception) {
            throw new RuntimeException('Error retrieving models: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Translates the given text to the target language.
     *
     * @param string $text The text to translate.
     * @param string $sourceLocale The source locale/language code.
     * @param string $targetLocale The target locale/language code.
     * @return string Translated text.
     * @throws RuntimeException
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        $profileConfig = self::config()->get('profiles')[$this->profileName];

        // For backwards compatibility: if gpt_model is set, override the profile model for openai
        if ($this->profileName === 'openai' && self::config()->get('gpt_model')) {
            $model = self::config()->get('gpt_model');
        } else {
            $model = $profileConfig['model'] ?? 'gpt-4o-mini';
        }

        try {
            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getGPTCommand($targetLocale)
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ]
            ]);

            // Ensure the response is well-formed
            if (isset($response->choices[0]->message->content)) {
                return $response->choices[0]->message->content;
            }

            throw new RuntimeException('Invalid response structure from API');
        } catch (Exception $exception) {
            throw new RuntimeException('Translation failed: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Generates the translation command for the GPT model.
     *
     * @param string $targetLocale The target locale/language code.
     * @return string The generated command for the GPT model.
     */
    private function getGPTCommand(string $targetLocale): string
    {
        $command = self::config()->get('gpt_command');
        $this->extend('updateGPTCommand', $command, $targetLocale);
        $targetLocale = Locale::getByLocale($targetLocale)->getLongTitle();
        return sprintf($command, $targetLocale);
    }
}

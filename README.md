# Silverstripe AutoTranslate

An extension for silverstripe/fluent to automatically translate content using AI services (LLM providers like OpenAI/ChatGPT, Mistral, Requesty, DeepL).

## Installation
```bash
composer require s2hub/silverstripe-autotranslate
```

### Silverstripe CMS Versions
The current version of this module is for Silverstripe CMS 6.

## Setup

### 1. Add the extension to your classes

```yml
# SiteTree already has fluent applied
SilverStripe\CMS\Model\SiteTree:
  extensions:
    autotranslate: S2Hub\AutoTranslate\Extension\AutoTranslate

My\Namespace\Model\Foo:
  extensions:
    fluent: TractorCow\Fluent\Extension\FluentExtension
    autotranslate: S2Hub\AutoTranslate\Extension\AutoTranslate
```

The `AutoTranslate` extension adds two fields to each locale:
- `IsAutoTranslated` – flag that editors can toggle to mark a translation as manually reviewed
- `LastTranslation` – timestamp of the last auto-translation

### 2. Choose and configure a translation backend

Several backends are available: **LLM** (default, supports OpenAI, Mistral, Requesty, etc.), **DeepL**, and legacy **ChatGPT**.

Set the active backend in your config or via environment variable:

```yml
S2Hub\AutoTranslate\Translator\TranslatableFactory:
  backend: LLM  # or DeepL, ChatGPT (legacy)
```

The environment variable `FLUENT_TRANS_BACKEND` takes precedence over the config value:

```
FLUENT_TRANS_BACKEND=LLM
```

---

## LLM Translator (OpenAI-compatible APIs)

The LLM translator supports **any OpenAI-compatible API endpoint**, including:
- OpenAI (ChatGPT)
- Mistral
- Requesty
- Groq
- Local LLM servers (e.g., Ollama with OpenAI-compatible endpoints)

### Configuration

The LLM translator uses **profiles** to configure different providers. Each profile defines the API endpoint, model, and authentication.

#### Profile Configuration (profiles.yml)

```yml
S2Hub\AutoTranslate\Translator\LLMTranslator:
  default_profile: 'openai'
  profiles:
    openai:
      base_uri: 'https://api.openai.com/v1'
      model: 'gpt-4o-mini'
      api_key_env: 'CHATGPT_API_KEY'
      headers: []
    mistral:
      base_uri: 'https://api.mistral.ai/v1'
      model: 'mistral-large-latest'
      api_key_env: 'MISTRAL_API_KEY'
      headers: []
    requesty:
      base_uri: 'https://router.requesty.ai/v1'
      model: 'google/gemini-2.5-flash'
      api_key_env: 'REQUESTY_API_KEY'
      headers:
        X-Requesty-Customer: 'your-customer-id'
        X-Requesty-Project: 'your-project-id'
```

#### Environment Variables

Set the API keys for each profile:

```
CHATGPT_API_KEY=your-openai-api-key
MISTRAL_API_KEY=your-mistral-api-key
REQUESTY_API_KEY=your-requesty-api-key
```

### Customising the prompt

You can customize the translation prompt:

```yml
S2Hub\AutoTranslate\Translator\LLMTranslator:
  gpt_command: 'You are a professional translator. Translate the following text to %s language. Please keep the json format intact.'
```

Or extend the translator to customize dynamically:

```php
use SilverStripe\Core\Extension;

class CustomLLMTranslatorExtension extends Extension
{
    public function updateGptCommand(&$command, $locale)
    {
        $command = 'Translate the following JSON to ' . $locale . '. Preserve the JSON structure.';
    }
}
```

Register the extension:

```yml
S2Hub\AutoTranslate\Translator\LLMTranslator:
  extensions:
    - CustomLLMTranslatorExtension
```

### Finding available models

In `ssshell` you can list models available for your API key:

```php
$llm = new S2Hub\AutoTranslate\Translator\LLMTranslator();
$llm->getModels();
```

---

## DeepL

### API Key

```
DEEPL_API_KEY=your-api-key
```

### Enabling DeepL

```yml
S2Hub\AutoTranslate\Translator\TranslatableFactory:
  backend: DeepL
```

or via environment variable:

```
FLUENT_TRANS_BACKEND=DeepL
```

### Locale mapping

The module ships with locale mappings for 40+ languages in `_config/locales.yml`. DeepL uses different language codes than SilverStripe (e.g. `en_US` → `EN-US`, `de_DE` → `DE`). Override or extend mappings in your project config if needed:

```yml
S2Hub\AutoTranslate\Translator\DeepLTranslator:
  source_locales:
    de_DE: DE
    en_US: EN
  target_locales:
    de_DE: DE
    en_US: EN-US
    en_GB: EN-GB
```

### Glossaries

DeepL glossaries let you enforce consistent terminology (e.g. brand names, product terms). Create glossaries in your DeepL account, then map their IDs to DeepL target language codes in your config:

```yml
S2Hub\AutoTranslate\Translator\DeepLTranslator:
  glossaries:
    EN-US: 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
    DE: 'yyyyyyyy-yyyy-yyyy-yyyy-yyyyyyyyyyyy'
```

The key must match the **DeepL target language code** (not the SilverStripe locale). The glossary is applied automatically whenever a translation targets that language.

### HTML handling

DeepL receives the SilverStripe field values as a JSON object. If a field value contains HTML markup, the translator automatically enables DeepL's HTML tag handling (`tag_handling: html`) to preserve markup structure. Plain text values are handled separately, with HTML entities decoded back after translation.

Large HTML content is split into chunks at block-level tag boundaries to stay below DeepL's 75 kB request limit.

---

## Running translations

### CLI task

```bash
sake tasks:FluentAIAutoTranslate --do_publish=1
```

#### Parameters

| Parameter | Shortcut | Required | Description |
|---|---|---|---|
| `--do_publish` | `-p` | yes | Set to `1` to publish translated content. Requires `FluentVersionedExtension` on versioned objects. |
| `--force_translation` | | no | Re-translate everything, including content already marked as manually edited (`IsAutoTranslated=false`). |
| `--locale_from` | `-l` | no | Source locale (defaults to the site's default locale). |
| `--locales_to` | `-t` | no | Semicolon-separated list of target locales, e.g. `--locales_to="en_US;es_ES"`. Translates to all locales if omitted. |

```bash
sake tasks:FluentAIAutoTranslate --help
```

### CMS UI

The `AutoTranslate` extension adds an **Auto Translate** button to the CMS actions bar. The button is only shown when editing a record in the **default locale** – it is hidden for translated locales.

Requires the `s2hub/silverstripe-cms-popup` module, which provides the modal infrastructure.

Clicking the button opens a modal with four options:

| Option | Default | Description |
|---|---|---|
| **Target languages** | all non-default locales selected | Select which locales to translate to. |
| **Publish after translation** | on | Publish the translated content immediately. Requires `FluentVersionedExtension` and the source record must be published. |
| **Only translate new content** | on | Skip records where `IsAutoTranslated = false` (manually edited) or whose `LastTranslation` is newer than the source. Uncheck to force re-translation of everything. |
| **Recursive** | off | Also queue all child pages for translation (SiteTree only). |

The modal processes items one by one and displays per-locale feedback (translated, published, skipped, error) for each item as it completes.

**Owned objects** (e.g. Elemental blocks, Links, related media records) are always translated inline as part of the parent record – they do not appear as separate queue items. The *Recursive* option only controls whether child *pages* are added to the queue.

---

## Translation behaviour

- Translation always reads from the **default locale**.
- A record is skipped if `IsAutoTranslated = false` (manual edit detected), unless `force_translation` is set.
- A locale is skipped if its `LastTranslation` timestamp is newer than the source record's, meaning it was manually edited after the last auto-translation, unless `force_translation` is set.
- `IsAutoTranslated` is set to `true` and `LastTranslation` is updated after each successful translation.
- Publishing only works if the object uses `FluentVersionedExtension` instead of `FluentExtension`.

---

## Troubleshooting

### `[Emergency] Uncaught RuntimeException: My\Namespace\HomePage does not have IsAutoTranslated as translatable field`

Your class defines a manual `translate` list. Add the required fields if they're not added automatically:

```yml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    autotranslate: S2Hub\AutoTranslate\Extension\AutoTranslate
  translate:
    - IsAutoTranslated
    - LastTranslation
```

### LLM API configuration error

Ensure your profile is correctly configured in `LLMTranslator` config and the corresponding environment variable is set.

### DeepL API character limit reached

The task throws a `RuntimeException` when the DeepL character quota is exhausted. Check your usage in the DeepL account dashboard and upgrade your plan or wait for the quota reset.

### When adding to SiteConfig, the save button is gone.

Yes, SingleRecordAdmin doesn't add save and delete buttons once you have an extension adding `updateCMSActions()`. For this reason, we added another extension to bring back those buttons. Configure SiteConfig like:

```yaml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    fluent: TractorCow\Fluent\Extension\FluentExtension
    autotranslate: S2Hub\AutoTranslate\Extension\AutoTranslate
    cmsactions: S2Hub\AutoTranslate\Extension\SingleRecordAllCMSActions
```

---

## Thanks to

This module is based on the fluent-export-import-module by [wernerkrauss](https://github.com/wernerkrauss/silverstripe-fluent-export-import/). Thanks to [Nobrainer](https://www.nobrainer.dk/) and [Adiwidjaja Teamworks](https://www.adiwidjaja.com/) for sponsoring this module ❤️.

Thanks to TractorCow and all contributors for the great [fluent module](https://github.com/tractorcow-farm/silverstripe-fluent). And thanks to the folks at Silverstripe for their great work.

## S2-Hub

This module is published and maintained by [S2-Hub](https://www.s2-hub.com). S2-Hub is a non-profit organisation that consists of Silverstripe CMS professionals and agencies around Europe with the goal of setting up a dedicated European professional hub for all things Silverstripe CMS.

See you on next [Stripecon](https://www.stripecon.eu)!

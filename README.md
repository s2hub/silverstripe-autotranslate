# Silverstripe AutoTranslate

An extension for silverstripe/fluent to automatically translate content using AI services (ChatGPT, DeepL).

## Installation
```bash
composer require s2hub/silverstripe-autotranslate
```

### Silverstripe CMS Versions
Version 2 of this module is for Silverstripe CMS 6

## Automatic Translation
You can use the ChatGPT API to translate your content. To do so, you need to set the API key in your .env file:

```
CHATGPT_API_KEY=your-api-key
```

Next add extension to all classes you want to translate:

```yml
# SiteTree has already fluent applied
SilverStripe\CMS\Model\SiteTree:
  extensions:
    autotranslate: S2Hub\AutoTranslate\Extension\AutoTranslate

My\Namespace\Model\Foo:
    extensions:
        fluent: TractorCow\Fluent\Extension\FluentExtension
        autotranslate: S2Hub\AutoTranslate\Extension\AutoTranslate
# if you have configured translations, make sure to add IsAutoTranslated and LastTranslation manually
    translate:
        - IsAutoTranslated
        - LastTranslation
```

The `AutoTranslate` extension adds a flag `IsAutoTranslated` to the class and a field `LastTranslation` to each locale. It's meant to be controlled by an editor, if the translation is correct.


⚠️ Be aware, that some extensions of other modules might add `translated` config to a class. Then you have to add `IsAutoTranslated` and `LastTranslation` to the `translated` config as well.

### Configuring ChatGPT
You can configure the ChatGPT API in your config.yml:

```yml
S2Hub\AutoTranslate\Translator\ChatGPTTranslator:
  gpt_model: gpt-4o-mini
  # %s will be replaced with the target locale
  gpt_command: 'You are a professional translator. Translate the following text to %s language. Please keep the json format intact.'
```

If you need to configure the gpt_command more dynamically, you can use the following code in an Extension to `ChatGPTTranslator`:

```php
public function updateGptCommand(&$command, $locale)
{
    $command = 'You are a professional translator. Translate the following text to ' . $locale . ' language. Please keep the json format intact.';
}
```

#### How to find available GPT models
In ssshell you can run the following commands to find out, which models are currently available for your API key:

```php
$api_key = Environment::getEnv('CHATGPT_API_KEY');
$gpt = new S2Hub\AutoTranslate\Translator\ChatGPTTranslator($api_key);
$gpt->getModels();
```

### fluent-ai-autotranslate task
When everything is configured properly you can run the task `sake tasks:FluentAIAutoTranslate --du_publish=1` to translate all content to the desired locale.

If IsAutoTranslated of LastTranslation is missing in localised fields, the task will throw a RuntimeException.

Notice: the task can only publish translated content, if you use `FluentVersionedExtension` instead of `FluentExtension` on the versioned DataObjects.

#### Parameters:
* `do_publish` (required, shortcut: `p`): If set to 1, the task will publish the translated content.
* `force_translation` (optional): If set to 1, the task will translate all content that is untranslated or marked as previoulsy auto translated, even if it was already translated.
* `locale_from` (optonal, shortcut: `l`): set the source locale for translations
* `locales_to` (optional, shortut: `t`): limit translations to  this locales; expects a semicolon separated list, e.g. `--locales_to="en_US;es_ES"`


See `sake tasks:FluentAIAutoTranslate --help` for all available commands.

## Troubleshooting / FAQ
###  [Emergency] Uncaught RuntimeException: My\Namespace\HomePage does not have IsAutoTranslated as translatable field

It seems, your `SiteTree` (or the main class where you applied the AutoTranslate extension) has defined the `translate` fields manually. While the above config works in many cases, you need to define the translated fields required by this extension manually:

```yml
# SiteTree has already fluent applied
SilverStripe\CMS\Model\SiteTree:
  extensions:
    autotranslate: S2Hub\AutoTranslate\Extension\AutoTranslate
    translate:
        - IsAutoTranslated
        - LastTranslation
```
This should fix the issues.


## Todo
## AI Translation
- [X] ~~documentation how to ask ChatGPT to translate in a correct way~~
- [ ] implement other translation services like DeepL

## Thanks to
Thanks to [Nobrainer](https://www.nobrainer.dk/) and [Adiwidjaja Teamworks](https://www.adiwidjaja.com/) for sponsoring this module ❤️.

Thanks to TractorCow and all contributors for the great [fluent module](https://github.com/tractorcow-farm/silverstripe-fluent). And thanks to the folks at Silverstripe for their great work.

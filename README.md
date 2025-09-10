# Silverstripe Fluent Export / Import

An extension for silvertripe/fluent 
* export and import translations with yml files
* automatically translate content using ChatGPT.

## Installation
```bash
composer require wernerkrauss/silverstripe-fluent-export-import
```

### Silverstripe CMS Versions
Version 2 of this module is for Silverstripe CMS 6

Use Version 1 for Silverstripe 5

### Silverstripe 6 requirements workaround

Currently some required modules are not released as a new SS6 compatible version. If you encounter errors, that some modules are not available in the required Version, try this workaround in your _composer.json_:

```json
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/wernerkrauss/silverstripe-pure-modal.git"
    },
    {
        "type": "git",
        "url": "https://github.com/wernerkrauss/silverstripe-cms-actions.git"
    }
],
"require": {
    "lekoala/silverstripe-pure-modal": "dev-silverstripe6 as 2.0",
    "lekoala/silverstripe-cms-actions": "dev-silverstripe6 as 2.0"
},
```

## Important notice
I take no warranty for any data loss. Please backup your database before importing translations.

There is only a check if the yml file has the same locale as you plan to import. Using the import funcitonality will overwrite the related content in the database.

Please validate the yml files before importing them. Take care to escape apostrophs. 

## Import / Export

### Export
Run `dev/tasks/fluent-export` to export all translations as a zip of yml files.

You can also go to the LocaleAdmin and click on the export button.

Now you can translate the content and import it again.

### Import
Translations are imported in LocaleAdmin. Hit the import button and select the zip or yml file you want to import.

### Configuration

You can configure fields you don't want to export, e.g. SiteTree's URLSegment:

```yml
SilverStripe\CMS\Model\SiteTree:
  translate_ignore:
    - URLSegment
```

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
    autotranslate: Netwerkstatt\FluentExIm\Extension\AutoTranslate

My\Namespace\Model\Foo:
    extensions:
        fluent: TractorCow\Fluent\Extension\FluentExtension
        autotranslate: Netwerkstatt\FluentExIm\Extension\AutoTranslat
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
Netwerkstatt\FluentExIm\Translator\ChatGPTTranslator:
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
$gpt = new Netwerkstatt\FluentExIm\Translator\ChatGPTTranslator($api_key);
$gpt->getModels();
```

### fluent-ai-autotranslate task
When everything is configured properly you can run the task `dev/tasks/fluent-ai-autotranslate do_publish=1` to translate all content to the desired locale.

If IsAutoTranslated of LastTranslation is missing in localised fields, the task will throw a RuntimeException.

Notice: the task can only publish translated content, if you use `FluentVersionedExtension` instead of `FluentExtension` on the versioned DataObjects.

#### Parameters:
* `do_publish` (required): If set to 1, the task will publish the translated content.
* `force_translation` (optional): If set to 1, the task will translate all content that is untranslated or marked as previoulsy auto translated, even if it was already translated.

## Troubleshooting / FAQ
###  [Emergency] Uncaught RuntimeException: My\Namespace\HomePage does not have IsAutoTranslated as translatable field

It seems, your `SiteTree` (or the main class where you applied the AutoTranslate extension) has defined the `translate` fields manually. While the above config works in many cases, you need to define the translated fields required by this extension manually:

```yml
# SiteTree has already fluent applied
SilverStripe\CMS\Model\SiteTree:
  extensions:
    autotranslate: Netwerkstatt\FluentExIm\Extension\AutoTranslate
    translate:
        - IsAutoTranslated
        - LastTranslation
```
This should fix the issues.


## Todo
### File Import/Export
- [X] ~~Export translations to YML~~
- [X] ~~Import translations from YML~~
- [X] ~~Import translations from a zip containing yml files~~
- [ ] other file adapters for import/export like json or xliff
- [X] ~~better UI, e.g. in LocaleAdmin~~
- [ ] use AI on export to translate content; export original and translated content
## AI Translation
- [X] ~~documentation how to ask ChatGPT to translate in a correct way~~
- [ ] implement other translation services like DeepL

## Thanks to
Thanks to [Nobrainer](https://www.nobrainer.dk/) and [Adiwidjaja Teamworks](https://www.adiwidjaja.com/) for sponsoring this module ❤️.

A special thank you to [Lekoala](https://github.com/lekoala) for the support with his CMS-Actions and PureModal modules.

Thanks to TractorCow and all contributors for the great [fluent module](https://github.com/tractorcow-farm/silverstripe-fluent). And thanks to the folks at Silverstripe for their great work.

## Need Help?

If you need some help with your Silverstripe project, feel free to [contact me](mailto:werner.krauss@netwerkstatt.at) ✉️.

See you at next [StripeCon](https://stripecon.eu) 👋

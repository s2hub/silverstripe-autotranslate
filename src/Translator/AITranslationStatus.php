<?php

namespace S2Hub\AutoTranslate\Translator;

use SilverStripe\Model\ModelData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;

class AITranslationStatus extends ModelData
{
    public const STATUS_NOTHINGTOTRANSLATE = 'Nothing to translate';

    public const STATUS_TRANSLATED = 'Translated';

    public const STATUS_PUBLISHED = 'Translated and published';

    public const STATUS_NOTAUTOTRANSLATED = 'Not auto translated';

    public const STATUS_ALREADYTRANSLATED = 'Already translated';

    public const STATUS_ERROR = 'Error';

    public const ERRORMSG_NOTDEFAULTLOCALE = 'Item not in default locale';

    public const ERRORMSG_NOTHINGFOUND = 'No translatable fields found';

    private readonly DataObject $object;

    private array $locales_translated_to = [];

    /**
     * Raw per-locale status strings, before summarisation. One entry per object
     * that reported a result for that locale (e.g. the page itself plus each
     * owned element). Keyed by locale code.
     *
     * @var array<string, string[]>
     */
    private array $localeRaw = [];

    private string $status;

    public function __construct(
        DataObject $object,
        string $status = '',
        private string $message = '',
        private string $source = '',
        private string $aiResponse = '',
        private array|string $data = []
    ) {
        $this->failover = $object;
        $this->object = $object;
        $this->status = $status;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getLocalesTranslatedTo(): array
    {
        return $this->locales_translated_to;
    }

    public function getLocalesTranslatedToForTemplate()
    {
        $data = ArrayList::create();
        foreach ($this->getLocalesTranslatedTo() as $locale => $status) {
            $data->push(ArrayData::create([
                'Locale' => $locale,
                'Status' => $status,
            ]));
        }

        return $data;
    }

    public function addLocale(string $locale, string $status): self
    {
        $this->localeRaw[$locale][] = $status;
        $this->locales_translated_to[$locale] = $this->summarizeLocale($locale);
        return $this;
    }

    /**
     * Merge per-locale statuses from an owned object's status into this one.
     * Each owned-object status contributes one entry per locale; the summary
     * string for the locale is regenerated after each merge.
     */
    public function mergeOwnedStatus(AITranslationStatus $other): self
    {
        foreach ($other->localeRaw as $locale => $statuses) {
            foreach ($statuses as $status) {
                $this->localeRaw[$locale][] = $status;
            }
            $this->locales_translated_to[$locale] = $this->summarizeLocale($locale);
        }
        return $this;
    }

    /**
     * Set the top-level status from the per-locale summaries. Any locale in
     * error → overall Error; otherwise Published if anything was published,
     * Translated if anything was written, else the first remaining status.
     */
    public function aggregateStatus(): self
    {
        if ($this->localeRaw === []) {
            if ($this->status === '') {
                $this->status = self::STATUS_ERROR;
            }
            return $this;
        }

        $hasError = false;
        $hasPublished = false;
        $hasTranslated = false;
        $firstOther = null;

        foreach ($this->localeRaw as $statuses) {
            foreach ($statuses as $s) {
                if (str_starts_with($s, self::STATUS_ERROR)) {
                    $hasError = true;
                } elseif ($s === self::STATUS_PUBLISHED) {
                    $hasPublished = true;
                } elseif ($s === self::STATUS_TRANSLATED) {
                    $hasTranslated = true;
                } elseif ($firstOther === null) {
                    $firstOther = $s;
                }
            }
        }

        $this->status = match (true) {
            $hasError => self::STATUS_ERROR,
            $hasPublished => self::STATUS_PUBLISHED,
            $hasTranslated => self::STATUS_TRANSLATED,
            $firstOther !== null => $firstOther,
            default => self::STATUS_ERROR,
        };
        return $this;
    }

    private function summarizeLocale(string $locale): string
    {
        $statuses = $this->localeRaw[$locale] ?? [];
        if (count($statuses) === 1) {
            return $statuses[0];
        }

        $counts = [
            self::STATUS_PUBLISHED => 0,
            self::STATUS_TRANSLATED => 0,
            self::STATUS_ALREADYTRANSLATED => 0,
            self::STATUS_NOTAUTOTRANSLATED => 0,
            self::STATUS_NOTHINGTOTRANSLATE => 0,
        ];
        $errorCount = 0;
        $firstError = null;

        foreach ($statuses as $s) {
            if (str_starts_with($s, self::STATUS_ERROR)) {
                $errorCount++;
                $firstError ??= $s;
                continue;
            }
            if (array_key_exists($s, $counts)) {
                $counts[$s]++;
            }
        }

        $parts = [];
        foreach ($counts as $label => $n) {
            if ($n > 0) {
                $parts[] = $n . ' × ' . $label;
            }
        }
        if ($errorCount > 0) {
            $parts[] = $errorCount . ' × ' . self::STATUS_ERROR;
        }

        $summary = implode(', ', $parts);
        if ($errorCount > 0) {
            return self::STATUS_ERROR . ': ' . $summary
                . ($firstError !== null ? ' — first: ' . $firstError : '');
        }
        return $summary;
    }

    public function setSource(string $source): AITranslationStatus
    {
        $this->source = $source;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setAiResponse(string $aiResponse): AITranslationStatus
    {
        $this->aiResponse = $aiResponse;
        return $this;
    }

    public function getAiResponse(): string
    {
        return $this->aiResponse;
    }

    public function setData(array|string $data): AITranslationStatus
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): array|string
    {
        return $this->data;
    }

    public function getObject(): DataObject
    {
        return $this->object;
    }

    public static function getLogLevel(string $status): string
    {
        if (str_starts_with($status, self::STATUS_ERROR)) {
            return 'error';
        }

        return match ($status) {
            self::STATUS_ALREADYTRANSLATED,
            self::STATUS_NOTAUTOTRANSLATED,
            self::STATUS_NOTHINGTOTRANSLATE => 'warning',
            default => 'info',
        };
    }
}

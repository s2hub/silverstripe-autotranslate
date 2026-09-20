<?php

namespace Helper;

class DataHelper
{
    /**
     * Helper method to clip invalid text outside the returned josn. e.g. "```json ... ```"
     * or "here is the translation: ..."
     *
     * @param string $input
     * @return string|null
     */
    public static function extractJson(string $input): ?string
    {
        $input = trim($input);

        $objectStart = strpos($input, '{');
        $arrayStart = strpos($input, '[');

        if ($objectStart === false && $arrayStart === false) {
            return null;
        }

        if ($objectStart === false) {
            $start = $arrayStart;
            $end = strrpos($input, ']');
        } elseif ($arrayStart === false) {
            $start = $objectStart;
            $end = strrpos($input, '}');
        } elseif ($objectStart < $arrayStart) {
            $start = $objectStart;
            $end = strrpos($input, '}');
        } else {
            $start = $arrayStart;
            $end = strrpos($input, ']');
        }

        if ($end === false || $end < $start) {
            return null;
        }

        return substr($input, $start, $end - $start + 1);
    }
}

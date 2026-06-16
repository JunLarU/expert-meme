<?php

namespace Whis\Validation\Rules;

use Whis\Storage\File;

class FilesizeRule implements ValidationRule
{
    public function __construct(private float $lessThan)
    {
    }

    public function message(): string
    {
        return "The filesize must be less than {$this->lessThan} bytes.";
    }

    public function isValid($field, $data): bool
    {
        if (! array_key_exists($field, $data)) {
            return false;
        }

        $value = $data[$field];

        if ($value instanceof File) {
            return $value->size() <= $this->lessThan;
        }

        if (is_array($value) && isset($value['size'])) {
            return is_numeric($value['size'])
                && (float) $value['size'] <= $this->lessThan;
        }

        return is_numeric($value)
            && (float) $value <= $this->lessThan;
    }
}
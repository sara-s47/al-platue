<?php

namespace App\Http\Requests\Concerns;

trait NormalizesClockTimes
{
    /**
     * Accept both H:i and H:i:s by stripping seconds before validation.
     *
     * @param  list<string>  $keys  Top-level keys or `list.*.field` paths
     */
    protected function normalizeClockTimes(array $keys): void
    {
        foreach ($keys as $key) {
            if (str_contains($key, '.*.')) {
                [$listKey, $field] = explode('.*.', $key, 2);
                $items = $this->input($listKey);

                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $index => $item) {
                    if (! is_array($item) || ! array_key_exists($field, $item)) {
                        continue;
                    }

                    $items[$index][$field] = $this->stripClockSeconds($item[$field]);
                }

                $this->merge([$listKey => $items]);

                continue;
            }

            if ($this->exists($key)) {
                $this->merge([$key => $this->stripClockSeconds($this->input($key))]);
            }
        }
    }

    protected function stripClockSeconds(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        return $value;
    }
}

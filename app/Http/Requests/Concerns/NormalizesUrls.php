<?php

namespace App\Http\Requests\Concerns;

trait NormalizesUrls
{
    /**
     * Percent-encode characters browsers tolerate but RFC 3986 forbids (e.g. `|`, spaces),
     * so links pasted from the address bar pass the `url` rule.
     *
     * @param  list<string>  $keys
     */
    protected function normalizeUrls(array $keys): void
    {
        foreach ($keys as $key) {
            $value = $this->input($key);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value === '') {
                $this->merge([$key => null]);

                continue;
            }

            $this->merge([$key => preg_replace_callback(
                "/[^A-Za-z0-9\-._~:\/?#\[\]@!$&'()*+,;=%]/u",
                fn (array $match) => rawurlencode($match[0]),
                $value
            )]);
        }
    }
}

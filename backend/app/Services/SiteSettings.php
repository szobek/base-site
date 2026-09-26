<?php

namespace App\Services;

use App\Models\Setting;

class SiteSettings
{
    /**
     * @return array{site_name: string, contact_address: string, contact_phone: string, contact_email: string}
     */
    public function all(): array
    {
        return [
            'site_name' => $this->get('site_name'),
            'contact_address' => $this->get('contact_address'),
            'contact_phone' => $this->get('contact_phone'),
            'contact_email' => $this->get('contact_email'),
        ];
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array{site_name: string, contact_address: string, contact_phone: string, contact_email: string}
     */
    public function update(array $values): array
    {
        foreach (['site_name', 'contact_address', 'contact_phone', 'contact_email'] as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) ($values[$key] ?? '')],
            );
        }

        return $this->all();
    }

    public function get(string $key): string
    {
        $stored = Setting::query()->whereKey($key)->value('value');

        if (is_string($stored)) {
            return $stored;
        }

        return (string) config('settings.'.$key, '');
    }
}

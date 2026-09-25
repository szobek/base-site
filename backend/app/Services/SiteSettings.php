<?php

namespace App\Services;

use App\Models\Setting;

class SiteSettings
{
    /**
     * @return array{site_name: string}
     */
    public function all(): array
    {
        return [
            'site_name' => $this->get('site_name'),
        ];
    }

    /**
     * @return array{site_name: string}
     */
    public function update(string $siteName): array
    {
        Setting::query()->updateOrCreate(
            ['key' => 'site_name'],
            ['value' => $siteName],
        );

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

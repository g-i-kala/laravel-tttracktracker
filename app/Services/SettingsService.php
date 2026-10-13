<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Reads and writes app settings (key/value). Secrets are encrypted with APP_KEY.
 */
class SettingsService
{
    public function get(string $key): ?string
    {
        return Setting::find($key)?->value;
    }

    public function put(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->forget($key);

            return;
        }

        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Decrypted secret, or null when it is missing or cannot be decrypted (e.g. APP_KEY changed).
     */
    public function getSecret(string $key): ?string
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function putSecret(string $key, string $value): void
    {
        $this->put($key, Crypt::encryptString($value));
    }

    public function forget(string $key): void
    {
        Setting::whereKey($key)->delete();
    }
}

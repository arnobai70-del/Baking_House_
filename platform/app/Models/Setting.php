<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable key/value settings (JSON values). Read with Setting::get(), memoised per request.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const LANGUAGES_ENABLED = 'storefront.languages_enabled';

    public const LANGUAGE_DEFAULT = 'storefront.language_default';

    public const SUPPORTED_LANGUAGES = ['en' => 'English', 'bn' => 'বাংলা'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $memo = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$memo = null);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$memo ??= self::query()->pluck('value', 'key')->all();

        return array_key_exists($key, self::$memo) ? self::$memo[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        self::$memo = null;
    }

    public static function flushMemo(): void
    {
        self::$memo = null;
    }

    /**
     * @return list<string>
     */
    public static function enabledLanguages(): array
    {
        $enabled = array_values(array_intersect((array) self::get(self::LANGUAGES_ENABLED, ['en']), array_keys(self::SUPPORTED_LANGUAGES)));

        return $enabled === [] ? ['en'] : $enabled;
    }

    public static function defaultLanguage(): string
    {
        $default = (string) self::get(self::LANGUAGE_DEFAULT, 'en');

        return in_array($default, self::enabledLanguages(), true) ? $default : self::enabledLanguages()[0];
    }
}

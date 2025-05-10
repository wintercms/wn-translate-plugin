<?php

namespace Winter\Translate\Models;

use App;
use ApplicationException;
use Cache;
use Config;
use Lang;
use Model;
use ValidationException;

/**
 * Locale Model
 */
class Locale extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \Winter\Storm\Database\Traits\Sortable;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_translate_locales';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'code' => 'required',
        'name' => 'required',
    ];

    /**
     * @var array Attributes that are mass assignable
     */
    public $fillable = [
        'code',
        'name',
        'is_enabled',
    ];

    public $timestamps = false;

    /**
     * @var array Object cache of self, by code.
     */
    protected static $cacheByCode = [];

    /**
     * @var array A cache of enabled locales.
     */
    protected static $cacheListEnabled;

    /**
     * @var array A cache of available locales.
     */
    protected static $cacheListAvailable;

    /**
     * @var self Default locale cache.
     */
    protected static $defaultLocale;

    public function afterCreate()
    {
        if ($this->is_default) {
            $this->makeDefault();
        }
    }

    public function afterSave()
    {
        self::clearCache();
    }

    public function beforeDelete()
    {
        if ($this->is_default) {
            throw new ApplicationException(Lang::get('winter.translate::lang.locale.delete_default', ['locale'=>$this->name]));
        }
    }

    public function beforeUpdate()
    {
        if ($this->isDirty('is_default')) {
            $this->makeDefault();

            if (!$this->is_default) {
                throw new ValidationException(['is_default' => Lang::get('winter.translate::lang.locale.unset_default', ['locale'=>$this->name])]);
            }
        }
    }

    /**
     * Makes this model the default
     * @return void
     */
    public function makeDefault()
    {
        if (!$this->is_enabled) {
            throw new ValidationException(['is_enabled' => Lang::get('winter.translate::lang.locale.disabled_default', ['locale'=>$this->name])]);
        }

        $this->newQuery()->where('id', $this->id)->update(['is_default' => true]);
        $this->newQuery()->where('id', '<>', $this->id)->update(['is_default' => false]);

        self::$defaultLocale = null;
        Cache::forget('winter.translate.defaultLocale');
    }

    /**
     * Returns the default locale defined.
     * @return self
     */
    public static function getDefault()
    {
        if (self::$defaultLocale !== null) {
            return self::$defaultLocale;
        }

        $forceDefault = Config::get('winter.translate::forceDefaultLocale');
        if (!$forceDefault && !App::hasDatabase()) {
            // If a database is not available, the default locale is always the app locale
            $forceDefault = Config::get('app.locale');
        }

        if ($forceDefault) {
            $locale = new self;
            $locale->name = $locale->code = $forceDefault;
            $locale->is_default = $locale->is_enabled = true;
            return self::$defaultLocale = $locale;
        }

        return self::$defaultLocale = self::where('is_default', true)
            ->remember(1440, 'winter.translate.defaultLocale')
            ->first()
        ;
    }

    /**
     * Locate a locale table by its code, cached.
     * @param  string $code
     * @return Model
     */
    public static function findByCode($code = null)
    {
        if (!$code) {
            return null;
        }

        if (isset(self::$cacheByCode[$code])) {
            return self::$cacheByCode[$code];
        }

        return self::$cacheByCode[$code] = self::whereCode($code)->first();
    }

    /**
     * Scope for checking if model is enabled
     * @param  Builder $query
     * @return Builder
     */
    public function scopeIsEnabled($query)
    {
        return $query
            ->whereNotNull('is_enabled')
            ->where('is_enabled', true)
        ;
    }

    /**
     * Scope for ordering the locales
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrder($query)
    {
        return $query
            ->orderBy('sort_order', 'asc')
        ;
    }

    /**
     * Returns true if there are at least 2 locales available.
     * @return boolean
     */
    public static function isAvailable()
    {
        return count(self::listAvailable()) > 1;
    }

    /**
     * Lists available locales, used on the back-end.
     * @return array
     */
    public static function listAvailable()
    {
        if (self::$cacheListAvailable) {
            return self::$cacheListAvailable;
        }

        return self::$cacheListAvailable = self::order()->pluck('name', 'code')->all();
    }

    /**
     * Lists the enabled locales, used on the front-end.
     * @return array
     */
    public static function listEnabled()
    {
        if (self::$cacheListEnabled) {
            return self::$cacheListEnabled;
        }

        $expiresAt = now()->addMinutes(1440);
        $isEnabled = Cache::remember('winter.translate.locales', $expiresAt, function() {
            return self::isEnabled()->order()->pluck('name', 'code')->all();
        });

        return self::$cacheListEnabled = $isEnabled;
    }

    /**
     * Returns true if the supplied locale is valid.
     * @return boolean
     */
    public static function isValid($locale)
    {
        $languages = array_keys(Locale::listEnabled());

        return in_array($locale, $languages);
    }

    /**
     * Clears all cache keys used by this model
     * @return void
     */
    public static function clearCache()
    {
        self::$cacheListAvailable = null;
        self::$cacheListEnabled = null;
        Cache::forget('winter.translate.locales');
        Cache::forget('winter.translate.defaultLocale');
    }
}

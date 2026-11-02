<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Cookie;

class LocalesServiceProvider extends ServiceProvider
{
    //TODO: Remove this as intl has become a mandatory requirement
    public static $languageCodes = [
        "aa" => "Afar",
        "ab" => "Abkhazian",
        "ae" => "Avestan",
        "af" => "Afrikaans",
        "ak" => "Akan",
        "am" => "Amharic",
        "an" => "Aragonese",
        "ar" => "Arabic",
        "as" => "Assamese",
        "av" => "Avaric",
        "ay" => "Aymara",
        "az" => "Azerbaijani",
        "ba" => "Bashkir",
        "be" => "Belarusian",
        "bg" => "Bulgarian",
        "bh" => "Bihari",
        "bi" => "Bislama",
        "bm" => "Bambara",
        "bn" => "Bengali",
        "bo" => "Tibetan",
        "br" => "Breton",
        "bs" => "Bosnian",
        "ca" => "Catalan",
        "ce" => "Chechen",
        "ch" => "Chamorro",
        "co" => "Corsican",
        "cr" => "Cree",
        "cs" => "Czech",
        "cu" => "Church Slavic",
        "cv" => "Chuvash",
        "cy" => "Welsh",
        "da" => "Danish",
        "de" => "German",
        "dv" => "Divehi",
        "dz" => "Dzongkha",
        "ee" => "Ewe",
        "el" => "Greek",
        "en" => "English",
        "eo" => "Esperanto",
        "es" => "Spanish",
        'es-mx' => 'Spanish (Mexico)',
        "et" => "Estonian",
        "eu" => "Basque",
        "fa" => "Persian",
        "ff" => "Fulah",
        "fi" => "Finnish",
        "fj" => "Fijian",
        "fo" => "Faroese",
        "fr" => "French",
        "fy" => "Western Frisian",
        "ga" => "Irish",
        "gd" => "Scottish Gaelic",
        "gl" => "Galician",
        "gn" => "Guarani",
        "gu" => "Gujarati",
        "gv" => "Manx",
        "ha" => "Hausa",
        "he" => "Hebrew",
        "hi" => "Hindi",
        "ho" => "Hiri Motu",
        "hr" => "Croatian",
        "ht" => "Haitian",
        "hu" => "Hungarian",
        "hy" => "Armenian",
        "hz" => "Herero",
        "ia" => "Interlingua (International Auxiliary Language Association)",
        "id" => "Indonesian",
        "ie" => "Interlingue",
        "ig" => "Igbo",
        "ii" => "Sichuan Yi",
        "ik" => "Inupiaq",
        "io" => "Ido",
        "is" => "Icelandic",
        "it" => "Italian",
        "iu" => "Inuktitut",
        "ja" => "Japanese",
        "jv" => "Javanese",
        "ka" => "Georgian",
        "kg" => "Kongo",
        "ki" => "Kikuyu",
        "kj" => "Kwanyama",
        "kk" => "Kazakh",
        "kl" => "Kalaallisut",
        "km" => "Khmer",
        "kn" => "Kannada",
        "ko" => "Korean",
        "kr" => "Kanuri",
        "ks" => "Kashmiri",
        "ku" => "Kurdish",
        "kv" => "Komi",
        "kw" => "Cornish",
        "ky" => "Kirghiz",
        "la" => "Latin",
        "lb" => "Luxembourgish",
        "lg" => "Ganda",
        "li" => "Limburgish",
        "ln" => "Lingala",
        "lo" => "Lao",
        "lt" => "Lithuanian",
        "lu" => "Luba-Katanga",
        "lv" => "Latvian",
        "mg" => "Malagasy",
        "mh" => "Marshallese",
        "mi" => "Maori",
        "mk" => "Macedonian",
        "ml" => "Malayalam",
        "mn" => "Mongolian",
        "mr" => "Marathi",
        "ms" => "Malay",
        "mt" => "Maltese",
        "my" => "Burmese",
        "na" => "Nauru",
        "nb" => "Norwegian Bokmal",
        "nd" => "North Ndebele",
        "ne" => "Nepali",
        "ng" => "Ndonga",
        "nl" => "Dutch",
        "nn" => "Norwegian Nynorsk",
        "no" => "Norwegian",
        "nr" => "South Ndebele",
        "nv" => "Navajo",
        "ny" => "Chichewa",
        "oc" => "Occitan",
        "oj" => "Ojibwa",
        "om" => "Oromo",
        "or" => "Oriya",
        "os" => "Ossetian",
        "pa" => "Panjabi",
        "pi" => "Pali",
        "pl" => "Polish",
        "ps" => "Pashto",
        "pt" => "Portuguese",
        "qu" => "Quechua",
        "rm" => "Raeto-Romance",
        "rn" => "Kirundi",
        "ro" => "Romanian",
        "ru" => "Russian",
        "rw" => "Kinyarwanda",
        "sa" => "Sanskrit",
        "sc" => "Sardinian",
        "sd" => "Sindhi",
        "se" => "Northern Sami",
        "sg" => "Sango",
        "si" => "Sinhala",
        "sk" => "Slovak",
        "sl" => "Slovenian",
        "sm" => "Samoan",
        "sn" => "Shona",
        "so" => "Somali",
        "sq" => "Albanian",
        "sr" => "Serbian",
        "ss" => "Swati",
        "st" => "Southern Sotho",
        "su" => "Sundanese",
        "sv" => "Swedish",
        "sw" => "Swahili",
        "ta" => "Tamil",
        "te" => "Telugu",
        "tg" => "Tajik",
        "th" => "Thai",
        "ti" => "Tigrinya",
        "tk" => "Turkmen",
        "tl" => "Tagalog",
        "tn" => "Tswana",
        "to" => "Tonga",
        "tr" => "Turkish",
        "ts" => "Tsonga",
        "tt" => "Tatar",
        "tw" => "Twi",
        "ty" => "Tahitian",
        "ug" => "Uighur",
        "uk" => "Ukrainian",
        "ur" => "Urdu",
        "uz" => "Uzbek",
        "ve" => "Venda",
        "vi" => "Vietnamese",
        "vo" => "Volapuk",
        "wa" => "Walloon",
        "wo" => "Wolof",
        "xh" => "Xhosa",
        "yi" => "Yiddish",
        "yo" => "Yoruba",
        "za" => "Zhuang",
        "zh" => "Chinese",
        "zh-CHS" => "Chinese (Simplified)",
        "zh-Hans" => "Chinese (Simplified)",
        "zh-CN" => "Chinese (Simplified)",
        "zh-SG" => "Chinese (Simplified)",
        "zh-CHT" => "Chinese (Traditional)",
        "zh-Hant" => "Chinese (Traditional)",
        "zh-HK" => "Chinese (Traditional)",
        "zh-MO" => "Chinese (Traditional)",
        "zh-TW" => "Chinese (Traditional)",
        "zu" => "Zulu",
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    public static function getAvailableLanguages() {
        $languageFiles = array_filter(scandir(app()->langPath()), function ($v) {
            return is_int(strpos($v, '.json'));
        });
        $languageFiles = array_map(function ($v) {
            if(is_int(strpos($v, '.json'))){
                return str_replace('.json', '', $v);
            }
        }, $languageFiles);
        return $languageFiles;
    }

    public static function getLanguageName($localeCode) {
        if(extension_loaded('intl')){
            return \Locale::getDisplayLanguage($localeCode, $localeCode);
        }
        else{
            if(isset(self::$languageCodes[$localeCode])){
                return self::$languageCodes[$localeCode];
            }
            else{
                return false;
            }
        }
    }

    public static function getUserPreferredLocale($request)
    {

        if (!InstallerServiceProvider::checkIfInstalled()) {
            return Config::get('app.locale');
        }

        if (!Session::has('locale')) {
            if (Cookie::get('app_locale')) {
                return Cookie::get('app_locale');
            }
            if (getSetting('site.use_browser_language_if_available')) {
                $preferredLang = self::getPreferredLocale($request->server('HTTP_ACCEPT_LANGUAGE'));
                if ($preferredLang) {
                    return $preferredLang; // If user has missing locale setting - default on site setting
                }
            }
            return getSetting('site.default_site_language');
        }

        if (isset(Auth::user()->settings['locale'])) {
            return Auth::user()->settings['locale'];
        } else {
            if (Cookie::get('app_locale')) {
                return Cookie::get('app_locale');
            } else {
                if (getSetting('site.use_browser_language_if_available')) {
                    $preferredLang = self::getPreferredLocale($request->server('HTTP_ACCEPT_LANGUAGE'));
                    if ($preferredLang) {
                        return $preferredLang; // If user has missing locale setting - we default on site setting on the LocaleSetter
                    } else {
                        return getSetting('site.default_site_language');
                    }
                }
                return getSetting('site.default_site_language');
            }
        }
    }

    public static function getPreferredLocale($languageString)
    {
        $preferredLang = $languageString;

        // Extract the first language from the list (handling q-values)
        if ($preferredLang) {
            $preferredLang = explode(',', $preferredLang)[0]; // First language
            $preferredLang = explode(';', $preferredLang)[0]; // Remove q-value
            $preferredLang = explode('-', $preferredLang)[0]; // Extract base language

            // Validate extracted language code (ISO 639-1: only 2-letter lowercase codes)
            if (!preg_match('/^[a-z]{2}$/', $preferredLang)) {
                $preferredLang = null; // Set to null if invalid
            }
        }
        return $preferredLang;

    }

    /**
     * Locale setter helper.
     * @param $code
     */
    public static function setLocale($code) {
        App::setLocale($code);
        Session::put('locale', $code);
    }

    public static function getTranslationsCacheKey(string $locale): string
    {
        return 'app_translations.'.$locale;
    }

    public static function getCachedTranslations(string $locale, string $langFilePath): string
    {
        $cacheKey = self::getTranslationsCacheKey($locale);

        $resolver = function () use ($langFilePath) {
            return file_get_contents($langFilePath);
        };

        if (App::environment('production')) {
            return Cache::rememberForever($cacheKey, $resolver);
        }

        return Cache::remember($cacheKey, now()->addMinutes(5), $resolver);
    }

    /**
     * Same locales, but fallback locale first.
     */
    public static function getAvailableLanguagesFallbackFirst(?string $fallback = null): array
    {
        $fallback ??= config('app.fallback_locale', 'en');

        $locales = self::getAvailableLanguages();

        // stable ordering: fallback first, then alphabetical
        usort($locales, function ($a, $b) use ($fallback) {
            if ($a === $fallback) return -1;
            if ($b === $fallback) return 1;
            return $a <=> $b;
        });

        return $locales;
    }

    /**
     * For Filament Select options:
     * ["en" => "EN", "ro" => "RO"] or use readable labels if you want.
     */
    public static function getAvailableLanguageOptions(bool $useNativeName = false): array
    {
        $options = [];

        foreach (self::getAvailableLanguages() as $locale) {
            if ($useNativeName) {
                // Uses your existing helper (intl display name if available)
                $name = self::getLanguageName($locale);
                $options[$locale] = $name ? $name : strtoupper($locale);
            } else {
                $options[$locale] = strtoupper($locale);
            }
        }

        return $options;
    }
}

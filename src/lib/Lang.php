<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <https://github.com/d4w33d/AuthBox>.
 */

// =============================================================================

namespace AuthBox\Lib;

// -----------------------------------------------------------------------------

use AuthBox\Lib\Application;
use AuthBox;

// =============================================================================

class Lang
{

    // =========================================================================

    private static $current;
    private static $name;
    private static $translations;

    // -------------------------------------------------------------------------

    public static function initialize()
    {
        if (array_key_exists('lang', $_GET) && ($lang = $_GET['lang'])) {
            if (self::isLangExists($lang)) {
                Session::set('language', $lang);
            }

            AuthBox::redirect(AuthBox::makeUrl('index'));
            return;
        }

        self::$current = Session::get('language',
            AuthBox::cfg('i18n.defaultLanguage'));

        self::load();
    }

    public static function isLangExists($lang)
    {
        return is_file(self::getLangFile($lang));
    }

    private static function getLangFile($lang)
    {
        return AUTHBOX_USER_DIR . DS . 'i18n' . DS . $lang . '.xml';
    }

    private static function load()
    {
        self::$translations = [];

        $xml = simplexml_load_file(self::getLangFile(self::$current));

        self::$name = (string) $xml->translations->name;

        foreach ($xml->translations->trans as $trans) {
            self::$translations[(string) $trans->key] = (string) $trans->value;
        }
    }

    public static function getCurrent()
    {
        return self::$current;
    }

    public static function getName()
    {
        return self::$name;
    }

    public static function getLanguages()
    {
        $languages = array_map(function($f) {
            $xml = simplexml_load_file($f);
            $code = (string) $xml->translations->attributes()->language;

            $url = AUTHBOX_REQUEST_URL;
            $url .= (strpos($url, '?') !== false ? '&' : '?')
                . 'lang=' . $code;

            return (object) [
                'name' => (string) $xml->translations->name,
                'code' => $code,
                'url' => $url,
            ];
        }, glob(AUTHBOX_USER_DIR . DS . 'i18n' . DS . '*.xml'));

        sort($languages);

        return $languages;
    }

    public static function t($key, array $vars = array())
    {
        $str = $key;

        if (array_key_exists($key, self::$translations)) {
            $str = self::$translations[$key];
        }

        foreach ($vars as $k => $v) {
            $str = str_replace('{' . $k . '}', $v, $str);
        }

        return $str;
    }

}

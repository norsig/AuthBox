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

namespace AuthBox\Lib;

// =============================================================================

class Session
{

    // =========================================================================

    public static function setup()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();

        if (AUTHBOX_SESSION_NAME !== null) {
            session_name(AUTHBOX_SESSION_NAME);
        }
    }

    public static function get($key, $default = null)
    {
        $key = self::getRealKey($key);

        if (array_key_exists($key, $_SESSION)) {
            return $_SESSION[$key];
        }

        return $default;
    }

    public static function set($key, $value)
    {
        $_SESSION[self::getRealKey($key)] = $value;
    }

    public static function push($key, $value)
    {
        $arr = (array) self::get($key, []);
        $arr[] = $value;
        self::set($key, $arr);
    }

    public static function delete($key)
    {
        $value = self::get($key);
        $key = self::getRealKey($key);

        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }

        return $value;
    }

    public static function getRealKey($key)
    {
        return AUTHBOX_SESSION_PREFIX . '_' . $key;
    }

}

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

use AuthBox\Lib\Session;
use AuthBox\Lib\Lang;
use AuthBox\Lib\BadRoleException;
use AuthBox\Models\User;
use AuthBox\Models\Token;

// =============================================================================

defined($k = 'DS') || define($k, DIRECTORY_SEPARATOR);

// =============================================================================

class AuthBox
{

    // =========================================================================

    const USER_SESSION_KEY = 'user_id';

    // =========================================================================

    private static $config;

    // -------------------------------------------------------------------------

    /**
     * AuthBox object initialization
     * @return null
     */
    public static function initialize()
    {
        // Check if AuthBox was already initialized
        if (self::$config !== null) {
            return;
        }

        // Load bootstrap script
        require_once __DIR__ . DS . 'src' . DS . 'bootstrap.php';

        // Do nothing if AuthBox is not installed
        if (!self::isInstalled()) {
            return;
        }

        // Load configuration
        self::$config = require self::getConfigFile();

        // Initialize session
        Session::setup();

        // Merge config with session config
        self::$config = array_merge_recursive(self::$config,
            Session::get('config', []));

        // Initialize lang
        Lang::initialize();

        // Initialize database
        self::initializeDatabase(self::cfg('database'));
    }

    public static function isInstalled()
    {
        return is_file(self::getConfigFile());
    }

    public static function getConfigFile()
    {
        return AUTHBOX_ROOT_DIR . DS . 'user' . DS . 'config.php';
    }

    public static function getDatabaseDsn(array $cfg = null)
    {
        if ($cfg === null) {
            $cfg = self::cfg('database');
        }

        if ($cfg['dbms'] === 'sqlite') {
            return 'sqlite:' . $cfg['filepath'];
        } else if (in_array($cfg['dbms'], ['mysql', 'pgsql'])) {
            return $cfg['dbms']
                . ':host=' . $cfg['host']
                . ';port=' . $cfg['port']
                . ';dbname=' . $cfg['dbname'];
        }
    }

    public static function initializeDatabase(array $cfg = null)
    {
        if ($cfg === null) {
            $cfg = self::cfg('database');
        }

        ORM::configure(self::getDatabaseDsn($cfg));

        if ($cfg['username'] !== null)
            ORM::configure('username', $cfg['username']);
        if ($cfg['password'] !== null)
            ORM::configure('password', $cfg['password']);
    }

    public static function executeDatabaseAction($action)
    {
        if ($action === 'reinstall') {
            self::executeDatabaseAction('uninstall');
            self::executeDatabaseAction('install');
            return;
        }

        $db = ORM::get_db();

        $models = [
            'user' => new User(),
            'token' => new Token(),
        ];

        $tables = $models;
        array_walk($tables, function(&$value, $key)
        {
            $value = $value->getTableName();
        });

        $cmd = require AUTHBOX_SRC_DIR . DS . 'sql' . DS . $action . '.php';

        foreach ($cmd[self::cfg('database.dbms')]() as $sql) {
            $db->exec($sql);
        }
    }

    /**
     * Get configuration value
     * @param string $key Dotted chain of configuration keys
     * @param mixed $default Default value if entry doesn't exists
     * @return mixed
     */
    public static function cfg($key, $default = null)
    {
        $value = self::$config;
        foreach (explode('.', $key) as $col) {
            if (!is_array($value) || !array_key_exists($col, $value)) {
                return $default;
            }

            $value = $value[$col];
        }

        return $value;
    }

    /**
     * Redirect to given URL and exit script
     * @param string $url
     * @param integer $code HTTP code of redirection
     * @return null
     */
    public static function redirect($url, $code = 302)
    {
        if (!$url || $url{0} !== '/') {
            $url = AUTHBOX_BASE_URL . '/' . $url;
        }

        header('Location: ' . $url, true, $code);
        exit;
    }

    /**
     * Returns a formatted URL from route name
     * @param string $name Route name
     * @param array $vars Array of vars to pass as query string
     * @param boolean $withHost Returns current hostname with scheme in URL
     * @return string
     */
    public static function makeUrl($name, array $vars = array(), $withHost = false)
    {
        $routes = self::cfg('routes');
        if (!array_key_exists($name, $routes)) {
            return;
        }

        $url = $routes[$name];

        if (!$url || $url{0} !== '/') {
            $url = AUTHBOX_BASE_URL . '/' . $url;
        }

        if ($query = http_build_query($vars)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . $query;
        }

        if ($withHost) {
            $url = AUTHBOX_HOST . $url;
        }

        return $url;
    }

    /**
     * Returns the URL to homepage as given in the configuration
     * @param array $vars Array of vars to pass as query string
     * @return string
     */
    public static function homepageUrl(array $vars = array())
    {
        $url = self::cfg('behaviour.homepageUrl');

        if (strpos($url, '://') === false) {
            if (!$url || $url{0} !== '/') {
                $url = AUTHBOX_BASE_URL . '/' . $url;
            }

            if (preg_match('/\/\.\.?$/', $url)) {
                $url .= '/';
            }

            $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
            for($n=1; $n>0; $url=preg_replace($re, '/', $url, -1, $n)) {}

            $url = AUTHBOX_HOST . $url;
        }

        if ($query = http_build_query($vars)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . $query;
        }

        return $url;
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATION METHODS

    /**
     * Execute login, returns false if $user was not found
     * @param mixed $user User object or user id
     * @param boolean $remember Login in long-life cookie instead of session
     * @return boolean
     */
    public static function login($user, $remember = false)
    {
        if (!($user instanceof User)) {
            if (!($user = User::find($user))) {
                return false;
            }
        }

        if (!$remember) {
            Session::set(self::USER_SESSION_KEY, $user->id);
            return true;
        }

        setcookie(
            self::cfg('cookies.remember.name'),
            http_build_query([
                'token' => $user->getRememberToken()->key,
            ]),
            time() + self::cfg('behaviour.rememberDuration'),
            self::cfg('cookies.remember.path'),
            self::cfg('cookies.remember.domain'),
            false,
            false
        );

        return true;
    }

    /**
     * Logout user by cleaning session and cookie
     * @return null
     */
    public static function logout()
    {
        Session::delete(self::USER_SESSION_KEY);

        $cookieName = self::cfg('cookies.remember.name');

        setcookie(
            $cookieName,
            null,
            -1,
            self::cfg('cookies.remember.path'),
            self::cfg('cookies.remember.domain'),
            false,
            false
        );

        if (array_key_exists($cookieName, $_COOKIE)) {
            unset($_COOKIE[$cookieName]);
        }
    }

    // -------------------------------------------------------------------------
    // USER METHODS

    /**
     * Returns authentication (register and login) page URL
     * @param string $afterUrl Where the user will be redirected after auth
     * @return null
     */
    public static function getAuthUrl($afterUrl = null)
    {
        return self::makeUrl('index', ['after' => $afterUrl]);
    }

    /**
     * Redirect to authentication page
     * @param string $afterUrl
     * @return null
     */
    public static function redirectToAuth($afterUrl = null)
    {
        self::redirect(self::getAuthUrl());
    }

    /**
     * Returns register page URL
     * @param string $afterUrl Where the user will be redirected after register
     * @return null
     */
    public static function getRegisterUrl($afterUrl = null)
    {
        return self::makeUrl('register', ['after' => $afterUrl]);
    }

    /**
     * Redirect to register page
     * @param string $afterUrl
     * @return null
     */
    public static function redirectToRegister($afterUrl = null)
    {
        self::redirect(self::getRegisterUrl());
    }

    /**
     * Returns login page URL
     * @param string $afterUrl Where the user will be redirected after login
     * @return null
     */
    public static function getLoginUrl($afterUrl = null)
    {
        return self::makeUrl('login', ['after' => $afterUrl]);
    }

    /**
     * Redirect to login page
     * @param string $afterUrl
     * @return null
     */
    public static function redirectToLogin($afterUrl = null)
    {
        self::redirect(self::getLoginUrl());
    }

    /**
     * Returns logout URL
     * @param string $afterUrl Where the user will be redirected after logout
     * @return null
     */
    public static function getLogoutUrl($afterUrl = null)
    {
        return self::makeUrl('logout', ['after' => $afterUrl]);
    }

    /**
     * Redirect to logout
     * @param string $afterUrl
     * @return null
     */
    public static function redirectToLogout($afterUrl = null)
    {
        self::redirect(self::getLogoutUrl());
    }

    /**
     * Returns profile page URL
     * @return null
     */
    public static function getProfileUrl()
    {
        return self::makeUrl('profile');
    }

    /**
     * Redirect to authentication page
     * @return null
     */
    public static function redirectToProfile()
    {
        self::redirect(self::getProfileUrl());
    }

    // -------------------------------------------------------------------------

    /**
     * Returns logged in user or null
     * @param boolean $onlyActive Restrict login to active users only
     * @return User
     */
    public static function getUser($onlyActive = true)
    {
        $cookieName = self::cfg('cookies.remember.name');
        $user = null;

        if (!($userId = Session::get(self::USER_SESSION_KEY))) {
            if (!array_key_exists($cookieName, $_COOKIE)
                || !$_COOKIE[$cookieName]) {
                return;
            }

            parse_str($_COOKIE[$cookieName], $data);
            if (!array_key_exists('token', $data)) {
                return;
            }

            if (!($user = User::findByToken($data['token'], 'remember'))) {
                return;
            }
        }

        if (!$user && !($user = User::find($userId))) {
            return;
        }

        if ($onlyActive && !$user->is_active) {
            return;
        }

        return $user;
    }

    /**
     * Returns true if user is logged in
     * @return boolean
     */
    public static function isLoggedIn()
    {
        return (bool) self::getUser();
    }

    /**
     * Redirect to authentication page if the user is not logged in
     * @return User
     */
    public static function assertLoggedIn()
    {
        if (!($user = self::getUser())) {
            self::redirectToAuth();
        }

        return $user;
    }

    /**
     * Check if the logged in user's role is higher or equal to the given role
     * @param string $targetRole One of the config's roles
     * @return boolean
     */
    public static function isRole($targetRole)
    {
        if (!($user = self::getUser())) {
            return false;
        }

        $targetRole = strtoupper($targetRole);
        $userRole = $user->role;

        $roles = self::cfg('acl.roles');

        return array_search($targetRole, $roles)
            <= array_search($userRole, $roles);
    }

    /**
     * Throw a AuthBox\Lib\BadRoleException if the role doesn't correspond
     * to the given role
     * @param string $targetRole
     * @return null
     */
    public static function assertRole($targetRole)
    {
        self::assertLoggedIn();

        if (!self::isRole($targetRole)) {
            throw new BadRoleException();
        }
    }

}

// =============================================================================

AuthBox::initialize();

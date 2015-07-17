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

if (defined('AUTHBOX')) return;

define('AUTHBOX', true);

// -----------------------------------------------------------------------------
// Directories

define('AUTHBOX_ROOT_DIR', realpath(__DIR__ . DS . '..'));
define('AUTHBOX_SRC_DIR', AUTHBOX_ROOT_DIR . DS . 'src');
define('AUTHBOX_VENDOR_DIR', AUTHBOX_SRC_DIR . DS . 'vendor');
define('AUTHBOX_USER_DIR', AUTHBOX_ROOT_DIR . DS . 'user');

// -----------------------------------------------------------------------------
// URLs

define('AUTHBOX_REQUEST_URL', $_SERVER['REQUEST_URI']);
define('AUTHBOX_BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));

$protocol = 'http';
$port = (string) $_SERVER['SERVER_PORT'];

if (strpos($port, '443') === 0) {
    $protocol = 'https';
} if (in_array($port, array('80', '443'))) {
    $port = null;
}

define('AUTHBOX_HOST', $protocol . '://' . $_SERVER['SERVER_NAME']
    . ($port ? ':' . $port : ''));

define('AUTHBOX_BASE_URL_WITH_HOST', AUTHBOX_HOST . AUTHBOX_BASE_URL);

define('AUTHBOX_HTTP_METHOD', strtoupper($_SERVER['REQUEST_METHOD']));
define('AUTHBOX_IS_POST', AUTHBOX_HTTP_METHOD === 'POST');

define('AUTHBOX_HTTP_REFERER',
    array_key_exists('HTTP_REFERER', $_SERVER) ?
        $_SERVER['HTTP_REFERER'] : null);

define('AUTHBOX_DOMAIN_NAME', array_key_exists('SERVER_NAME', $_SERVER) ?
    strtolower($_SERVER['SERVER_NAME']) : '');

define('AUTHBOX_TOP_LEVEL_DOMAIN_NAME',
    preg_replace('/^(.*\.)*([^.]*\.[a-z]+)$/', '$2', AUTHBOX_DOMAIN_NAME));

// -----------------------------------------------------------------------------
// Load classes

$directories = [
    AUTHBOX_SRC_DIR . DS . 'lib',
    AUTHBOX_USER_DIR . DS . 'models',
];

foreach ($directories as $dir) {
    foreach (glob($dir . DS . '*.php') as $pathname) {
        require_once $pathname;
    }
}

// Load vendors

if (!class_exists('ORM')) {
    require_once AUTHBOX_VENDOR_DIR
        . DS . 'j4mie'
        . DS . 'idiorm'
        . DS . 'idiorm.php';
}

if (!class_exists('PHPMailer')) {
    require_once AUTHBOX_VENDOR_DIR
        . DS . 'phpmailer'
        . DS . 'phpmailer'
        . DS . 'PHPMailerAutoload.php';
}

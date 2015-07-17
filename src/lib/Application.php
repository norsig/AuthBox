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

use AuthBox\Lib\Controller;
use AuthBox\Lib\Session;
use AuthBox\Models\Token;
use AuthBox;
use PHPMailer;
use ORM;
use PDOException;

// =============================================================================

class Application
{

    // =========================================================================

    private static $instance;

    // -------------------------------------------------------------------------

    public static function factory()
    {
        self::$instance = new self();
        self::$instance->initialize();

        return self::$instance;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    // =========================================================================

    private $flashMessages = array();
    private $ctrl;

    // -------------------------------------------------------------------------

    public function initialize()
    {
        if (!AuthBox::isInstalled()) {
            $this->install();
            exit;
        }
    }

    public function install()
    {
        $output = null;

        if (!AUTHBOX_IS_POST) {
            header('Content-type: text/html; charset=utf-8');
            require AUTHBOX_SRC_DIR . DS . 'data' . DS . 'setup.phtml';
            return;
        }

        $data = $this->p('setup');

        foreach ($this->p('db')[$data['dbms']] as $key => $value) {
            $data['db_' . $key] = $value;
        }

        $db = ['dbms' => $data['dbms']];
        foreach ($data as $key => $value) {
            if (strpos($key, 'db_') === 0) {
                $db[str_replace('db_', '', $key)] = $value;
            }
        }

        AuthBox::initializeDatabase($db);

        try {
            ORM::for_table('foo')->raw_query('SELECT 1');
        } catch (PDOException $e) {
            $this->error([
                'error' => 'Database connection test failed: '
                    . $e->getMessage(),
            ]);
            return;
        }

        $configFile = AuthBox::getConfigFile();
        $configSampleFile = preg_replace('/\.php$/', '-sample.php',
            $configFile);

        $output = file_get_contents($configSampleFile);

        $data['security_salt'] = sha1(uniqid('', true));
        $data['auto_activation_token'] = sha1(uniqid('', true));

        foreach ($data as $key => $value) {
            if (is_numeric($value)
                && ((string) $value === (string) (float) $value)) {
                $value = (float) $value;
            } else if (in_array(strtolower($value), ['true', 'false', 'null'])) {
                $value = strtolower($value);
            } else {
                $value = "'" . str_replace("'", "\\'", $value) . "'";
            }

            $output = str_replace('MY__' . strtoupper($key), $value, $output);
        }

        $output = preg_replace('/MY__[A-Z_]+/', 'null', $output);

        file_put_contents($configFile, $output);

        AuthBox::initialize();
        AuthBox::executeDatabaseAction('reinstall');

        $this->success();
    }

    public function run()
    {
        // Small cleanup on tokens table
        Token::cleanup();

        // Get controller
        $this->ctrl = new Controller();

        // Get routes
        $routes = AuthBox::cfg('routes');

        uksort($routes, function($a, $b)
        {
            $la = strlen($a);
            $lb = strlen($b);

            if ($la > $lb) {
                return -1;
            } else if ($la < $lb) {
                return 1;
            }

            return 0;
        });

        foreach ($routes as $name => $path) {
            if (!$path || $path{0} !== '/') {
                $path = AUTHBOX_BASE_URL . '/' . $path;
            }

            $regex = '/^'
                . str_replace('/', '\\/', preg_quote($path))
                . '([?&\\/].*)?$/';

            if (!preg_match($regex, AUTHBOX_REQUEST_URL)) {
                continue;
            }

            $methodName = 'on' . str_replace(' ', '',
                str_replace('_', ' ', ucwords($name)));

            $this->ctrl->$methodName($this);
            return;
        }

        AuthBox::redirect($routes['index']);
    }

    public function runCatchingErrors()
    {
        try {
            $this->run();
        } catch (PDOException $e) {
            header('Content-type: text/plain; charset=utf-8');
            echo $e->getMessage();
        }
    }

    public function flashMessage($text, $type = 'info', $processArray = true)
    {
        $self = $this;

        if (is_array($text) && $processArray) {
            return array_map(function($t) use ($self, $type) {
                return $self->flashMessage($t, $type, false);
            }, $text);
        }

        Session::push('flash_messages', [
            'text' => $text,
            'type' => $type,
        ]);
    }

    public function getFlashMessages()
    {
        return Session::delete('flash_messages') ?: [];
    }

    public function keepFormData($data = null)
    {
        Session::set('previous_form', $data ?: $this->p());
    }

    public function fetchTemplate($tpl, array $vars = array(), $dir = null)
    {
        $theme = AuthBox::cfg('app.theme');
        $themeDirectory = AUTHBOX_USER_DIR . DS . 'themes' . DS . $theme;
        $themeUrl = AUTHBOX_BASE_URL . '/user/themes/' . $theme;
        if ($dir !== null) {
            $themeDirectory .= DS . $dir;
            $themeUrl .= '/' . $dir;
        }

        $vars['__THEME'] = $theme;
        $vars['__THEME_URL'] = $themeUrl;
        $vars['__USER'] = AuthBox::getUser();

        extract($vars);

        $previousDir = getcwd();
        chdir($themeDirectory);

        ob_start();
        require $tpl;
        $body = ob_get_contents();
        ob_end_clean();

        chdir($previousDir);

        return $body;
    }

    public function render($tpl, array $vars = array())
    {
        $vars['__VIEW'] = null;
        $vars['__IS_ADMIN'] = $this->ctrl->isAdmin();
        $vars['__FORM'] = Session::delete('previous_form');

        $body = $this->fetchTemplate($tpl, $vars);

        header('Content-type: text/html; charset=utf-8');
        echo $body;
    }

    public function json(array $data)
    {
        header('Content-type: text/javascript; charset=utf-8');
        echo json_encode($data);
    }

    public function success(array $data = array())
    {
        $this->json(array_merge(['success' => true], $data));
    }

    public function error(array $data = array())
    {
        $this->json(array_merge(['success' => false], $data));
    }

    public function sendEmail(array $params)
    {
        $params = array_merge([
            'to' => null,
            'subject' => null,
            'tpl' => null,
            'vars' => [],
        ], $params);

        $body = $this->fetchTemplate($params['tpl'],
            $params['vars'], 'emails');

        $cfg = AuthBox::cfg('emails');

        $mail = new PHPMailer();

        $mail->CharSet = 'UTF-8';

        if ($cfg['smtp']['enabled']) {
            $mail->IsSMTP();

            $mail->Host = $cfg['smtp']['host'];
            $mail->SMTPSecure = $cfg['smtp']['encryption'];
            $mail->SMTPDebug = false;
            $mail->Port = $cfg['smtp']['port'];
            $mail->Timeout = 5;

            if ($mail->SMTPAuth = $cfg['smtp']['auth']) {
                $mail->Username = $cfg['smtp']['username'];
                $mail->Password = $cfg['smtp']['password'];
            }
        }

        $from = (array) $cfg['from'];
        $mail->SetFrom($from[0],
            count($from) >= 2 ? $from[1] : null);

        if ($cfg['replyTo']) {
            $replyTo = (array) $cfg['replyTo'];
            $mail->AddReplyTo($replyTo[0],
                count($replyTo) >= 2 ? $replyTo[1] : null);
        }

        $mail->Subject = $params['subject'];
        $mail->MsgHTML($body);

        foreach ((array) $params['to'] as $to) {
            $to = (array) $to;
            $mail->AddAddress($to[0],
                count($to) >= 2 ? $to[1] : null);
        }

        $mail->send();
    }

    public function p($key = null, $default = null)
    {
        $vars = array_merge($_GET, $_POST);

        if ($key === null) {
            return $vars;
        }

        return array_key_exists($key, $vars) ? $vars[$key] : $default;
    }

    public function generatePassword($length = 8, $readable = true)
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';

        if (!$readable) {
            $alphabet .= 'ilo01';
        }

        $str = '';

        $alphabetLength = strlen($alphabet);
        for ($i = 0; $i < $length; $i++) {
            $str .= $alphabet{mt_rand(0, $alphabetLength - 1)};
        }

        return $str;
    }

}

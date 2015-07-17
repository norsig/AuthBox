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
use AuthBox\Lib\Session;
use AuthBox\Models\User;
use AuthBox\Models\Token;
use AuthBox;
use ORM;
use Exception;

// =============================================================================

class Controller
{

    // =========================================================================

    public function onIndex(Application $app)
    {
        if (AuthBox::isLoggedIn()) {
            AuthBox::redirect($app->p('after', AuthBox::makeUrl('profile')));
        }

        $app->render('index.phtml', [
            'forms' => ['login', 'register'],
        ]);
    }

    public function onRegister(Application $app)
    {
        if (AuthBox::isLoggedIn()) {
            AuthBox::redirect($app->p('after', AuthBox::makeUrl('profile')));
        }

        if (!AUTHBOX_IS_POST) {
            $app->render('index.phtml', [
                'forms' => ['register'],
            ]);
            return;
        }

        $cfg = AuthBox::cfg('behaviour');

        $user = new User();

        $errors = [];

        foreach ($user->getAdditionalFields() as $fieldName => $field) {
            $value = $app->p($fieldName);

            if ($field['mandatory'] && !$value) {
                $errors[] = $field['messages']['missing'];
                continue;
            }

            if ($field['regex'] && !preg_match($field['regex'], $value ?: '')) {
                $errors[] = $field['messages']['bad_format'];
                continue;
            }

            if ($field['unique']) {
                $r = ORM::for_table($user->getTableName())
                    ->where_equal($fieldName, $value)
                    ->find_one();

                if ($r) {
                    $errors[] = $field['messages']['duplicate'];
                    continue;
                }
            }

            if ($value === null) {
                $value = $field['default_value'];
            }

            $user->$fieldName = $app->p($fieldName);
        }

        if (!($password = $app->p('password'))) {
            $errors[] = __('fields.password.missing');
        } else if ($cfg['passwordValidationRegex']
            && !preg_match_all($cfg['passwordValidationRegex'], $password)) {
            $errors[] = __('fields.password.bad_format');
        }

        $user->setPassword($password);

        if ($cfg['autoActivated']) {
            $user->is_active = true;
        } else if ($token = $app->p('t')) {
            $user->is_active = in_array($token, $cfg['autoActivationTokens']);
        }

        if ($errors) {
            $app->flashMessage([$errors], 'danger');
            $app->keepFormData();
            AuthBox::redirect($this->getPreviousUrl());
            return;
        }

        $user->role = AuthBox::cfg('acl.defaultRole');

        $user->save();

        if (AuthBox::cfg('behaviour.sendEmailToUserOnRegistration')
            && ($email = $user->getEmail())) {
            $app->sendEmail([
                'to' => $email,
                'subject' => __('register.success_email.subject'),
                'tpl' => 'registration.phtml',
                'vars' => [
                    'user' => $user,
                ],
            ]);
        }

        if (AuthBox::cfg('behaviour.registrationAlert.enabled')) {
            $app->sendEmail([
                'to' => AuthBox::cfg('behaviour.registrationAlert.to'),
                'subject' => __('register.alert_email.subject'),
                'tpl' => 'registration_alert.phtml',
                'vars' => [
                    'user' => $user,
                ],
            ]);
        }

        if ($user->is_active) {
            $this->executeLogin($user);
        }

        AuthBox::redirect(AuthBox::makeUrl('success',
            ['after' => $this->getAfterUrl('register', $user)]));
    }

    public function onSuccess(Application $app)
    {
        $app->render('success.phtml', [
            'after_url' => $app->p('after'),
        ]);
    }

    public function onLogin(Application $app)
    {
        if (AuthBox::isLoggedIn()) {
            AuthBox::redirect($app->p('after', AuthBox::makeUrl('profile')));
        }

        if (!AUTHBOX_IS_POST) {
            $app->render('index.phtml', [
                'forms' => ['login'],
            ]);
            return;
        }

        $errors = [];

        $login = $app->p('login');
        $password = $app->p('password');

        if (!$login) {
            $errors[] = __('login.error.login.empty');
        }

        if (!$password) {
            $errors[] = __('login.error.password.empty');
        }

        if (!$errors) {
            $u = new User();

            $where = [];
            foreach (AuthBox::cfg('behaviour.authFields') as $fieldName) {
                $where[] = [
                    $fieldName => $login,
                    'password' => $u->hashPassword($password),
                ];
            }

            $user = User::factory(ORM::for_table($u->getTableName())
                ->where_any_is($where)
                ->find_one());

            if (!$user) {
                $errors[] = __('login.error.credentials');
            }
        }

        if ($errors) {
            $app->flashMessage([$errors], 'danger');
            $app->keepFormData();
            AuthBox::redirect($this->getPreviousUrl());
            return;
        }

        if (!$user->is_active) {
            AuthBox::redirect(AuthBox::makeUrl('success',
                ['after' => $this->getAfterUrl('login', $user)]));
        }

        $this->executeLogin($user, (bool) (int) $app->p('remember'));

        AuthBox::redirect($this->getAfterUrl('login', $user));
    }

    public function onForgotPassword(Application $app)
    {
        $login = $app->p('login');

        $u = new User();

        $where = [];
        foreach (AuthBox::cfg('behaviour.authFields') as $fieldName) {
            $where[] = [$fieldName => $login];
        }

        $user = User::factory(ORM::for_table($u->getTableName())
            ->where_any_is($where)
            ->find_one());

        if (!$user) {
            $app->flashMessage(__('forgot_password.not_exists',
                ['login' => $login]), 'danger');
            AuthBox::redirect(AuthBox::makeUrl('login'));
        }

        if ($email = $user->getEmail()) {
            $app->sendEmail([
                'to' => $email,
                'subject' => __('forgot_password.email_subject'),
                'tpl' => 'reset_password.phtml',
                'vars' => [
                    'resetLink' => AuthBox::makeUrl('resetPassword', [
                        'token' => $user->createToken(3600, 'reset')->key,
                    ], true),
                ],
            ]);
        }

        $app->flashMessage(__('forgot_password.sent',
            ['login' => $login]), 'info');

        AuthBox::redirect(AuthBox::makeUrl('login'));
    }

    public function onResetPassword(Application $app)
    {
        $token = $app->p('token');

        if (!($user = User::findByToken($token, 'reset'))) {
            AuthBox::redirect(AuthBox::makeUrl('index'));
        }

        if (!AUTHBOX_IS_POST) {
            $app->render('reset_password.phtml');
            return;
        }

        if (!($password = $app->p('password'))) {
            $errors[] = __('fields.password.missing');
        } else if ($cfg['passwordValidationRegex']
            && !preg_match_all($cfg['passwordValidationRegex'], $password)) {
            $errors[] = __('fields.password.bad_format');
        }

        $user->setPassword($password);

        if ($errors) {
            $app->flashMessage([$errors], 'danger');
            AuthBox::redirect(AuthBox::makeUrl('resetPassword',
                ['token' => $token]));
            return;
        }

        $user->save();
        $app->flashMessage(__('reset_password.success'), 'success');
        AuthBox::redirect(AuthBox::makeUrl('login'));
    }

    public function onLogout(Application $app)
    {
        $this->executeLogout();

        AuthBox::redirect($this->getAfterUrl('logout'));
    }

    public function onProfile(Application $app)
    {
        $user = AuthBox::assertLoggedIn();

        $do = $app->p('do');
        $cfg = AuthBox::cfg('behaviour');
        $errors = [];

        if ($do === 'profile') {
            $idField = $user->getIdentifyerField();
            foreach ($user->getAdditionalFields() as $fieldName => $field) {
                $value = $app->p($fieldName);

                if ($field['mandatory'] && !$value) {
                    $errors[] = $field['messages']['missing'];
                    continue;
                }

                if ($field['regex'] && !preg_match($field['regex'], $value ?: '')) {
                    $errors[] = $field['messages']['bad_format'];
                    continue;
                }

                if ($field['unique']) {
                    $r = ORM::for_table($user->getTableName())
                        ->where($fieldName, $value)
                        ->where_not_equal($idField, $user->id)
                        ->find_one();

                    if ($r) {
                        $errors[] = $field['messages']['duplicate'];
                        continue;
                    }
                }

                if ($value === null) {
                    $value = $field['default_value'];
                }

                $user->$fieldName = $app->p($fieldName);
            }

            if ($errors) {
                $app->flashMessage([$errors], 'danger');
                $app->keepFormData();
                AuthBox::redirect(AuthBox::makeUrl('profile'));
                return;
            }

            $user->save();
            $app->flashMessage(__('profile.success'), 'success');
            AuthBox::redirect(AuthBox::makeUrl('profile'));
            return;
        }

        if ($do === 'password') {
            if (!($currentPassword = $app->p('current_password'))) {
                $errors[] = __('fields.current_password.missing');
            } else if ($user->hashPassword($currentPassword) !== $user->password) {
                $errors[] = __('fields.current_password.bad');
            }

            if (!($password = $app->p('new_password'))) {
                $errors[] = __('fields.new_password.missing');
            } else if ($cfg['passwordValidationRegex']
                && !preg_match_all($cfg['passwordValidationRegex'], $password)) {
                $errors[] = __('fields.password.bad_format');
            }

            $user->setPassword($password);

            if ($errors) {
                $app->flashMessage([$errors], 'danger');
                AuthBox::redirect(AuthBox::makeUrl('profile'));
                return;
            }

            $user->save();
            $app->flashMessage(__('password.success'), 'success');
            AuthBox::redirect(AuthBox::makeUrl('profile'));
            return;
        }

        $app->render('profile.phtml', [
            'user' => AuthBox::getUser(),
        ]);
    }

    // -------------------------------------------------------------------------

    private function executeLogin($user, $remember = false)
    {
        AuthBox::login($user, $remember);
    }

    private function executeLogout()
    {
        AuthBox::logout();
    }

    // -------------------------------------------------------------------------

    private function getAfterUrl($type, $user = null)
    {
        if ($url = Application::getInstance()->p('after')) {
            return $url;
        }

        $url = AuthBox::cfg('behaviour.after' . ucfirst($type) . 'Url');

        if ($user) {
            foreach (get_object_vars($user) as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }

        return $url;
    }

    private function getPreviousUrl()
    {
        if (!($url = Application::getInstance()->p('previous'))) {
            $url = AUTHBOX_HTTP_REFERER ?:
                AuthBox::makeUrl('index');
        }

        return $url;
    }

    // -------------------------------------------------------------------------

    public function onAdmin(Application $app)
    {
        if ($do = $app->p('do')) {
            if (!$this->isAdmin()) {
                throw new Exception('You are not allowed to do this action');
                return;
            }

            $methodName = 'adminCommand' . ucfirst($do);
            if (!method_exists($this, $methodName)) {
                throw new Exception('Unknown admin action');
                return;
            }

            $this->$methodName($app);
            return;
        }

        if ($password = $app->p('password')) {
            if (in_array($password, AuthBox::cfg('admin.passwords'))) {
                Session::set('admin', true);
            } else {
                $app->flashMessage(__('admin.bad_password'), 'danger');
            }
        }

        $u = new User();
        $q = ORM::for_table($u->getTableName())->select('*');
        $q->order_by_asc('is_active');
        foreach ($u->getDefaultSortKeys() as $sort) {
            if (strtoupper($sort[1]) === 'DESC') {
                $q->order_by_desc($sort[0]);
            } else {
                $q->order_by_asc($sort[0]);
            }
        }

        $app->render('admin.phtml', [
            'view' => 'admin',
            'users' => User::factory($q->find_many()),
        ]);
    }

    public function adminCommandLogout(Application $app)
    {
        Session::delete('admin');

        AuthBox::redirect(AuthBox::makeUrl('index'));
    }

    public function adminCommandPhpinfo(Application $app)
    {
        phpinfo();
    }

    public function adminCommandDeleteUser(Application $app)
    {
        $user = User::find($app->p('id'));

        if (!$user) {
            throw new Exception('User not found');
        }

        $user->delete();

        AuthBox::redirect(AuthBox::makeUrl('admin'));
    }

    public function adminCommandEditUser(Application $app)
    {
        $user = User::find($app->p('id'));

        if (!$user) {
            throw new Exception('User not found');
        }

        foreach ($user->getAdditionalFields() as $fieldName => $field) {
            $user->$fieldName = $app->p($fieldName);
        }

        if ($password = $app->p('password')) {
            $user->setPassword($password);
        }

        $user->is_active = (bool) (int) $app->p('is_active');
        $user->role = $app->p('role');

        $user->save();

        AuthBox::redirect(AuthBox::makeUrl('admin'));
    }

    public function adminCommandActivateUser(Application $app)
    {
        $user = User::find($app->p('id'));

        if (!$user) {
            throw new Exception('User not found');
        }

        $user->is_active = true;
        $user->save();

        if (AuthBox::cfg('behaviour.sendEmailToUserOnActivation')
            && ($email = $user->getEmail())) {
            $app->sendEmail([
                'to' => $email,
                'subject' => __('admin.activation_email.subject'),
                'tpl' => 'activation.phtml',
                'vars' => [
                    'user' => $user,
                ],
            ]);
        }

        $app->flashMessage(__('admin.activation.confirmation'), 'success');

        AuthBox::redirect(AuthBox::makeUrl('admin'));
    }

    public function adminCommandInstall(Application $app)
    {
        AuthBox::executeDatabaseAction('install');

        die('INSTALLED.');
    }

    public function adminCommandUninstall(Application $app)
    {
        AuthBox::executeDatabaseAction('uninstall');

        die('UNINSTALLED.');
    }

    public function adminCommandReinstall(Application $app)
    {
        AuthBox::executeDatabaseAction('uninstall');
        AuthBox::executeDatabaseAction('install');

        die('REINSTALLED.');
    }

    // -------------------------------------------------------------------------

    public function isAdmin()
    {
        return Session::get('admin', false);
    }

}

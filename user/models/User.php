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

namespace AuthBox\Models;

// -----------------------------------------------------------------------------

use AuthBox\Lib\Model;
use AuthBox\Lib\Lang;
use AuthBox;

// =============================================================================

class User extends Model
{

    // =========================================================================

    public static function findByToken($token, $type = false)
    {
        if (!($token instanceof Token)) {
            if (!($token = Token::find($token))) {
                return;
            }
        }

        if ($type !== false && $token->type !== $type) {
            return;
        }

        return self::find($token->id_user);
    }

    // =========================================================================

    public $id;
    public $created_at;
    public $updated_at;
    public $password;
    public $plain_password;
    public $role;
    public $is_active = false;

    // -------------------------------------------------------------------------

    /**
     * User's constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns the "id" field of the table
     * @return string
     */
    public function getIdentifyerField()
    {
        return 'id';
    }

    /**
     * Returns the list of the fields which are fillable by the user.
     * @return string
     */
    public function getAdditionalFields()
    {
        $fields = [

            'email' => [

                'label' => Lang::t('fields.email.label'),
                'placeholder' => Lang::t('fields.email.placeholder'),

                'type' => 'email',

                'mandatory' => true,
                'unique' => true,
                'regex' => '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i',

                'messages' => [
                    'info' => Lang::t('fields.email.info'),
                    'missing' => Lang::t('fields.email.missing'),
                    'bad_format' => Lang::t('fields.email.bad_format'),
                    'duplicate' => Lang::t('fields.email.duplicate'),
                ],

                'is_visible' => true,
                'default_value' => null,

            ],

            'username' => [

                'label' => Lang::t('fields.username.label'),
                'placeholder' => Lang::t('fields.username.placeholder'),

                'type' => 'text',

                'mandatory' => true,
                'unique' => true,
                'regex' => '/^[a-z0-9_.]{3,}$/i',

                'messages' => [
                    'info' => Lang::t('fields.username.info'),
                    'missing' => Lang::t('fields.username.missing'),
                    'bad_format' => Lang::t('fields.username.bad_format'),
                    'duplicate' => Lang::t('fields.username.duplicate'),
                ],

                'is_visible' => true,
                'default_value' => null,

            ],

        ];

        return $fields;
    }

    public function getDefaultSortKeys()
    {
        return [
            ['email', 'ASC'],
            ['username', 'ASC'],
            ['created_at', 'ASC'],
        ];
    }

    public function getLogin()
    {
        return $this->email;
    }

    public function getEmail()
    {
        return $this->email;
    }

    // -------------------------------------------------------------------------

    public function beforeCreate()
    {
        $this->updated_at = date('Y-m-d H:i:s');
        $this->created_at = $this->updated_at;
    }

    public function beforeUpdate()
    {
        $this->updated_at = date('Y-m-d H:i:s');
    }

    public function onLoaded()
    {
        $this->is_active = (bool) (int) $this->is_active;
    }

    // -------------------------------------------------------------------------

    public function setPassword($password)
    {
        if (AuthBox::cfg('security.storePlainPassword')) {
            $this->plain_password = $password;
        }

        $this->password = $this->hashPassword($password);
    }

    public function hashPassword($password)
    {
        return hash('sha256', AuthBox::cfg('security.salt') . $password);
    }

    // -------------------------------------------------------------------------

    public function createToken($expiresAt = null, $type = null)
    {
        if (is_integer($expiresAt)) {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresAt);
        }

        $token = new Token();
        $token->id_user = $this->id;
        $token->expires_at = $expiresAt;
        $token->type = $type;
        $token->save();

        return $token;
    }

    public function getRememberToken()
    {
        if (!($token = Token::findOneByUser($this->id, 'remember'))) {
            $token = $this->createToken(null, 'remember');
        }

        return $token;
    }

}

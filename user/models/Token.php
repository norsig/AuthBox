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
use ORM;

// =============================================================================

class Token extends Model
{

    // =========================================================================

    public static function find($key, $allowExpired = false)
    {
        $query = ORM::for_table((new self())->getTableName())
            ->where('key', $key);

        if (!$allowExpired) {
            $query->where_raw('(`expires_at` IS NULL OR `expires_at` >= ?)',
                [date('Y-m-d H:i:s')]);
        }

        return self::factory($query->find_one());
    }

    public static function findOneByUser($userId, $type = false)
    {
        $query = ORM::for_table((new self())->getTableName())
            ->where('id_user', $userId)
            ->where_raw('(`expires_at` IS NULL OR `expires_at` >= ?)',
                [date('Y-m-d H:i:s')]);;

        if ($type !== false) {
            $query->where('type', $type);
        }

        return self::factory($query->find_one());
    }

    public static function cleanup()
    {
        ORM::for_table((new self())->getTableName())
            ->where_not_null('expires_at')
            ->where_lt('expires_at', date('Y-m-d H:i:s'))
            ->delete_many();
    }

    // =========================================================================

    public $key;
    public $created_at;
    public $updated_at;
    public $expires_at;
    public $id_user;
    public $type;

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
        return 'key';
    }

    // -------------------------------------------------------------------------

    public function beforeCreate()
    {
        $this->key = hash('sha256', uniqid('', true));
        $this->updated_at = date('Y-m-d H:i:s');
        $this->created_at = $this->updated_at;
    }

    public function beforeUpdate()
    {
        $this->updated_at = date('Y-m-d H:i:s');
    }

}

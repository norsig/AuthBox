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

use AuthBox;
use ORM;
use Exception;

// =============================================================================

abstract class Model
{

    // =========================================================================

    public static function factory($object)
    {
        $className = get_called_class();

        if (!$object) {
            return is_array($object) ? [] : null;
        }

        if (is_array($object)) {
            return array_map([$className, 'factory'], $object);
        }

        $model = new $className();
        $model->fillFromOrm($object);
        return $model;
    }

    public static function find($id)
    {
        $className = get_called_class();
        $model = new $className();

        return call_user_func(
            [$className, 'factory'],
            ORM::for_table($model->getTableName())
                ->where_equal($model->getIdentifyerField(), $id)
                ->find_one()
        );
    }

    // =========================================================================

    protected $additionalFieldsData = array();
    protected $ormObject;

    // -------------------------------------------------------------------------

    public function __construct()
    {
        foreach ($this->getAdditionalFields() as $fieldName => $fieldParams) {
            $this->additionalFieldsData[$fieldName] = $fieldParams['default_value'];
        }
    }

    public function fillFromOrm($object)
    {
        $this->ormObject = $object;

        foreach ($object->as_array() as $key => $value) {
            if ($this->isPropertyExists($key)) {
                $this->$key = $value;
            }
        }

        $this->onLoaded();
    }

    public function save()
    {
        $idField = $this->getIdentifyerField();
        $model = null;

        if ($this->$idField) {
            $this->beforeUpdate();
            $model = ORM::for_table($this->getTableName())
                ->where($idField, $this->$idField)->find_one();
        } else {
            $this->beforeCreate();
            $model = ORM::for_table($this->getTableName())
                ->create();
        }

        $defaultVars = get_class_vars(get_class());
        foreach (get_object_vars($this) as $key => $value) {
            if (array_key_exists($key, $defaultVars)) {
                continue;
            }

            $model->$key = $value;
        }

        foreach ($this->additionalFieldsData as $key => $value) {
            $model->$key = $value;
        }

        $model->save();

        if ($idField === 'id') {
            $this->$idField = $model->id();
        }
    }

    public function delete()
    {
        $className = get_called_class();
        $identifyer = $this->getIdentifyerField();

        if (!$this->ormObject) {
            $this->ormObject = call_user_func([$className, 'factory'],
                $this->$identifyer);
        }

        if (!$this->ormObject) {
            return;
        }

        $this->ormObject->delete();
    }

    public function asObject()
    {
        $arr = [];

        $defaultVars = get_class_vars(get_class());
        foreach (get_object_vars($this) as $key => $value) {
            if (array_key_exists($key, $defaultVars)) {
                continue;
            }

            $arr[$key] = $value;
        }

        foreach ($this->additionalFieldsData as $key => $value) {
            $arr[$key] = $value;
        }

        return (object) $arr;
    }

    public function getTableName($id = null)
    {
        if ($id === null) {
            $id = preg_replace('/^.*\\\\/', '', get_called_class());
            $id{0} = strtolower($id{0});
        }

        $cfg = AuthBox::cfg('database');

        return $cfg['tables_prefix']
            . $cfg['tables'][$id]['name'];
    }

    public function __get($key)
    {
        if (!array_key_exists($key, $this->additionalFieldsData)) {
            throw new Exception("The field $key does not exists in model "
                . get_called_class());
        }

        return $this->additionalFieldsData[$key];
    }

    public function __set($key, $value)
    {
        if (!array_key_exists($key, $this->additionalFieldsData)) {
            throw new Exception("The field $key does not exists in model "
                . get_called_class());
        }

        $this->additionalFieldsData[$key] = $value;
    }

    public function isPropertyExists($key)
    {
        return property_exists($this, $key)
            || array_key_exists($key, $this->additionalFieldsData);
    }

    // -------------------------------------------------------------------------

    public function getIdentifyerField()
    {
        return;
    }

    public function beforeCreate()
    {
        return;
    }

    public function beforeUpdate()
    {
        return;
    }

    public function onLoaded()
    {
        return;
    }

    public function getAdditionalFields()
    {
        return [];
    }

}

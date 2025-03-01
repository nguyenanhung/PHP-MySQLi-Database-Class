<?php
/**
 * Project PHP-MySQLi-Database-Class
 * Created by PhpStorm
 * User: 713uk13m <dev@nguyenanhung.com>
 * Copyright: 713uk13m <dev@nguyenanhung.com>
 * Date: 08/21/2021
 * Time: 11:34
 */

namespace nguyenanhung\MySQLi;

use Exception;

/**
 * DbObject
 *
 * @category  Database Access
 * @package   nguyenanhung\MySQLi
 * @author    713uk13m <dev@nguyenanhung.com>
 * @copyright 713uk13m <dev@nguyenanhung.com>
 * @author    Alexander V. Butenko <a.butenka@gmail.com>
 * @copyright Copyright (c) 2015-2017
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link      http://github.com/joshcam/PHP-MySQLi-Database-Class
 * @version   2.9-master
 *
 * @method DbObject query($query, $numRows = NULL)
 * @method DbObject rawQuery($query, $bindParams = NULL)
 * @method DbObject groupBy(string $groupByField)
 * @method DbObject orderBy($orderByField, $orderbyDirection = "DESC", $customFieldsOrRegExp = NULL)
 * @method DbObject where($whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND')
 * @method DbObject orWhere($whereProp, $whereValue = 'DBNULL', $operator = '=')
 * @method DbObject having($havingProp, $havingValue = 'DBNULL', $operator = '=', $cond = 'AND')
 * @method DbObject orHaving($havingProp, $havingValue = NULL, $operator = NULL)
 * @method DbObject setQueryOption($options)
 * @method DbObject setTrace($enabled, $stripPrefix = NULL)
 * @method DbObject withTotalCount()
 * @method DbObject startTransaction()
 * @method DbObject commit()
 * @method DbObject rollback()
 * @method DbObject ping()
 * @method string getLastError()
 * @method string getLastQuery()
 */
class DbObject
{
    /**
     * Working instance of MysqliDb created earlier
     *
     * @var MysqliDb
     */
    private $db;
    /** @var */
    protected static $modelPath;
    /**
     * An array that holds object data
     *
     * @var array
     */
    public $data;
    /**
     * Flag to define is object is new or loaded from database
     *
     * @var boolean
     */
    public $isNew = TRUE;
    /**
     * Return type: 'Array' to return results as array, 'Object' as object
     * 'Json' as json string
     *
     * @var string
     */
    public $returnType = 'Object';
    /**
     * An array that holds has* objects which should be loaded togeather with main
     * object togeather with main object
     *
     * @var string
     */
    private $_with = array();
    /**
     * Per page limit for pagination
     *
     * @var int
     */
    public static $pageLimit = 20;
    /**
     * Variable that holds total pages count of last paginate() query
     *
     * @var int
     */
    public static $totalPages = 0;
    /**
     * Variable which holds an amount of returned rows during paginate queries
     *
     * @var string
     */
    public static $totalCount = 0;
    /**
     * An array that holds insert/update/select errors
     *
     * @var array
     */
    public $errors = NULL;

    /** @var string $primaryKey Primary key for an object. 'id' is a default value. */
    protected $primaryKey = 'id';
    /** @var string $dbTable Table name for an object. Class name will be used by default */
    protected $dbTable;

    /**
     * @var array name of the fields that will be skipped during validation, preparing & saving
     */
    protected $toSkip = array();
    /**
     * @var
     */
    protected $createdAt;
    protected $updatedAt;

    /**
     * DbObject constructor.
     *
     * @param null|array $data Data to preload on object creation
     *
     * @author   : 713uk13m <dev@nguyenanhung.com>
     * @copyright: 713uk13m <dev@nguyenanhung.com>
     */
    public function __construct($data = NULL)
    {
        $this->db = MysqliDb::getInstance();
        if (empty ($this->dbTable))
            $this->dbTable = get_class($this);

        if ($data)
            $this->data = $data;
    }

    /**
     * Function __set - Magic setter function
     *
     * @param $name
     * @param $value
     *
     * @author   : 713uk13m <dev@nguyenanhung.com>
     * @copyright: 713uk13m <dev@nguyenanhung.com>
     * @time     : 08/21/2021 57:16
     */
    public function __set($name, $value)
    {
        if (property_exists($this, 'hidden') && array_search($name, $this->hidden) !== FALSE)
            return;

        $this->data[$name] = $value;
    }

    /**
     * Function __get - Magic getter function
     *
     * @param $name
     *
     * @return mixed|\nguyenanhung\MySQLi\DbObject|null
     * @author   : 713uk13m <dev@nguyenanhung.com>
     * @copyright: 713uk13m <dev@nguyenanhung.com>
     * @time     : 08/21/2021 56:45
     */
    public function __get($name)
    {
        if (property_exists($this, 'hidden') && array_search($name, $this->hidden) !== FALSE)
            return NULL;

        if (isset ($this->data[$name]) && $this->data[$name] instanceof DbObject)
            return $this->data[$name];

        if (property_exists($this, 'relations') && isset ($this->relations[$name])) {
            $relationType = strtolower($this->relations[$name][0]);
            $modelName    = $this->relations[$name][1];
            switch ($relationType) {
                case 'hasone':
                    $key             = isset ($this->relations[$name][2]) ? $this->relations[$name][2] : $name;
                    $obj             = new $modelName;
                    $obj->returnType = $this->returnType;

                    return $this->data[$name] = $obj->byId($this->data[$key]);
                    break;
                case 'hasmany':
                    $key             = $this->relations[$name][2];
                    $obj             = new $modelName;
                    $obj->returnType = $this->returnType;

                    return $this->data[$name] = $obj->where($key, $this->data[$this->primaryKey])->get();
                    break;
                default:
                    break;
            }
        }

        if (isset ($this->data[$name]))
            return $this->data[$name];

        if (property_exists($this->db, $name))
            return $this->db->$name;
    }

    public function __isset($name)
    {
        if (isset ($this->data[$name]))
            return isset ($this->data[$name]);

        if (property_exists($this->db, $name))
            return isset ($this->db->$name);
    }

    public function __unset($name)
    {
        unset ($this->data[$name]);
    }

    /**
     * Helper function to create dbObject with Json return type
     *
     * @return dbObject
     */
    private function JsonBuilder()
    {
        $this->returnType = 'Json';

        return $this;
    }

    /**
     * Helper function to create dbObject with Array return type
     *
     * @return dbObject
     */
    private function ArrayBuilder()
    {
        $this->returnType = 'Array';

        return $this;
    }

    /**
     * Helper function to create dbObject with Object return type.
     * Added for consistency. Works same way as new $objname ()
     *
     * @return dbObject
     */
    private function ObjectBuilder()
    {
        $this->returnType = 'Object';

        return $this;
    }

    /**
     * Helper function to create a virtual table class
     *
     * @param string tableName Table name
     *
     * @return dbObject
     */
    public static function table($tableName)
    {
        $tableName = preg_replace("/[^-a-z0-9_]+/i", '', $tableName);
        if (!class_exists($tableName))
            eval ("class $tableName extends \nguyenanhung\MySQLi\DbObject {}");

        return new $tableName ();
    }

    /**
     * Function insert
     *
     * @return bool insert id or false in case of failure
     * @throws \Exception
     * @author   : 713uk13m <dev@nguyenanhung.com>
     * @copyright: 713uk13m <dev@nguyenanhung.com>
     * @time     : 08/21/2021 58:23
     */
    public function insert()
    {
        if (!empty ($this->timestamps) && in_array("createdAt", $this->timestamps))
            $this->createdAt = date("Y-m-d H:i:s");
        $sqlData = $this->prepareData();
        if (!$this->validate($sqlData))
            return FALSE;

        $id = $this->db->insert($this->dbTable, $sqlData);
        if (!empty ($this->primaryKey) && empty ($this->data[$this->primaryKey]))
            $this->data[$this->primaryKey] = $id;
        $this->isNew  = FALSE;
        $this->toSkip = array();

        return $id;
    }

    /**
     * Function update
     *
     * @param null|array $data Optional update data to apply to the object
     *
     * @return false|void
     * @throws \Exception
     * @author   : 713uk13m <dev@nguyenanhung.com>
     * @copyright: 713uk13m <dev@nguyenanhung.com>
     * @time     : 08/21/2021 59:18
     */
    public function update($data = NULL)
    {
        if (empty ($this->dbFields))
            return FALSE;

        if (empty ($this->data[$this->primaryKey]))
            return FALSE;

        if ($data) {
            foreach ($data as $k => $v) {
                if (in_array($k, $this->toSkip))
                    continue;

                $this->$k = $v;
            }
        }

        if (!empty ($this->timestamps) && in_array("updatedAt", $this->timestamps))
            $this->updatedAt = date("Y-m-d H:i:s");

        $sqlData = $this->prepareData();
        if (!$this->validate($sqlData))
            return FALSE;

        $this->db->where($this->primaryKey, $this->data[$this->primaryKey]);
        $res          = $this->db->update($this->dbTable, $sqlData);
        $this->toSkip = array();

        return $res;
    }

    /**
     * Function save - Save or Update object
     *
     * @param null $data
     *
     * @return bool|void insert id or false in case of failure
     * @throws \Exception
     * @author   : 713uk13m <dev@nguyenanhung.com>
     * @copyright: 713uk13m <dev@nguyenanhung.com>
     * @time     : 08/21/2021 59:43
     */
    public function save($data = NULL)
    {
        if ($this->isNew)
            return $this->insert();

        return $this->update($data);
    }

    /**
     * Delete method. Works only if object primaryKey is defined
     *
     * @return boolean Indicates success. 0 or 1.
     * @throws \Exception
     */
    public function delete()
    {
        if (empty ($this->data[$this->primaryKey]))
            return FALSE;

        $this->db->where($this->primaryKey, $this->data[$this->primaryKey]);
        $res          = $this->db->delete($this->dbTable);
        $this->toSkip = array();

        return $res;
    }

    /**
     * chained method that append a field or fields to skipping
     *
     * @param mixed|array|false $field field name; array of names; empty skipping if false
     *
     * @return $this
     */
    public function skip($field)
    {
        if (is_array($field)) {
            foreach ($field as $f) {
                $this->toSkip[] = $f;
            }
        } elseif ($field === FALSE) {
            $this->toSkip = array();
        } else {
            $this->toSkip[] = $field;
        }

        return $this;
    }

    /**
     * Function byId - Get object by primary key.
     *
     * @param mixed $id     Primary Key
     * @param mixed $fields Array or coma separated list of fields to fetch
     *
     * @return $this|null
     * @author   : 713uk13m <dev@nguyenanhung.com>
     * @copyright: 713uk13m <dev@nguyenanhung.com>
     * @time     : 08/21/2021 00:20
     * @throws \Exception
     */
    private function byId($id, $fields = NULL)
    {
        $this->db->where(MysqliDb::$prefix . $this->dbTable . '.' . $this->primaryKey, $id);

        return $this->getOne($fields);
    }

    /**
     * Convinient function to fetch one object. Mostly will be togeather with where()
     *
     * @access public
     *
     * @param array|string $fields Array or coma separated list of fields to fetch
     *
     * @return dbObject
     * @throws \Exception
     */
    protected function getOne($fields = NULL)
    {
        $this->processHasOneWith();
        $results = $this->db->ArrayBuilder()->getOne($this->dbTable, $fields);
        if ($this->db->count == 0)
            return NULL;

        $this->processArrays($results);
        $this->data = $results;
        $this->processAllWith($results);
        if ($this->returnType == 'Json')
            return json_encode($results);
        if ($this->returnType == 'Array')
            return $results;

        $item        = new static ($results);
        $item->isNew = FALSE;

        return $item;
    }

    /**
     * A convenient SELECT COLUMN function to get a single column value from model object
     *
     * @param string $column The desired column
     * @param int    $limit  Limit of rows to select. Use null for unlimited..1 by default
     *
     * @return mixed Contains the value of a returned column / array of values
     * @throws Exception
     */
    protected function getValue($column, $limit = 1)
    {
        $res = $this->db->ArrayBuilder()->getValue($this->dbTable, $column, $limit);
        if (!$res)
            return NULL;

        return $res;
    }

    /**
     * A convenient function that returns TRUE if exists at least an element that
     * satisfy the where condition specified calling the "where" method before this one.
     *
     * @return bool
     * @throws Exception
     */
    protected function has()
    {
        return $this->db->has($this->dbTable);
    }

    /**
     * Fetch all objects
     *
     * @access public
     *
     * @param integer|array $limit  Array to define SQL limit in format Array ($count, $offset)
     *                              or only $count
     * @param array|string  $fields Array or coma separated list of fields to fetch
     *
     * @return array Array of dbObjects
     * @throws \Exception
     */
    protected function get($limit = NULL, $fields = NULL)
    {
        $objects = array();
        $this->processHasOneWith();
        $results = $this->db->ArrayBuilder()->get($this->dbTable, $limit, $fields);
        if ($this->db->count == 0)
            return NULL;

        foreach ($results as $k => &$r) {
            $this->processArrays($r);
            $this->data = $r;
            $this->processAllWith($r, FALSE);
            if ($this->returnType == 'Object') {
                $item        = new static ($r);
                $item->isNew = FALSE;
                $objects[$k] = $item;
            }
        }
        $this->_with = array();
        if ($this->returnType == 'Object')
            return $objects;

        if ($this->returnType == 'Json')
            return json_encode($results);

        return $results;
    }

    /**
     * Function to set witch hasOne or hasMany objects should be loaded togeather with a main object
     *
     * @access public
     *
     * @param string $objectName Object Name
     *
     * @return dbObject
     */
    private function with($objectName)
    {
        if (!property_exists($this, 'relations') || !isset ($this->relations[$objectName]))
            die ("No relation with name $objectName found");

        $this->_with[$objectName] = $this->relations[$objectName];

        return $this;
    }

    /**
     * Function to join object with another object.
     *
     * @access public
     *
     * @param string $objectName Object Name
     * @param string $key        Key for a join from primary object
     * @param string $joinType   SQL join type: LEFT, RIGHT,  INNER, OUTER
     * @param string $primaryKey SQL join On Second primaryKey
     *
     * @return dbObject
     * @throws \Exception
     */
    private function join($objectName, $key = NULL, $joinType = 'LEFT', $primaryKey = NULL)
    {
        $joinObj = new $objectName;
        if (!$key)
            $key = $objectName . "id";

        if (!$primaryKey)
            $primaryKey = MysqliDb::$prefix . $joinObj->dbTable . "." . $joinObj->primaryKey;

        if (!strchr($key, '.'))
            $joinStr = MysqliDb::$prefix . $this->dbTable . ".{$key} = " . $primaryKey;
        else
            $joinStr = MysqliDb::$prefix . "{$key} = " . $primaryKey;

        $this->db->join($joinObj->dbTable, $joinStr, $joinType);

        return $this;
    }

    /**
     * Function to get a total records count
     *
     * @return array|int|mixed
     * @throws \Exception
     */
    protected function count()
    {
        $res = $this->db->ArrayBuilder()->getValue($this->dbTable, "count(*)");
        if (!$res)
            return 0;

        return $res;
    }

    /**
     * Pagination wraper to get()
     *
     * @access public
     *
     * @param int          $page   Page number
     * @param array|string $fields Array or coma separated list of fields to fetch
     *
     * @return array
     * @throws \Exception
     */
    private function paginate($page, $fields = NULL)
    {
        $this->db->pageLimit = self::$pageLimit;
        $objects             = array();
        $this->processHasOneWith();
        $res              = $this->db->paginate($this->dbTable, $page, $fields);
        self::$totalPages = $this->db->totalPages;
        self::$totalCount = $this->db->totalCount;
        if ($this->db->count == 0) return NULL;

        foreach ($res as $k => &$r) {
            $this->processArrays($r);
            $this->data = $r;
            $this->processAllWith($r, FALSE);
            if ($this->returnType == 'Object') {
                $item        = new static ($r);
                $item->isNew = FALSE;
                $objects[$k] = $item;
            }
        }
        $this->_with = array();
        if ($this->returnType == 'Object')
            return $objects;

        if ($this->returnType == 'Json')
            return json_encode($res);

        return $res;
    }

    /**
     * Catches calls to undefined methods.
     *
     * Provides magic access to private functions of the class and native public mysqlidb functions
     *
     * @param string $method
     * @param mixed  $arg
     *
     * @return mixed
     */
    public function __call($method, $arg)
    {
        if (method_exists($this, $method))
            return call_user_func_array(array($this, $method), $arg);

        call_user_func_array(array($this->db, $method), $arg);

        return $this;
    }

    /**
     * Catches calls to undefined static methods.
     *
     * Transparently creating dbObject class to provide smooth API like name::get() name::orderBy()->get()
     *
     * @param string $method
     * @param mixed  $arg
     *
     * @return mixed
     */
    public static function __callStatic($method, $arg)
    {
        $obj    = new static;
        $result = call_user_func_array(array($obj, $method), $arg);
        if (method_exists($obj, $method))
            return $result;

        return $obj;
    }

    /**
     * Converts object data to an associative array.
     *
     * @return array Converted data
     */
    public function toArray()
    {
        $data = $this->data;
        $this->processAllWith($data);
        foreach ($data as &$d) {
            if ($d instanceof DbObject)
                $d = $d->data;
        }

        return $data;
    }

    /**
     * Converts object data to a JSON string.
     *
     * @return string Converted data
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Converts object data to a JSON string.
     *
     * @return string Converted data
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Function queries hasMany relations if needed and also converts hasOne object names
     *
     * @param array $data
     */
    private function processAllWith(&$data, $shouldReset = TRUE)
    {
        if (count($this->_with) == 0)
            return;

        foreach ($this->_with as $name => $opts) {
            $relationType = strtolower($opts[0]);
            $modelName    = $opts[1];
            if ($relationType == 'hasone') {
                $obj        = new $modelName;
                $table      = $obj->dbTable;
                $primaryKey = $obj->primaryKey;

                if (!isset ($data[$table])) {
                    $data[$name] = $this->$name;
                    continue;
                }
                if ($data[$table][$primaryKey] === NULL) {
                    $data[$name] = NULL;
                } else {
                    if ($this->returnType == 'Object') {
                        $item             = new $modelName ($data[$table]);
                        $item->returnType = $this->returnType;
                        $item->isNew      = FALSE;
                        $data[$name]      = $item;
                    } else {
                        $data[$name] = $data[$table];
                    }
                }
                unset ($data[$table]);
            } else
                $data[$name] = $this->$name;
        }
        if ($shouldReset)
            $this->_with = array();
    }

    /*
     * Function building hasOne joins for get/getOne method
     */
    private function processHasOneWith()
    {
        if (count($this->_with) == 0)
            return;
        foreach ($this->_with as $name => $opts) {
            $relationType = strtolower($opts[0]);
            $modelName    = $opts[1];
            $key          = NULL;
            if (isset ($opts[2]))
                $key = $opts[2];
            if ($relationType == 'hasone') {
                $this->db->setQueryOption("MYSQLI_NESTJOIN");
                $this->join($modelName, $key);
            }
        }
    }

    /**
     * @param array $data
     */
    private function processArrays(&$data)
    {
        if (isset ($this->jsonFields) && is_array($this->jsonFields)) {
            foreach ($this->jsonFields as $key)
                $data[$key] = json_decode($data[$key]);
        }

        if (isset ($this->arrayFields) && is_array($this->arrayFields)) {
            foreach ($this->arrayFields as $key)
                $data[$key] = explode("|", $data[$key]);
        }
    }

    /**
     * @param array $data
     */
    private function validate($data)
    {
        if (!$this->dbFields)
            return TRUE;

        foreach ($this->dbFields as $key => $desc) {
            if (in_array($key, $this->toSkip))
                continue;

            $type     = NULL;
            $required = FALSE;
            if (isset ($data[$key]))
                $value = $data[$key];
            else
                $value = NULL;

            if (is_array($value))
                continue;

            if (isset ($desc[0]))
                $type = $desc[0];
            if (isset ($desc[1]) && ($desc[1] == 'required'))
                $required = TRUE;

            if ($required && strlen($value) == 0) {
                $this->errors[] = array($this->dbTable . "." . $key => "is required");
                continue;
            }
            if ($value == NULL)
                continue;

            switch ($type) {
                case "text":
                    $regexp = NULL;
                    break;
                case "int":
                    $regexp = "/^[0-9]*$/";
                    break;
                case "double":
                    $regexp = "/^[0-9\.]*$/";
                    break;
                case "bool":
                    $regexp = '/^(yes|no|0|1|true|false)$/i';
                    break;
                case "datetime":
                    $regexp = "/^[0-9a-zA-Z -:]*$/";
                    break;
                default:
                    $regexp = $type;
                    break;
            }
            if (!$regexp)
                continue;

            if (!preg_match($regexp, $value)) {
                $this->errors[] = array($this->dbTable . "." . $key => "$type validation failed");
                continue;
            }
        }

        return !count($this->errors) > 0;
    }

    private function prepareData()
    {
        $this->errors = array();
        $sqlData      = array();
        if (count($this->data) == 0)
            return array();

        if (method_exists($this, "preLoad"))
            $this->preLoad($this->data);

        if (!$this->dbFields)
            return $this->data;

        foreach ($this->data as $key => &$value) {
            if (in_array($key, $this->toSkip))
                continue;

            if ($value instanceof DbObject && $value->isNew == TRUE) {
                $id = $value->save();
                if ($id)
                    $value = $id;
                else
                    $this->errors = array_merge($this->errors, $value->errors);
            }

            if (!in_array($key, array_keys($this->dbFields)))
                continue;

            if (!is_array($value) && !is_object($value)) {
                $sqlData[$key] = $value;
                continue;
            }

            if (isset ($this->jsonFields) && in_array($key, $this->jsonFields))
                $sqlData[$key] = json_encode($value);
            elseif (isset ($this->arrayFields) && in_array($key, $this->arrayFields))
                $sqlData[$key] = implode("|", $value);
            else
                $sqlData[$key] = $value;
        }

        return $sqlData;
    }

    private static function dbObjectAutoload($classname)
    {
        $filename = static::$modelPath . $classname . ".php";
        if (file_exists($filename))
            include($filename);
    }

    /*
     * Enable models autoload from a specified path
     *
     * Calling autoload() without path will set path to dbObjectPath/models/ directory
     *
     * @param string $path
     */
    public static function autoload($path = NULL)
    {
        if ($path)
            static::$modelPath = $path . "/";
        else
            static::$modelPath = __DIR__ . "/models/";
        spl_autoload_register("DbObject::dbObjectAutoload");
    }
}

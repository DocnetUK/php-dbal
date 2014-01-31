<?php
/**
 * Copyright 2014 Docnet
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Docnet\DB;

use \Docnet\DB;

/**
 * Statement class
 *
 * @author Tom Walder <tom@docnet.nu>
 */
class Statement
{

    /**
     * States for our Statement class
     */
    const STATE_INIT = 1;
    const STATE_PREPARED = 10;
    const STATE_BOUND = 20;
    const STATE_EXECUTED = 30;

    /**
     * MySQLi bind types
     */
    const BIND_TYPE_INTEGER = 'i';
    const BIND_TYPE_STRING = 's';
    const BIND_TYPE_DOUBLE = 'd';
    const BIND_TYPE_BLOB = 'b';

    /**
     * Named parameter pattern
     */
    const NAMED_PARAM_REGEX = "/\?(\w+)/";

    /**
     * Current state
     *
     * @var int
     */
    private $int_state = NULL;

    /**
     * @var \mysqli
     */
    private $obj_db = NULL;

    /**
     * @var \mysqli_stmt
     */
    private $obj_stmt = NULL;

    /**
     * @var string
     */
    private $str_prepare_sql = NULL;

    /**
     * @var array
     */
    private $arr_raw_params = array();

    /**
     * @var array
     */
    private $arr_raw_types = array();

    /**
     * @var string
     */
    private $str_bind_string = '';

    /**
     * @var array
     */
    private $arr_bind_params = array();

    /**
     * The class into which results will be hydrated (presumably triggering
     * the magic __set method in the process)
     *
     * @var string
     */
    private $str_result_class = NULL;

    /**
     * Bind types
     * http://uk3.php.net/manual/en/function.gettype.php
     *
     * Stored as a slightly verbose array for lookup performance later
     *
     * @var array
     */
    private $arr_bind_type_map = array(
        "boolean"       => self::BIND_TYPE_INTEGER,
        "integer"       => self::BIND_TYPE_INTEGER,
        "double"        => self::BIND_TYPE_DOUBLE,
        "string"        => self::BIND_TYPE_STRING,
        "array"         => self::BIND_TYPE_STRING,
        "object"        => self::BIND_TYPE_STRING,
        "resource"      => self::BIND_TYPE_STRING,
        "NULL"          => self::BIND_TYPE_STRING,
        "unknown type"  => self::BIND_TYPE_STRING
    );

    /**
     * If SQL is passed, store for later preparation (it may have named params that need to be replaced)
     *
     * @param \mysqli $obj_db
     * @param string $str_sql
     * @param string $str_result_class
     */
    public function __construct(\mysqli $obj_db, $str_sql, $str_result_class = NULL)
    {
        $this->obj_db = $obj_db;
        $this->str_prepare_sql = $str_sql;
        $this->int_state = self::STATE_INIT;
        $this->str_result_class = $str_result_class;
    }

    /**
     * Execute a query, return the first result.
     *
     * @param Array $arr_params
     * @return Array|NULL
     */
    public function fetchOne($arr_params = NULL)
    {
        if ($this->process($arr_params)) {
            return $this->fetch(DB::FETCH_MODE_ONE);
        }
        return NULL;
    }

    /**
     * Execute a query, return ALL the results.
     *
     * @param Array $arr_params
     * @return array|NULL
     */
    public function fetchAll($arr_params = NULL)
    {
        if ($this->process($arr_params)) {
            return $this->fetch(DB::FETCH_MODE_ALL);
        }
        return NULL;
    }

    /**
     * Execute an update statement
     *
     * @param array $arr_params
     * @return array|null|object
     */
    public function update(array $arr_params = NULL)
    {
        return $this->process($arr_params);
    }

    /**
     * Execute an insert statement
     *
     * @param array $arr_params
     * @return array|null|object
     */
    public function insert(array $arr_params = NULL)
    {
        return $this->process($arr_params);
    }

    /**
     * Execute a delete statement
     *
     * @param array $arr_params
     * @return array|null|object
     */
    public function delete(array $arr_params = NULL)
    {
        return $this->process($arr_params);
    }

    /**
     * Bind, Execute
     *
     * 1. Prepare SQL
     * 2. (if provided) Bind untyped parameters
     *    otherwise bind any previously provided typed parameters
     * // SHORTHAND calls (i.e. where SQL and all Parameters are passed in one call
     * 3. Execute
     *
     * This method used by SELECT/UPDATE/INSERT/DELETE
     *
     * @todo Make this method less bulky!
     *
     * @param null $arr_params
     * @return array|null|object
     */
    private function process($arr_params = NULL)
    {
        if (NULL === $arr_params) {
            if($this->str_prepare_sql) {
                if ($this->int_state === self::STATE_BOUND) {
                    // The NAMED parameters have already been bound to this object using bind*() methods
                    $str_sql = preg_replace_callback(self::NAMED_PARAM_REGEX, array($this, 'applyNamedParam'), $this->str_prepare_sql);
                    $this->str_prepare_sql = NULL;
                    $this->obj_stmt = $this->prepare($str_sql);
                    $this->bindParameters();
                } elseif ($this->int_state === self::STATE_INIT) {
                    // The query does not require params (e.g. "SELECT * from tblData")
                    $this->obj_stmt = $this->prepare($this->str_prepare_sql);
                    $this->str_prepare_sql = NULL;
                }
            }
        } else {
            // Support for single, scalar parameters
            if(!is_array($arr_params)) {
                $arr_params = array($arr_params);
            }
            if($this->isAssoc($arr_params)) {
                // Shorthand, NAMED parameters
                $this->arr_raw_params = $arr_params;
                $str_sql = preg_replace_callback(self::NAMED_PARAM_REGEX, array($this, 'applyNamedParam'), $this->str_prepare_sql);
                $this->str_prepare_sql = NULL;
                $this->obj_stmt = $this->prepare($str_sql);
            } else {
                // Shorthand, unnamed (i.e. numerically indexed)
                // No "preg_replace" needed - parameters must be passed in the correct order
                $this->obj_stmt = $this->prepare($this->str_prepare_sql);
                $this->applyIndexedParams($arr_params);
            }
            $this->bindParameters();
        }
        $this->int_state = self::STATE_EXECUTED;
        return $this->obj_stmt->execute();
    }

    /**
     * Common prepare method which throws an exception
     *
     * @param string $str_sql
     * @return \mysqli_stmt
     * @throws \Exception if the call to mysqli::prepare failed
     */
    private function prepare($str_sql)
    {
        $obj_stmt = $this->obj_db->prepare($str_sql);
        if (!$obj_stmt) {
            throw new \Exception(
                sprintf(
                    'Error preparing statement - Code: %d, Message: "%s"',
                    $this->obj_db->errno,
                    $this->obj_db->error
                )
            );
        }
        $this->int_state = self::STATE_PREPARED;
        return $obj_stmt;
    }

    /**
     * Fetch ONE or ALL results
     *
     * Note: seems you cannot pass NULL or blank string to fetch_object()
     * you must actually NOT pass anything
     *
     * @param int $int_fetch_mode
     * @return array|object
     */
    private function fetch($int_fetch_mode = NULL)
    {
        /** @var  $obj_result \mysqli_result */
        $obj_result = $this->obj_stmt->get_result();
        if ($int_fetch_mode === DB::FETCH_MODE_ONE) {
            if ($this->str_result_class) {
                $obj_row = $obj_result->fetch_object($this->str_result_class);
            } else {
                $obj_row = $obj_result->fetch_object();
            }
            $obj_result->free();
            return $obj_row;
        } else {
            $arr_data = array();
            if ($this->str_result_class) {
                while ($obj_row = $obj_result->fetch_object($this->str_result_class)) {
                    $arr_data[] = $obj_row;
                }
            } else {
                while ($obj_row = $obj_result->fetch_object()) {
                    $arr_data[] = $obj_row;
                }
            }
            $obj_result->free();
            return $arr_data;
        }
    }

    /**
     * Apply parameters from a numerically indexed array
     *
     * Determine type using gettype()
     *
     * @param array $arr_params
     */
    private function applyIndexedParams($arr_params)
    {
        $this->arr_raw_params = $arr_params;
        foreach ($this->arr_raw_params as $mix_key => $mix_param) {
            $this->str_bind_string .= $this->getBindType($mix_param);
            $this->arr_bind_params[] = & $this->arr_raw_params[$mix_key];
        }
    }

    /**
     * Get the mysqli bind type, based on a parameter value
     *
     * @param $mix_param
     * @return string
     */
    private function getBindType($mix_param)
    {
        return $this->arr_bind_type_map[gettype($mix_param)];
    }

    /**
     * Change the class, on a per statement/query level, into which SELECT
     * results are hydrated.
     *
     * @param string $str_result_class the target class
     * @return $this
     * @throws \Exception
     */
    public function setResultClass($str_result_class = NULL)
    {
        if (NULL === $str_result_class || class_exists($str_result_class)) {
            $this->str_result_class = $str_result_class;
            return $this;
        }
        throw new \Exception("Result class does not exist: " . $str_result_class);
    }

    /**
     * Bind a parameter, using Hungarian Notation or "Variable name hinting" for types so stick to
     *
     * int = i,
     * str = s,
     * blb = b,
     * dbl = d
     *
     * @param $str_key
     * @param $mix_val
     * @return $this
     */
    public function bind($str_key, $mix_val)
    {
        $this->int_state = self::STATE_BOUND;
        $this->arr_raw_params[$str_key] = $mix_val;
        return $this;
    }

    /**
     * Bind an Integer parameter
     *
     * @param $str_key
     * @param $int_val
     * @return $this
     */
    public function bindInt($str_key, $int_val)
    {
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_INTEGER;
        return $this->bind($str_key, $int_val);
    }

    /**
     * Bind a String parameter
     *
     * @param $str_key
     * @param $str_val
     * @return $this
     */
    public function bindString($str_key, $str_val)
    {
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_STRING;
        return $this->bind($str_key, $str_val);
    }

    /**
     * Bind a Double parameter
     *
     * @param $str_key
     * @param $dbl_val
     * @return $this
     */
    public function bindDouble($str_key, $dbl_val)
    {
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_DOUBLE;
        return $this->bind($str_key, $dbl_val);
    }

    /**
     * Bind a Blob/binary parameter
     *
     * @param $str_key
     * @param $blb_val
     * @return $this
     */
    public function bindBlob($str_key, $blb_val)
    {
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_BLOB;
        return $this->bind($str_key, $blb_val);
    }

    /**
     * Get the ID from the last insert operation
     *
     * @return int|null
     */
    public function getInsertId()
    {
        if($this->obj_stmt) {
            return $this->obj_stmt->insert_id;
        }
        return NULL;
    }

    /**
     * Get the number of affected rows
     *
     * @return int|null
     */
    public function getAffectedRows()
    {
        if($this->obj_stmt) {
            return $this->obj_stmt->affected_rows;
        }
        return NULL;
    }

    /**
     * Named parameter binding
     *
     * Store the REFERENCE to a raw parameter for passing to call_user_func_array() later
     *
     * @param array $arr_matches
     * @return string
     * @throws \InvalidArgumentException
     */
    private function applyNamedParam($arr_matches)
    {
        $str_name = $arr_matches[1];
        if (isset($this->arr_raw_params[$str_name])) {
            if (isset($this->arr_raw_types[$str_name])) {
                // Hard typed
                $this->str_bind_string .= $this->arr_raw_types[$str_name];
            } elseif(in_array(substr($str_name, 0, 4), array('int_', 'str_', 'dbl_', 'blb_'))) {
                // Type hinted
                $this->str_bind_string .= $str_name[0];
            } else {
                // Determine type from data
                $this->str_bind_string .= $this->getBindType($this->arr_raw_params[$str_name]);
            }
            $this->arr_bind_params[] = & $this->arr_raw_params[$str_name];
            return '?';
        }
        throw new \InvalidArgumentException("Not enough or incorrect named parameters");
    }

    /**
     * Carry out binding the parameters to the prepared statement
     */
    private function bindParameters()
    {
        array_unshift($this->arr_bind_params, $this->str_bind_string);
        call_user_func_array(array($this->obj_stmt, 'bind_param'), $this->arr_bind_params);
    }

    /**
     * Is the array associative?
     *
     * @param array $arr
     * @return bool
     */
    private function isAssoc(array $arr)
    {
        return (gettype(array_keys($arr)[0]) == "string");
    }

    /**
     * Close any open statements on destruction
     */
    public function __destruct()
    {
        if ($this->obj_stmt) {
            $this->obj_stmt->close();
        }
    }
}
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
    private $str_sql = NULL;

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
     * Use functions only supported by mysqlnd?
     *
     * @var bool
     */
    private $bol_use_mysqlnd = FALSE;

    /**
     * The class into which results will be hydrated (presumably triggering
     * the magic __set method in the process)
     *
     * @var string
     */
    private $str_result_class = NULL;

    /**
     * Member variables for statistics
     *
     * @var int
     */
    private static $int_statement = 0;
    private static $int_prepare = 0;
    private static $int_execute = 0;

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
        $this->str_sql = $str_sql;
        $this->int_state = self::STATE_INIT;
        $this->str_result_class = $str_result_class;
        $this->bol_use_mysqlnd = extension_loaded('mysqlnd');
        self::$int_statement++;
    }

    /**
     * Execute a query, return the first result.
     *
     * @param array $arr_params
     * @return array|object|null
     */
    public function fetchOne($arr_params = NULL)
    {
        return $this->processAndFetch($arr_params, DB::FETCH_MODE_ONE);
    }

    /**
     * Execute a query, return ALL the results.
     *
     * @param array $arr_params
     * @return array|NULL
     */
    public function fetchAll($arr_params = NULL)
    {
        return $this->processAndFetch($arr_params, DB::FETCH_MODE_ALL);
    }

    /**
     * Execute an update statement
     *
     * @param array $arr_params
     * @return bool
     */
    public function update(array $arr_params = NULL)
    {
        return $this->process($arr_params);
    }

    /**
     * Execute an insert statement
     *
     * @param array $arr_params
     * @return bool
     */
    public function insert(array $arr_params = NULL)
    {
        return $this->process($arr_params);
    }

    /**
     * Execute a delete statement
     *
     * @param array $arr_params
     * @return bool
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
     * 3. Execute
     *
     * This method used by SELECT/UPDATE/INSERT/DELETE
     *
     * @param null $arr_params
     * @return bool
     * @throws \Exception if fails to process the prepared statement
     */
    private function process($arr_params = NULL)
    {
        if (NULL === $arr_params || (is_array($arr_params) && count($arr_params) == 0)) {
            if ($this->str_sql) {
                if ($this->int_state === self::STATE_BOUND) {
                    // The NAMED parameters have already been bound to this object using bind*() methods
                    $this->str_sql = preg_replace_callback(self::NAMED_PARAM_REGEX, array($this, 'applyNamedParam'), $this->str_sql);
                    $this->prepare();
                    $this->bindParameters();
                } elseif ($this->int_state === self::STATE_INIT) {
                    // The query does not require params (e.g. "SELECT * from tblData")
                    $this->prepare();
                }
            }
        } else {
            $this->arr_raw_params = $arr_params;
            if (!is_array($this->arr_raw_params)) {
                // Support for single, scalar parameters.
                $this->str_bind_string = $this->getBindType($this->arr_raw_params);
                $this->arr_bind_params[] = & $this->arr_raw_params;
            } elseif ($this->isAssoc($arr_params)) {
                // Shorthand, NAMED parameters
                $this->str_sql = preg_replace_callback(self::NAMED_PARAM_REGEX, array($this, 'applyNamedParam'), $this->str_sql);
            } else {
                // Shorthand, unnamed (i.e. numerically indexed) - parameters must be passed in the correct order
                foreach ($this->arr_raw_params as $int_key => $mix_param) {
                    $this->str_bind_string .= $this->getBindType($mix_param);
                    $this->arr_bind_params[] = & $this->arr_raw_params[$int_key];
                }
            }
            $this->prepare();
            $this->bindParameters();
        }
        $this->int_state = self::STATE_EXECUTED;
        self::$int_execute++;

        $bol_result = $this->obj_stmt->execute();

        if ($this->obj_stmt->errno != 0) {
            $str_message = sprintf(
                'Error processing statement - Code: %d, Message: "%s"',
                $this->obj_stmt->errno,
                $this->obj_stmt->error
            );
            throw DB\Exception\Factory::build($str_message, $this->obj_stmt->errno);
        }

        return $bol_result;
    }

    /**
     * Common prepare method which throws an exception if it cannot, in fact,
     * prepare
     *
     * @throws \Exception if the call to mysqli::prepare failed
     */
    private function prepare()
    {
        self::$int_prepare++;
        $this->obj_stmt = $this->obj_db->prepare($this->str_sql);
        if (!$this->obj_stmt) {
            $str_message = sprintf(
               'Error preparing statement - Code: %d, Message: "%s"',
               $this->obj_db->errno,
               $this->obj_db->error
            );
            throw DB\Exception\Factory::build($str_message, $this->obj_db->errno);
        }
        $this->int_state = self::STATE_PREPARED;
        $this->str_sql = NULL;
    }

    /**
     * Process & Fetch ONE or ALL results
     *
     * @param array $arr_params
     * @param int $int_fetch_mode
     * @return array|object|null
     */
    private function processAndFetch($arr_params, $int_fetch_mode)
    {
        if (!$this->process($arr_params)) {
            return NULL;
        }
        if ($this->bol_use_mysqlnd) {
            return $this->fetchNative($int_fetch_mode);
        } else {
            return $this->fetchOldSchool($int_fetch_mode);
        }
    }

    /**
     * Fetch using mysql native driver functions
     *
     * Note: seems you CANNOT pass NULL or blank string to fetch_object()
     * you must actually NOT pass anything
     *
     * @param $int_fetch_mode
     * @return array|object|\stdClass
     */
    private function fetchNative($int_fetch_mode)
    {
        /** @var  $obj_result \mysqli_result */
        $obj_result = $this->obj_stmt->get_result();
        if (DB::FETCH_MODE_ONE === $int_fetch_mode) {
            if ($this->str_result_class) {
                $mix_data = $obj_result->fetch_object($this->str_result_class);
            } else {
                $mix_data = $obj_result->fetch_object();
            }
        } else {
            $mix_data = array();
            if ($this->str_result_class) {
                while ($obj_row = $obj_result->fetch_object($this->str_result_class)) {
                    $mix_data[] = $obj_row;
                }
            } else {
                while ($obj_row = $obj_result->fetch_object()) {
                    $mix_data[] = $obj_row;
                }
            }
        }
        $obj_result->free();
        return $mix_data;
    }

    /**
     * Fetch for non-mysqlnd environments
     *
     * @todo review support for custom classes
     * @todo review pros/cons of using store_result()
     * @todo fix statements using AS
     *
     * @param $int_fetch_mode
     * @return array|null|object|\stdClass
     */
    private function fetchOldSchool($int_fetch_mode)
    {
        $this->obj_stmt->store_result();
        $obj_meta = $this->obj_stmt->result_metadata();
        $arr_fields = $obj_meta->fetch_fields();
        $obj_result = (NULL !== $this->str_result_class ? new $this->str_result_class() : new \stdClass());
        $arr_bind_fields = array();
        foreach ($arr_fields as $obj_field) {
            $arr_bind_fields[] = & $obj_result->{$obj_field->name};
        }
        call_user_func_array(array($this->obj_stmt, 'bind_result'), $arr_bind_fields);
        if (DB::FETCH_MODE_ONE === $int_fetch_mode) {
            if ($this->obj_stmt->fetch()) {
                $mix_data = $obj_result;
            } else {
                $mix_data = NULL;
            }
        } else {
            $mix_data = array();
            while ($this->obj_stmt->fetch()) {
                // Manual clone method - nasty, but required because of all the binding references
                // to avoid each row being === the last row in the result set
                $obj_row = (NULL !== $this->str_result_class ? new $this->str_result_class() : new \stdClass());
                foreach ($arr_fields as $obj_field) {
                    $obj_row->{$obj_field->name} = $obj_result->{$obj_field->name};
                }
                $mix_data[] = $obj_row;
            }
        }
        $this->obj_stmt->free_result();
        return $mix_data;
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
     * Bind a parameter, optionally using Hungarian Notation or
     * "Variable name prefix" type hinting - if so, stick to:
     *
     * int_* = i (Integers)
     * str_* = s (Strings)
     * dbl_* = d (Floats)
     * blb_* = b (Blob/Binary)
     *
     * Also called internally by the bind<Type>() methods
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
        if ($this->obj_stmt) {
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
        if ($this->obj_stmt) {
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
        if (isset($this->arr_raw_params[$str_name]) || array_key_exists($str_name, $this->arr_raw_params)) {
            if (isset($this->arr_raw_types[$str_name])) {
                // Hard typed
                $this->str_bind_string .= $this->arr_raw_types[$str_name];
            } elseif (in_array(substr($str_name, 0, 4), array('int_', 'str_', 'dbl_', 'blb_'))) {
                // Type hinted
                $this->str_bind_string .= $str_name[0];
            } else {
                // Determine type from data
                $this->str_bind_string .= $this->getBindType($this->arr_raw_params[$str_name]);
            }
            $this->arr_bind_params[] = & $this->arr_raw_params[$str_name];
            return '?';
        }
        throw new \InvalidArgumentException("Named parameter not found when looking for: " . $str_name);
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
        $arr_keys = array_keys($arr);
        return (gettype($arr_keys[0]) == "string");
    }

    /**
     * Return statistical data
     *
     * @return array
     */
    public static function getStats()
    {
        return array(
            'objects' => self::$int_statement,
            'prepare' => self::$int_prepare,
            'execute' => self::$int_execute
        );
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

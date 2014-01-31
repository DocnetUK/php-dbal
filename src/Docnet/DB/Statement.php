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
 * @todo Abstract out the fetch type/classname to a method and config object
 *
 * @author Tom Walder <tom@docnet.nu>
 */
class Statement
{

    /**
     * States for our Statement class
     */
    const STATE_RESET = 1;
    const STATE_PREPARED = 2;
    const STATE_BOUND = 3;
    const STATE_EXECUTED = 5;

    /**
     * MySQLi bind types
     */
    const BIND_TYPE_INTEGER = 'i';
    const BIND_TYPE_STRING = 's';
    const BIND_TYPE_DOUBLE = 'd';
    const BIND_TYPE_BLOB = 'b';

    /**
     * Current state
     *
     * @var int
     */
    private $int_state = self::STATE_RESET;


    /**
     * @var \mysqli
     */
    private $obj_db = NULL;

    /**
     * @var \mysqli_result
     */
    private $obj_result = NULL;

    /**
     * @var \mysqli_stmt
     */
    private $obj_stmt = NULL;

    /**
     * @var string
     */
    private $str_prepare_sql = '';

    /**
     * @var array
     */
    protected $arr_raw_params = array();
    /**
     * @var array
     */
    protected $arr_raw_types = array();

    /**
     * @var string
     */
    protected $str_bind_string = '';

    /**
     * @var array
     */
    protected $arr_bind_params = array();

    /**
     * The class into which results will be hydrated (presumably triggering
     * the magic __set method in the process)
     *
     * @var string
     */
    protected $str_result_class = NULL;

    /**
     * If SQL is passed, store for later preparation (it may have named params that need to be replaced)
     *
     * @param \mysqli $obj_db
     * @param string $str_sql
     * @param string $str_result_class
     */
    public function __construct(\mysqli $obj_db, $str_sql = NULL, $str_result_class = NULL)
    {
        $this->obj_db = $obj_db;
        if(NULL !== $str_sql) {
            $this->str_prepare_sql = $str_sql;
            $this->int_state = self::STATE_PREPARED;
        }
        $this->str_result_class = $str_result_class;
    }

    /**
     * Execute a query, return the first result.
     *
     * @param String $str_sql
     * @param Array $arr_params
     * @return Array|NULL
     */
    public function fetchOne($str_sql = NULL, $arr_params = NULL)
    {
        if ($this->process($str_sql, $arr_params)) {
            return $this->fetch(DB::FETCH_MODE_ONE);
        }
        return NULL;
    }

    /**
     * Execute a query, return ALL the results.
     *
     * @param String $str_sql
     * @param Array $arr_params
     * @return array|NULL
     */
    public function fetchAll($str_sql = NULL, $arr_params = NULL)
    {
        if ($this->process($str_sql, $arr_params)) {
            return $this->fetch(DB::FETCH_MODE_ALL);
        }
        return NULL;
    }

    public function update($str_sql = NULL, $arr_params = NULL) {
        return $this->process($str_sql, $arr_params, true);
    }

    public function insert($str_sql = NULL, $arr_params = NULL) {
        return $this->process($str_sql, $arr_params, true);
    }

    public function delete($str_sql = NULL, $arr_params = NULL) {
        return $this->process($str_sql, $arr_params, true);
    }

    /**
     * Bind, Execute
     *
     * 1. (if provided) Reset & prepare SQL
     * 2. (if provided) Bind untyped parameters
     *    otherwise bind any typed parameters
     * 3. Execute
     *
     * This method should now be usable by UPDATE/INSERT/DELETE
     *
     * @param null $str_sql
     * @param null $arr_params
     * @return array|null|object
     */
    private function process($str_sql = NULL, $arr_params = NULL)
    {
        if (NULL !== $str_sql) {
            // The SQL passed into this method CANNOT contain named parameters
            // If we're being given SQL at this stage, reset & prepare
            $this->reset();
            $this->obj_stmt = $this->prepare($str_sql);
        }
        if (NULL === $arr_params) {
            // No parameters, so EITHER
            // a) the query does not require params (e.g. "SELECT * from tbl")
            // b) the NAMED parameters have already been bound to this object
            if ($this->str_prepare_sql !== NULL) {
            if($this->int_state === self::STATE_BOUND) {
                $str_sql = preg_replace_callback("/\?(\w+)/", array($this, 'replaceTypedParams'), $this->str_prepare_sql);
                    $this->str_prepare_sql = NULL;
                $this->obj_stmt = $this->prepare($str_sql);
                $this->bindParameters();
                } elseif ($this->int_state === self::STATE_PREPARED) {
                    $this->obj_stmt = $this->prepare($this->str_prepare_sql);
                    $this->str_prepare_sql = NULL;
                }
            }
        } else {
            // The parameters passed into this method SHOULD NOT be named, so bind them as such
            $this->processUntypedParams($arr_params);
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
        return $obj_stmt;
    }


    /**
     * Fetch ONE or ALL results
     *
     * Note: seems you cannot pass NULL or blank string to fetch_object -
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
            $this->obj_stmt->close();
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
            $this->obj_stmt->close();
            return $arr_data;
        }
    }

    /**
     * Determine type as best we can (no blob for now, based on length or byte check?)
     *
     * @param array $arr_params
     */
    private function processUntypedParams($arr_params)
    {
        $this->arr_raw_params = $arr_params;
        foreach ($this->arr_raw_params as $int_key => $mix_param) {
            switch (gettype($mix_param)) {
                case "boolean":
                case "integer":
                    $this->str_bind_string .= self::BIND_TYPE_INTEGER;
                    break;
                case "double":
                    $this->str_bind_string .= self::BIND_TYPE_DOUBLE;
                    break;
                case "string":
                default:
                    $this->str_bind_string .= self::BIND_TYPE_STRING;
            }
            $this->arr_bind_params[] = & $this->arr_raw_params[$int_key];
        }
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
     * Bind a parameter, using Hungarian Notation or "Variable name hinting" for types
     *
     *  so stick to
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
        $this->int_state = self::STATE_BOUND;
        $this->arr_raw_params[$str_key] = $int_val;
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_INTEGER;
        return $this;
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
        $this->int_state = self::STATE_BOUND;
        $this->arr_raw_params[$str_key] = $str_val;
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_STRING;
        return $this;
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
        $this->int_state = self::STATE_BOUND;
        $this->arr_raw_params[$str_key] = $dbl_val;
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_DOUBLE;
        return $this;
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
        $this->int_state = self::STATE_BOUND;
        $this->arr_raw_params[$str_key] = $blb_val;
        $this->arr_raw_types[$str_key] = self::BIND_TYPE_BLOB;
        return $this;
    }

    /**
     * Used by preg_replace_callback() to match named parameters
     *
     * Uses the first character of the named parameter string (unless Typed Binding has been used) to determine data type
     *
     * @param $arr_matches
     * @return string
     * @throws \InvalidArgumentException
     */
    private function replaceTypedParams($arr_matches)
    {
        $str_key = $arr_matches[1];
        if(isset($this->arr_raw_params[$str_key])) {

            // Hard Typed or prefixed?
            if(isset($this->arr_raw_types[$str_key])) {
                $this->str_bind_string .= $this->arr_raw_types[$str_key];
            } else {
                // String array access for sped
                $this->str_bind_string .= $str_key[0];
            }

            // Store the REFERENCE to the raw parameter for passing to call_user_func_array() later
            $this->arr_bind_params[] = & $this->arr_raw_params[$arr_matches[1]];

            // swap out the "?field" for just "?" in the SQL string for MySQLi binding later
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
     * Reset the object
     */
    private function reset()
    {
        $this->int_state = self::STATE_RESET;
        $this->obj_stmt = NULL;
        $this->obj_result = NULL;
        $this->str_prepare_sql = '';
        $this->arr_raw_params = NULL;
        $this->str_bind_string = '';
        $this->arr_bind_params = array();
    }

}
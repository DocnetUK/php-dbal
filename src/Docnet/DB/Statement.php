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
     * @var null
     */
    private $int_fetch_mode = NULL;

    /**
     * @var \mysqli
     */
    private $obj_db = NULL;

    /**
     * @var \mysqli_result
     */
    private $obj_result = NULL;

    /**
     * @var null
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
     * @var string
     */
    protected $str_bind_string = '';

    /**
     * @var array
     */
    protected $arr_bind_params = array();

    /**
     * If SQL is passed, store for later preparation
     */
    public function __construct($obj_db, $str_sql = NULL)
    {
        $this->obj_db = $obj_db;
        $this->reset();
        if(NULL !== $str_sql) {
            $this->str_prepare_sql = $str_sql;
            $this->int_state = self::STATE_PREPARED;
        }
    }

    /**
     * Execute a query, return the first result
     *
     * @param String $str_sql
     * @param Array $arr_params
     * @return Array|NULL
     */
    public function fetchOne($str_sql = NULL, $arr_params = NULL)
    {
        $this->int_fetch_mode = \Docnet\DB::FETCH_MODE_ONE;
        return $this->process($str_sql, $arr_params);
    }

    /**
     * Execute a query, return ALL the results
     */
    public function fetchAll($str_sql = NULL, $arr_params = NULL)
    {
        $this->int_fetch_mode = \Docnet\DB::FETCH_MODE_ALL;
        return $this->process($str_sql, $arr_params);
    }

    /**
     * Bind (if required), Execute, Fetch
     *
     * @param null $str_sql
     * @param null $arr_params
     * @return array|null|object
     */
    private function process($str_sql = NULL, $arr_params = NULL) {
        if (NULL === $str_sql && NULL === $arr_params) {
            // If our internal state is 'BOUND' then we need to do the mysqli binding next...
            if($this->int_state === self::STATE_BOUND) {
                $str_sql = preg_replace_callback("/\?(\w+)/", array($this, 'replaceTypedParams'), $this->str_prepare_sql);
                $this->obj_stmt = $this->obj_db->prepare($str_sql);
                $this->bindParameters();
            }
        } else {
            // Got an SQL string and some UNNAMED, UNTYPED params
            $this->reset();
            $this->processUntypedParams($arr_params);
            $this->obj_stmt = $this->obj_db->prepare($str_sql);
            $this->bindParameters();
        }
        if($this->execute()) {
            return $this->fetch();
        }
        return NULL;
    }

    /**
     * Just execute
     *
     * No result fetching - generally for inserts/updates
     *
     * @return boolean
     */
    public function execute()
    {
        $this->int_state = self::STATE_EXECUTED;
        return $this->obj_stmt->execute();
    }

    /**
     * Fetch ONE or ALL results
     *
     * @return array|object
     */
    private function fetch()
    {
        /** @var  $obj_result \mysqli_result */
        $obj_result = $this->obj_stmt->get_result();
        if ($this->int_fetch_mode === \Docnet\DB::FETCH_MODE_ONE) {
            $obj_row = $obj_result->fetch_object('\Docnet\DB\Model');
            $obj_result->free();
            $this->obj_stmt->close();
            return $obj_row;
        } else {
            $arr_data = array();
            while ($obj_row = $obj_result->fetch_object('\Docnet\DB\Model')) {
                $arr_data[] = $obj_row;
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
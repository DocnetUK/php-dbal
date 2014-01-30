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

namespace Docnet;

/**
 * Easy DB
 *
 * @todo move connection data to config object
 *
 * @author Tom Walder <tom@docnet.nu>
 */
class DB
{

    /**
     * Fetch modes
     */
    const FETCH_MODE_ONE = 1;
    const FETCH_MODE_ALL = 2;

    /**
     * @var \Docnet\DB
     */
    private static $obj_instance = NULL;

    /**
     * @var \mysqli
     */
    private $obj_db = NULL;

    /**
     * @var null
     */
    private $int_fetch_mode = NULL;

    /**
     * @var bool
     */
    private $bol_in_transaction = false;

    /**
     * Connect on construction
     *
     * @throws \Exception
     */
    public function __construct($str_host, $str_user, $str_pass, $str_db = NULL, $int_port = NULL, $int_socket = NULL)
    {
        $this->obj_db = new \mysqli($str_host, $str_user, $str_pass, $str_db, $int_port, $int_socket);
        if ($this->obj_db->connect_error) {
            throw new \Exception('Connect Error (' . $this->obj_db->connect_errno . ') ' . $this->obj_db->connect_error);
        }
    }

    /**
     * Start a transaction. While MySQL does support "Nested Transactions" of
     * sorts (see http://dev.mysql.com/doc/refman/5.0/en/savepoint.html), we're
     * not going to support them here. If we're already in a transaction,
     * silently ignore the request.
     *
     * @throws \Exception if mysqli::begin_transaction() returned false
     */
    public function begin() {
        if ($this->bol_in_transaction) {
            return;
        }

        if (!$this->obj_db->begin_transaction()) {
            throw new \Exception("Failed to start a transaction");
        } else {
            $this->bol_in_transaction = true;
        }
    }

    /**
     * Commit a transaction. If we're not in transaction, throw an exception.
     * It's important that the calling code knows it's not in a transaction if
     * the deveoper assumed that they were.
     *
     * @todo Are we scared that bol_in_transaction might get out of sync and
     * prevent legit commits?
     * @throws \Exception if we're not in a transaction
     * @throws \Exception if the driver reports that the commit failed
     */
    public function commit() {
        if (!$this->bol_in_transaction) {
            throw new \Exception("Not in a transaction, can't commit");
        }
        if (!$this->obj_db->commit()) {
            throw new \Exception("MySQL failed to commit the transaction");
        }
    }

    /**
     * Rollback a transaction.
     * @throws \Exception if we're not in a transaction
     * @throws \Exception if the driver reports that the rollback failed
     */
    public function rollback() {
        if (!$this->bol_in_transaction) {
            throw new \Exception("Not in a transaction, can't rollback");
        }
        if (!$this->obj_db->rollback()) {
            throw new \Exception("MySQL failed to rollback the transaction");
        }
    }


    /**
     * Execute a query, return the results as an array
     *
     * @param String $str_sql
     * @param Array $arr_params
     * @return Array|NULL
     */
    public function fetchAll($str_sql, $arr_params = NULL)
    {
        $this->int_fetch_mode = self::FETCH_MODE_ALL;
        return $this->delegateFetch($str_sql, $arr_params);
    }

    /**
     * Execute a query, return the first result
     *
     * @param String $str_sql
     * @param Array $arr_params
     * @return Array|NULL
     */
    public function fetchOne($str_sql, $arr_params = NULL)
    {
        $this->int_fetch_mode = self::FETCH_MODE_ONE;
        return $this->delegateFetch($str_sql, $arr_params);
    }

    /**
     * Direct or Statement
     *
     * @param $str_sql
     * @param null $arr_params
     * @return Array|NULL|void
     */
    private function delegateFetch($str_sql, $arr_params = NULL)
    {
        if (NULL === $arr_params) {
            return $this->fetchDirect($str_sql);
        } else {
            $obj_stmt = new DB\Statement($this->obj_db);
            if ($this->int_fetch_mode === self::FETCH_MODE_ONE) {
                return $obj_stmt->fetchOne($str_sql, $arr_params);
            } else {
                return $obj_stmt->fetchAll($str_sql, $arr_params);
            }
        }
    }

    /**
     * Direct query & fetch
     *
     * @param $str_sql
     * @return array|null|object|\stdClass
     */
    private function fetchDirect($str_sql)
    {
        if ($obj_result = $this->obj_db->query($str_sql)) {
            if ($this->int_fetch_mode === self::FETCH_MODE_ONE) {
                $obj_row = $obj_result->fetch_object('\Docnet\DB\Model');
                $obj_result->free();
                return $obj_row;
            } else {
                $arr_data = array();
                while ($obj_row = $obj_result->fetch_object('\Docnet\DB\Model')) {
                    $arr_data[] = $obj_row;
                }
                $obj_result->free();
                return $arr_data;
            }
        }
        return NULL;
    }

    /**
     * Set up a Statement
     *
     * @param $str_sql
     * @return $this
     */
    public function prepare($str_sql)
    {
        $obj_stmt = new DB\Statement($this->obj_db, $str_sql);
        return $obj_stmt;
    }

    /**
     * Escape a string - utility method. Prefer using prepare/bind/fetch
     *
     * @param type $str
     * @return type
     */
    public function escape($str)
    {
        return $this->obj_db->escape_string($str);
    }

    /**
     * Run arbitrary SQL. CAREFUL!
     *
     * @param string $sql
     * @return bool|\mysqli_result
     */
    public function query($sql)
    {
        return $this->obj_db->query($sql);
    }

    /**
     * Get the Singleton instance
     *
     * @return \Docnet\DB
     */
    public static function instance()
    {
        if (NULL === self::$obj_instance) {
            self::$obj_instance = new DB();
        }
        return self::$obj_instance;
    }

    /**
     * Clean up on destruct
     */
    public function __destruct()
    {
        if ($this->obj_db) {
            $this->obj_db->close();
        }
    }

}
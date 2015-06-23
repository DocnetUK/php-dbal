<?php
/**
 * Copyright 2015 Docnet
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

use Docnet\DB\ConnectionSettingsInterface;

/**
 * DB stuff
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
     * @var \mysqli
     */
    private $obj_db = null;

    /**
     * Are we in a transaction?
     *
     * @var bool
     */
    private $bol_in_transaction = false;

    /**
     * Connection settings required on construction
     *
     * @param DB\ConnectionSettingsInterface $obj_settings
     * @throws \Exception
     */
    public function __construct(ConnectionSettingsInterface $obj_settings)
    {
        $this->obj_db = new \mysqli($obj_settings->getHost(), $obj_settings->getUser(), $obj_settings->getPass(),
           $obj_settings->getDbName(), $obj_settings->getPort(), $obj_settings->getSocket());
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
     * @returns \Docnet\Db
     * @throws \Exception if mysqli::begin_transaction() returned false
     * @since 19/May/14 support for PHP 5.4 using query('BEGIN')
     */
    public function begin()
    {
        if ($this->bol_in_transaction) {
            return $this;
        }

        if (PHP_VERSION_ID >= 50500) {
            $bol_success = $this->obj_db->begin_transaction();
        } else {
            $bol_success = $this->obj_db->query('BEGIN');
        }

        if ($bol_success) {
            $this->bol_in_transaction = true;
        } else {
            throw new \Exception("Failed to start a transaction");
        }

        return $this;
    }

    /**
     * Commit a transaction. If we're not in transaction, throw an exception.
     * It's important that the calling code knows it's not in a transaction if
     * the developer assumed that they were.
     *
     * @todo Are we scared that bol_in_transaction might get out of sync and
     * prevent legit commits?
     *
     * @return \Docnet\DB
     * @throws \Exception if we're not in a transaction
     * @throws \Exception if the driver reports that the commit failed
     */
    public function commit()
    {
        if (!$this->bol_in_transaction) {
            throw new \Exception("Not in a transaction, can't commit");
        }
        if (!$this->obj_db->commit()) {
            throw new \Exception("MySQL failed to commit the transaction");
        }
        $this->bol_in_transaction = false;

        return $this;
    }

    /**
     * Rollback a transaction.
     *
     * @returns \Docnet\DB
     * @throws \Exception if we're not in a transaction
     * @throws \Exception if the driver reports that the rollback failed
     */
    public function rollback()
    {
        if (!$this->bol_in_transaction) {
            throw new \Exception("Not in a transaction, can't rollback");
        }
        if (!$this->obj_db->rollback()) {
            throw new \Exception("MySQL failed to rollback the transaction");
        }
        $this->bol_in_transaction = false;

        return $this;
    }

    /**
     * Delegate to Statement::insert()
     *
     * @param $str_sql
     * @param $arr_params
     * @return integer Insert ID or TRUE/FALSE if no ID was generated, based on the rows affected being > 0
     * @see update()
     */
    public function insert($str_sql, $arr_params)
    {
        $obj_stmt = new DB\Statement($this->obj_db, $str_sql);
        $obj_stmt->insert($arr_params);
        $int_id = $obj_stmt->getInsertId();
        if ($int_id > 0) {
            return $int_id;
        }

        return ($obj_stmt->getAffectedRows() > 0);
    }

    /**
     * Delegate to the Statement::update() method. Worth intentionally keeping
     * this level of indirection incase we want to change the behaviour of each
     * DML query.
     *
     * @param $str_sql
     * @param $arr_params
     * @return int
     */
    public function update($str_sql, $arr_params)
    {
        $obj_stmt = new DB\Statement($this->obj_db, $str_sql);
        $obj_stmt->update($arr_params);

        return $obj_stmt->getAffectedRows();
    }

    /**
     * Delegate to the Statement::delete() method. See notes on indirection in
     * DB::update().
     *
     * @param $str_sql
     * @param $arr_params
     * @return int
     */
    public function delete($str_sql, $arr_params)
    {
        $obj_stmt = new DB\Statement($this->obj_db, $str_sql);
        $obj_stmt->delete($arr_params);

        return $obj_stmt->getAffectedRows();
    }

    /**
     * Execute a query, return the results as an array
     *
     * @param String $str_sql
     * @param Array $arr_params
     * @param String $str_result_class manually override result class (just for
     * this query)
     * @return Array|NULL
     */
    public function fetchAll($str_sql, $arr_params = null, $str_result_class = null)
    {
        return $this->delegateFetch($str_sql, $arr_params, $str_result_class, self::FETCH_MODE_ALL);
    }

    /**
     * Execute a query, return the first result
     *
     * @param String $str_sql
     * @param Array $arr_params
     * @param String $str_result_class manually override result class (just for
     * this query)
     * @return Array|NULL
     */
    public function fetchOne($str_sql, $arr_params = null, $str_result_class = null)
    {
        return $this->delegateFetch($str_sql, $arr_params, $str_result_class, self::FETCH_MODE_ONE);
    }

    /**
     * Create, configure and call Statement
     *
     * @param $str_sql
     * @param null $arr_params
     * @param String $str_result_class
     * @param int $int_fetch_mode
     * @return Array|NULL|void
     */
    private function delegateFetch($str_sql, $arr_params, $str_result_class, $int_fetch_mode)
    {
        $obj_stmt = new DB\Statement($this->obj_db, $str_sql);
        if ($str_result_class) {
            $obj_stmt->setResultClass($str_result_class);
        }
        if (self::FETCH_MODE_ONE === $int_fetch_mode) {
            return $obj_stmt->fetchOne($arr_params);
        } else {
            return $obj_stmt->fetchAll($arr_params);
        }
    }

    /**
     * Set up a Statement
     *
     * @param $str_sql
     * @return \Docnet\DB\Statement
     * @throws \InvalidArgumentException
     */
    public function prepare($str_sql = null)
    {
        if (null === $str_sql) {
            throw new \InvalidArgumentException("SQL required for prepare() call");
        }
        $obj_stmt = new DB\Statement($this->obj_db, $str_sql);

        return $obj_stmt;
    }

    /**
     * Escape a string - utility method. Prefer using prepare/bind/fetch
     *
     * @param string $str
     * @return string
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
     * Clean up on destruct
     */
    public function __destruct()
    {
        if ($this->obj_db) {
            $this->obj_db->close();
        }
    }

}

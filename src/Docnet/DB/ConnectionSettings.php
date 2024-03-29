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

namespace Docnet\DB;

/**
 * Represents details needed to connect to a MySQL database
 *
 * Note to self: This ~200 line class basically represents an array ;-(
 *
 * @author Tom Walder <tom@docnet.nu>
 */
class ConnectionSettings implements ConnectionSettingsInterface
{
    /**
     * DB host
     *
     * @var string|null
     */
    private $str_host = null;

    /**
     * DB user
     *
     * @var string|null
     */
    private $str_user = null;

    /**
     * DB pass
     *
     * @var string|null
     */
    private $str_pass = null;

    /**
     * DB name
     *
     * @var string|null
     */
    private $str_db = null;

    /**
     * DB port
     *
     * @var string|null
     */
    private $str_port = null;

    /**
     * DB socket
     *
     * @var string|null
     */
    private $str_socket = null;

    /**
     * DB connection flags
     *
     * @var int
     */
    private $int_flags = 0;

    /**
     * Optionally Configure on construction
     *
     * @param string|null $str_host
     * @param string|null $str_user
     * @param string|null $str_pass
     * @param string|null $str_db
     * @param string|null $str_port
     * @param string|null $str_socket
     * @param int $int_flags
     */
    public function __construct(
        $str_host = null,
        $str_user = null,
        $str_pass = null,
        $str_db = null,
        $str_port = null,
        $str_socket = null,
        $int_flags = 0
    ) {
        $this->str_host = $str_host;
        $this->str_user = $str_user;
        $this->str_pass = $str_pass;
        $this->str_db = $str_db;
        $this->str_port = $str_port;
        $this->str_socket = $str_socket;
        $this->int_flags = $int_flags;
    }

    /**
     * Get the DB host
     *
     * @return string|null
     */
    public function getHost()
    {
        return $this->str_host;
    }

    /**
     * Get the DB user
     *
     * @return string|null
     */
    public function getUser()
    {
        return $this->str_user;
    }

    /**
     * Get the password
     *
     * @return string|null
     */
    public function getPass()
    {
        return $this->str_pass;
    }

    /**
     * Get the database name
     *
     * @return string|null
     */
    public function getDbName()
    {
        return $this->str_db;
    }

    /**
     * Get the server port
     *
     * @return string|null
     */
    public function getPort()
    {
        return $this->str_port;
    }

    /**
     * Get the server socket
     *
     * @return string|null
     */
    public function getSocket()
    {
        return $this->str_socket;
    }

    /**
     * Get the connection flags
     *
     * @return int
     */
    public function getFlags()
    {
        return $this->int_flags;
    }

    /**
     * Set the DB host
     *
     * @param $str_host
     * @return self
     */
    public function setHost($str_host)
    {
        $this->str_host = $str_host;
        return $this;
    }

    /**
     * Set the DB user
     *
     * @param $str_user
     * @return self
     */
    public function setUser($str_user)
    {
        $this->str_user = $str_user;
        return $this;
    }

    /**
     * Set the DB pass
     *
     * @param $str_pass
     * @return self
     */
    public function setPass($str_pass)
    {
        $this->str_pass = $str_pass;
        return $this;
    }

    /**
     * Set the DB name
     *
     * @param $str_db
     * @return self
     */
    public function setDbName($str_db)
    {
        $this->str_db = $str_db;
        return $this;
    }

    /**
     * Set the DB port
     *
     * @param $str_port
     * @return self
     */
    public function setPort($str_port)
    {
        $this->str_port = $str_port;
        return $this;
    }

    /**
     * Set the DB socket
     *
     * @param $str_socket
     * @return self
     */
    public function setSocket($str_socket)
    {
        $this->str_socket = $str_socket;
        return $this;
    }

    /**
     * Set the DB connection flags
     *
     * @param int $int_flags
     * @return self
     */
    public function setFlags($int_flags)
    {
        $this->int_flags = $int_flags;
        return $this;
    }

}
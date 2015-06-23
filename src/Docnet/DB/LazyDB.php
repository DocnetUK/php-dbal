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

use Docnet\DB;

/**
 * Class LazyDB
 *
 * @author Kamba Abudu <kabudu@docnet.nu>
 * @package Docnet\PSA\API\Traits
 */
class LazyDB
{
    /**
     * DB object
     *
     * @var DB
     */
    private $obj_db = null;

    /**
     * DB connection settings object
     *
     * @var ConnectionSettingsInterface
     */
    private $obj_connection_settings = null;

    /**
     * Constructor
     *
     * @param ConnectionSettingsInterface $obj_connection_settings
     */
    public function __construct(ConnectionSettingsInterface $obj_connection_settings)
    {
        $this->obj_connection_settings = $obj_connection_settings;
    }

    /**
     * Get a DB instance
     *
     * @return DB
     */
    public function getDb()
    {
        if (null === $this->obj_db) {
            $this->obj_db = new DB($this->obj_connection_settings);
        }

        return $this->obj_db;
    }
}
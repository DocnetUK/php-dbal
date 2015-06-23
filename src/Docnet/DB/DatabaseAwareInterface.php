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
 * Interface DatabaseAwareInterface
 *
 * @author Kamba Abudu <kabudu@docnet.nu>
 * @package Docnet\DB
 */
interface DatabaseAwareInterface
{
    /**
     * Set a DB Instance
     *
     * @param DB $obj_db
     * @return mixed
     */
    public function setDb(DB $obj_db);

    /**
     * Set a LazyDB instance
     *
     * @param LazyDB $obj_lazy_db
     * @return mixed
     */
    public function setLazyDb(LazyDB $obj_lazy_db);
}


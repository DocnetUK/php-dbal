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

require('../src/Docnet/DB.php');
require('../src/Docnet/DB/Config.php');
require('../src/Docnet/DB/Statement.php');
require('../src/Docnet/DB/Model.php');

$obj_db = new \Docnet\DB('127.0.0.1', 'root', 'letmein', 'dbCluster');
$arr_objects = array();

/**
 * Simple - no parameters
 */
$arr_objects[] = $obj_db->fetchOne("SELECT * FROM tblServer");


/**
 * Statement/bind Type 1 - unnamed parameters - uses gettype()
 */
$arr_objects[] = $obj_db->fetchOne("SELECT * FROM tblServer WHERE intID = ? AND vchType = ?", array(1, 'web'));


/**
 * Statement/bind Type 2 - named/hungarian parameters - uses key prefix (int/str/dbl/blb)
 */
$arr_objects[] = $obj_db
    ->prepare("SELECT * FROM tblServer WHERE intID = ?int_id AND vchType = ?str_val")
    ->bind('int_id', 2)
    ->bind('str_val', 'web')
    ->fetchOne();

/**
 * Statement/bind Type 3 - named parameters - alternative bindXxxx() methods
 */
$arr_objects[] = $obj_db->prepare("SELECT * FROM tblServer WHERE intID = ?id AND vchType = ?val")
    ->bindInt('id', 2)
    ->bindString('val', 'web')
    ->fetchOne();

print_r($arr_objects);
echo "Done fetchOne() x 4\n";
echo "===================\n\n";



echo "\nfetchAll(), mode 1\n";
print_r($obj_db->fetchAll("SELECT * FROM tblServer"));

echo "\nfetchAll(), mode 2\n";
print_r($obj_db->fetchAll("SELECT * FROM tblServer WHERE vchType = ?", array('web')));

echo "\nfetchAll(), mode 3\n";
$obj_stmt = $obj_db->prepare("SELECT * FROM tblServer WHERE vchType = ?val")->bindString('val', 'web');
print_r($obj_stmt->fetchAll());

echo "\nprepare()->fetchOne();\n";
print_r($obj_db->prepare("SELECT * FROM tblServer")->fetchOne());

echo "\nprepare()->setResultClass()->fetchOne();\n";
print_r($obj_db->prepare("SELECT * FROM tblServer")->setResultClass('\\Docnet\\DB\\Model')->fetchOne());
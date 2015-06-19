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

require('../vendor/autoload.php');

$obj_db = new \Docnet\DB(new \Docnet\DB\ConnectionSettings('127.0.0.1', 'root', 'letmein', 'dbCluster'));
$arr_objects = array();

echo round(memory_get_usage()/1024/1024, 2) . "MB\n";

/**
 * Simple - no parameters
 */
print_r($obj_db->fetchOne("SELECT * FROM tblServer"));

/**
 * Statement/bind Type 1 - indexed parameters
 */
print_r($obj_db->fetchOne("SELECT * FROM tblServer WHERE intID = ? AND vchType = ?", array(1, 'web')));

/**
 * Statement/bind Type 2 - named/hungarian parameters - uses key prefix (int/str/dbl/blb)
 */
print_r(
    $obj_db->prepare("SELECT * FROM tblServer WHERE intID = ?int_id AND vchType = ?str_val")
        ->bind('int_id', 2)
        ->bind('str_val', 'web')
        ->fetchOne()
);

/**
 * Statement/bind Type 3 - named parameters - alternative bindXxxx() methods
 */
print_r(
    $obj_db->prepare("SELECT * FROM tblServer WHERE intID = ?id AND vchType = ?val")
        ->bindInt('id', 2)
        ->bindString('val', 'web')
        ->fetchOne()
);

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

// ==================================

echo "\ninsert, select, delete\n";
$int_id = $obj_db->insert("INSERT INTO tblServer VALUES (NULL, ?, ?, ?)", array('a', 'b', 'c'));
echo "Inserted: {$int_id}\n";
print_r(
    $obj_db->fetchOne("SELECT * FROM tblServer WHERE intID = ?", array($int_id))
);
$int_rows = $obj_db->delete("DELETE FROM tblServer WHERE intID = ?", array($int_id));
echo "Deleting: {$int_rows} rows\n";


// Multi-execute fetch (as per README.md)
$stmt = $obj_db->prepare("SELECT * FROM tblServer WHERE intID = ?id");
print_r($stmt->bindInt('id', 1)->fetchOne());
print_r($stmt->bindInt('id', 2)->fetchOne());

// Multi-execute fetch (as per README.md)
echo "\nMulti-insert, select, delete\n";
$stmt = $obj_db->prepare("INSERT INTO tblServer VALUES (NULL, ?host, ?name, ?type)");
$stmt
    ->bindString('host', 'a')
    ->bindString('name', 'b')
    ->bindString('type', 'test')
    ->insert();
$stmt
    ->bindString('host', 'c')
    ->bindString('name', 'd')
    ->insert();
print_r($obj_db->fetchAll("SELECT * FROM tblServer"));
echo "Deleted rows: " . $obj_db->delete("DELETE FROM tblServer WHERE vchType = ?", array('test'));


// NAMED in a single call
echo "\n\nSingle named shorthand parameter\n";
print_r(
    $obj_db->fetchOne("SELECT * FROM tblServer WHERE intID = ?id",
        array("id" => 1)
    )
);

// scalar params
echo "\n\nSingle shorthand scalar parameter\n";
print_r(
    $obj_db->fetchOne("SELECT * FROM tblServer WHERE intID = ?", 2)
);


echo "\n\n=========================================\n";
echo "Statements: " . print_r(\Docnet\DB\Statement::getStats(), TRUE);
echo "Memory: " . round(memory_get_usage()/1024/1024, 2) . "MB (peak ".round(memory_get_peak_usage()/1024/1024, 2).")\n";

echo "\nDone\n";
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

require('../vendor/autoload.php');

$obj_db = new \Docnet\DB(new \Docnet\DB\ConnectionSettings('127.0.0.1', 'root', 'letmein', 'dbCluster'));
$pad = 40;

$_SERVER['db'] = $obj_db;

echo "\nSINGLE INTEGER PARAM\n";


timeit("single scalar", function(){
    $_SERVER['db']->fetchOne("SELECT * FROM tblServer WHERE intID = ?", mt_rand(1,2));
});

timeit("shorthand array ", function(){
    $_SERVER['db']->fetchOne("SELECT * FROM tblServer WHERE intID = ?", array(mt_rand(1,2)));
});

timeit("shorthand named array ", function(){
    $_SERVER['db']->fetchOne("SELECT * FROM tblServer WHERE intID = ?id", array('id' => mt_rand(1,2)));
});

timeit("prepare()->bindInt()->fetchOne() ", function(){
    $_SERVER['db']->prepare("SELECT * FROM tblServer WHERE intID = ?id")->bindInt('id', mt_rand(1,2))->fetchOne();
});

timeit("prepare()->bindInt()->fetchAll() ", function(){
    $_SERVER['db']->prepare("SELECT * FROM tblServer WHERE intID = ?id")->bindInt('id', mt_rand(1,2))->fetchAll();
});


echo "\nSINGLE STRING PARAM\n";

timeit("single scalar ", function(){
    $_SERVER['db']->fetchOne("SELECT * FROM tblServer WHERE vchHostname = ?", 'test');
});

timeit("shorthand array ", function(){
    $_SERVER['db']->fetchOne("SELECT * FROM tblServer WHERE vchHostname = ?", array('test'));
});

timeit("shorthand named array ", function(){
    $_SERVER['db']->fetchOne("SELECT * FROM tblServer WHERE vchHostname = ?host", array('host' => 'test'));
});

timeit("prepare()->bindString()->fetchOne() ", function(){
    $_SERVER['db']->prepare("SELECT * FROM tblServer WHERE vchHostname = ?host")->bindString('host', 'test')->fetchOne();
});

timeit("prepare()->bindString()->fetchAll() ", function(){
    $_SERVER['db']->prepare("SELECT * FROM tblServer WHERE vchHostname = ?host")->bindString('host', 'test')->fetchAll();
});


/**
 * Time a callback function.
 *
 * Execute callback 'n' times in 'm' loops and calculate the average
 *
 * @param string $str_name
 * @param $callback
 * @param int $int_times
 * @param int $int_loops
 * @return array
 */
function timeit($str_name, $callback, $int_times = 3, $int_loops = 2000) {
    echo str_pad($str_name, 50);
    $arr_times = array();
    $flt_total = 0.0;
    for($n = 0; $n < $int_times; $n++) {
        $start = microtime(TRUE);
        for($i = 0; $i < $int_loops; $i++) {
            $callback();
        }
        $flt_time = round(microtime(TRUE) - $start, 4);
        $flt_total += $flt_time;
        $arr_times[] = $flt_time;
    }
    echo "{$int_times}/avg: " . round($flt_total / $int_times, 4) . "s\n";
}



echo "\n\n=========================================\n";
echo "Statements: " . print_r(\Docnet\DB\Statement::getStats(), TRUE);
echo "Memory: " . round(memory_get_usage()/1024/1024, 2) . "MB (peak ".round(memory_get_peak_usage()/1024/1024, 2).")\n";

echo "\nDone\n";
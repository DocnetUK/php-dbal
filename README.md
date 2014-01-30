# PHP DB Access Layer #

Designed to wrap mysqli into a series of fast, secure and easy to use classes.

## Let's Connect ##

You're gonna need to connect to a DB before you can do anything else...

```php
<?php
$db = new \Docnet\DB('127.0.0.1', 'root', 'password', 'dbname');
```

For the following examples, we'll assume there's an active DB object.

## Example 1 - SELECT Star ##

After this has executed, `$records` will be an array of objects - we'll talk about what type later.

```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData");
```
## Example 2 - SELECT Star with parameter binding ##
```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData WHERE intPrimaryKey > ?", array(3));
var_dump($records); // $records is an array of StdObject objects where intPrimaryKey > 3.
```
## Example 3 - SELECT Star into class ##
```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData WHERE intPrimaryKey > ?", array(3), 'Foo');
var_dump($records); // $records is a array of 'Foo' objects, where intPrimaryKey > 3
```



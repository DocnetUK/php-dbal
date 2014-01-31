# PHP DB Access Layer #

Designed to wrap mysqli into a series of fast, secure and easy to use classes.

## Let's Connect ##

You're gonna need to connect to a DB before you can do anything else...

```php
<?php
$db = new \Docnet\DB('127.0.0.1', 'root', 'password', 'dbname');
```

For the following examples, we'll assume there's an active DB object.

## SELECT Star ##

After this has executed, `$records` will be an array of objects - we'll talk about what type later.

```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData");
```

## SELECT with parameters and custom result Class ##

After execution, `$records` is an array of 'Foo' objects, where intKey > 3 and vchName = Barry

```php
<?php
$records = $db->prepare("SELECT * FROM tblData WHERE intKey > ?id AND vchName = ?name")
   ->bindInt('id', 3)
   ->bindString('name', 'Barry')
   ->setResultClass('Foo')
   ->fetchAll();
var_dump($records);
```
The `prepare()` method returns a fluent `Statement` class which provides named parameter binding.

Parameter binding deals with all escaping and quoting for you.

## SELECT with simple un-named parameter binding ##

```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData WHERE intPrimaryKey > ?", array(3));
var_dump($records); // $records is an array of StdObject objects where intPrimaryKey > 3.
```

## SELECT into class ##

```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData WHERE intPrimaryKey > ?", array(3), 'Foo');
var_dump($records); // $records is a array of 'Foo' objects, where intPrimaryKey > 3
```



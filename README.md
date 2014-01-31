# PHP DB Access Layer #

Designed to wrap mysqli into a series of fast, secure and easy to use classes.

## Let's Connect ##

You're gonna need to connect to a DB before you can do anything else...

```php
<?php
$db = new \Docnet\DB('127.0.0.1', 'root', 'password', 'dbname');
```

For the following examples, we'll assume there's an active DB object.

## My First SELECT ##

After this has executed, `$records` will be an array of objects - we'll talk about what type later.

```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData");
```

## SELECT One Record ##

After this has executed, `$record` will be a stdClass object.

```php
<?php
$record = $db->fetchOne("SELECT * FROM tblData WHERE intKey = ?", array(1));
```

If we pass in an optional third parameter, we'll get back an object of that class

```php
<?php
$foo = $db->fetchOne("SELECT * FROM tblData WHERE intKey = ?", array(1), 'Foo');
```

So now, `$foo` is an instance of class `Foo`

## SELECT with parameters and result Class ##

After execution, `$records` is an array of (namespaced) `\Docnet\Bar` objects, where intKey > 3 and vchName = Barry

```php
<?php
$records = $db->prepare("SELECT * FROM tblData WHERE intKey > ?id AND vchName = ?name")
   ->bindInt('id', 3)
   ->bindString('name', 'Barry')
   ->setResultClass('\\Docnet\\Bar')
   ->fetchAll();
```
The `prepare()` method returns a fluent `Statement` class which provides named parameter binding.

Parameter binding deals with all escaping and quoting for you.

## INSERT, UPDATE, DELETE ##
Insert, update and delete operations (also called DML queries) work in just the
same way as the ``fetch`` methods.
```php
<?php
$binds = array(1, 'foo');
$db->insert("INSERT INTO tblData (intField1, vchField2) VALUES (?,?)", $binds);
```
The number of affected rows is returned.

## Re-executing Prepared Statements ##
```php
<?php
$stmt = $db->prepare("SELECT * FROM tblData WHERE intKey = ?id");
$stmt->bindInt('id', 4)->fetchOne();
$stmt->bindInt('id', 5)->fetchOne();
```

## Arbitrary SQL ##

If you REALLY need to, you can just run arbitrary queries like this:

```php
<?php
$db->query("TRUNCATE tblTransient");
```

# PHP DB Access Layer #

Designed to wrap mysqli into a series of fast, secure and easy to use classes.

## Install with Composer ##

Require line as follows

`"docnet/php-dbal": "v2.0"`

## Let's Connect ##

You're gonna need to connect to a DB before you can do anything else...

```php
<?php
$settings = new \Docnet\DB\ConnectionSettings('127.0.0.1', 'root', 'password', 'dbname');
$db = new \Docnet\DB($settings);
```

For the following examples, we'll assume there's an active DB object.

## My First SELECT ##

After this has executed, `$records` will be an array of `stdClass` objects - see how to change the result class later.

```php
<?php
$records = $db->fetchAll("SELECT * FROM tblData");
```

## SELECT One Record ##

After this has executed, `$record` will be a stdClass object.

```php
<?php
$record = $db->fetchOne("SELECT * FROM tblData WHERE intKey = ?", 84);
```

### Result Class ###

If we pass in an optional third parameter, we'll get back an object of that class

```php
<?php
$foo = $db->fetchOne("SELECT * FROM tblData WHERE intKey = ?", 84, 'Foo');
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
$db->insert("INSERT INTO tblData (intField1, vchField2) VALUES (?, ?)", $binds);
```

### Return Values ###
- `insert()` returns the last insert id
- `update()` and `delete()` return the number of affected rows

## Re-executing Prepared Statements ##

For SELECTs

```php
<?php
$stmt = $db->prepare("SELECT * FROM tblData WHERE intKey = ?id");
$stmt->bindInt('id', 4)->fetchOne();
$stmt->bindInt('id', 5)->fetchOne();
```

Or, more commonly, INSERTs - this can be MUCH higher performance than running multiple INSERT queries as the server only
interprets the SQL string once.

```php
<?php
$stmt = $db->prepare("INSERT INTO tblPeople VALUES (?name");
$stmt->bindString('name', 'Tom')->insert();
$stmt->bindString('name', 'Dick')->insert();
$stmt->bindString('name', 'Harry')->insert();
```

## Arbitrary SQL ##

If you REALLY need to, you can just run arbitrary queries like this:

```php
<?php
$db->query("TRUNCATE tblTransient");
```

## Binding ##

Binding is great.  It allows the DBAL to take care of **escaping AND quoting**.

There are quite a few different supported binding methods (probably too many, but I'm keen to be flexible).

Shorthand, single scalar value

```php
<?php
$db->fetchOne("SELECT * FROM tblData WHERE intKey = ?", 84);
```

Shorthand array of parameters - parameter sequence must match your query

```php
<?php
$db->fetchOne("SELECT * FROM tblData WHERE intKey = ? AND vchName = ?", array(84, 'Tom'));
```

Shorthand array of named parameters - any sequence, types auto-detected

```php
<?php
$params = array('name' => 'Tom', 'id' => 84);
$db->fetchOne("SELECT * FROM tblData WHERE intKey = ?id AND vchName = ?name", $params);
```

Long-hand typed, named binding - fluent, any sequence

```php
<?php
$db->prepare("SELECT * FROM tblData WHERE intKey = ?id AND vchName = ?name")
   ->bindString('name', 'Dick')
   ->bindInt('id', 4)
   ->fetchOne();
```

Long-hand type-hinted, named binding - fluent, any sequence

```php
<?php
$db->prepare("SELECT * FROM tblData WHERE intKey = ?int_id AND vchName = ?str_name")
   ->bind('str_name', 'Dick')
   ->bind('int_id', 4)
   ->fetchOne();
```

## Public Methods ##

### DB Class ###

The following methods are available

- `fetchOne()`
- `fetchAll()`
- `insert()`
- `update()`
- `delete()`
- `prepare()` which returns a new `Statement` object when called
- `query()`
- `escape()`
- `begin()` Transaction support
- `commit()` Transaction support
- `rollback()` Transaction support

### Statement Class ###

SELECT
- `fetchOne()`
- `fetchAll()`
- `setResultClass()`

DML
- `insert()`
- `update()`
- `delete()`

Binding
- `bind()`
- `bindInt()`
- `bindString()`
- `bindDouble()`
- `bindBlob()`

Post execution
- `getInsertId()`
- `getAffectedRows()`

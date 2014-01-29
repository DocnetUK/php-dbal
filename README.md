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

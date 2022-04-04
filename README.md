# Welcome to the WDB page
## About WDB
WDB is an ORM for PHP. It allows you to manipulate your databases simply, but efficiently.
WDB brings you power, as well as remarkable freedom and flexibility in creating your code.
You do what you want, the way you want. To describe it in a few words:
- MIT license
- familiar syntax
- zero hassle, zero configuration
- easy to use, simple to learn
- flexible, fast, powerful and smart
- PHP 5.3 or newer
- mysql, postgresql, sqlite, sqlserver, oracle, etc

### Website & Documention
Official website:  [http://wdb.freevar.com/](http://wdb.freevar.com/)  
Documentations: [http://wdb.freevar.com/documentation.php](http://wdb.freevar.com/documentation.php)  

## QUICK USE
### 1) Including WDB in your script
You cann use WDB by including one of the following two files in your script **src/WDB.php** or **vendor/autoload.php**
```php
<?php
require "wdb_directory_path/vendor/autoload.php";
use \NCB01\WDB\WDB;
```
OR 
```php
<?php
require "wdb_directory_path/src/WDB.php";
use \NCB01\WDB\WDB;
```
### 2) Create a WDB object
```php
$db = new wdb(array(
  "server"  =>  "server_name",
  "port"    =>  "port_nummer", # optional
  "dbname"  =>  "database_name",
  "type"    =>  wdb::MYSQL,    # If you are under MYSQL or MariaDB
  "user"    =>  "user_name",
  "pswd"    =>  "passwort",
  "charset" =>  "charset",     # optional
));
```
If you want to use SQLite, it will be like this:
```php
<?php
$db = new wdb(array(
  "type"    =>  wdb::SQLITE,
  "path"    =>  "path_to_db_file",  # optional
));
```

### 3) Selecting data with a shortened select
```php
$where = array
(   "email"   => "some@email.com",
    "column3" => "some string",
    "column4" => 5
);
$db->select
(  "mytable",
   array
   (   "column1",
       "column2",
   )
   $where
);
```
### 4) Selecting data with a chained SELECT
```php
$db->select($columns)
 ->from($table)
 ->join($tables_to_join)
 ->where($where)
 ->groupby($group_columns)
 ->having($condition)
 ->orderby($order)
 ->limit($nbr, $offset);
 ```
 
### 5) Retrieving results one by one. $row ist an array
```php
while($row = $db->fetch()) var_dump($row);
```

### 6) Retrieving all results at once. $res is an array
```php
$res = $db->fetchAll(); 
```

### 7) Deleting data with a condition
```php
$db->delete("mytable", $where);
```

### 8) Deleting data with a list of ID values
```php
$db->delete("mytable", "id_colum", $value1, $value2);
```

### 9) Updating data
```php
$db->update
(  "mytable",
   array(
      "column1" => $value1,
      "column2" => $value2,
   ),
   $where
);
```

And many other things to discover. You can now go to the
[website](http://wdb.freevar.com) or directly
to the [documentation](http://wdb.freevar.com/documentation.php)
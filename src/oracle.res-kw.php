<?php
/**
 *  WDB version 1.0.0
 *
 *  @author NYIMA Charles B.
 *  @Copyright (c) 2022 Charles B. Nyima
 *	@version 1.0.0
 *	@website  http://wdb.freevar.com
 *	@documentation  http://wdb.freevar.com/documentation.php
 *
 ***********************************************************************
 *
 *	A normal identifier follows the rules: [a-bA-B][a-bA-B0-9_$#]*
 *
 *  https://www.red-gate.com/simple-talk/databases/oracle-databases/oracle-for-absolute-beginners-part-7-creating-tables-constraints-and-triggers/
 *	https://docs.oracle.com/database/121/SQLRF/sql_elements008.htm#SQLRF51129
 *	https://docs.oracle.com/database/121/SQLRF/ap_keywd001.htm#SQLRF55621
 *
 *	None of these words can be used as identifiers unless they are enclosed in double quotes.
 */
$or_reserved_kw = array('ACCESS', 'ADD','ALL','ALTER', 'AND', 'ANY','AS','ASC','AUDIT','BETWEEN','BY','CHAR',
'CHECK','CLUSTER','COLUMN','COMMENT','COMPRESS','CONNECT','CREATE','CURRENT','DATE','DECIMAL',
'DEFAULT','DELETE','DESC','DISTINCT','DROP','ELSE','EXCLUSIVE','EXISTS','FILE','FLOAT','FOR',
'FROM','GRANT','GROUP','HAVING','IDENTIFIED','IMMEDIATE','IN','INCREMENT','INDEX','INITIAL',
'INSERT','INTEGER','INTERSECT','INTO','IS','LEVEL','LIKE','LOCK','LONG','MAXEXTENTS','MINUS',
'MLSLABEL','MODE','MODIFY','NOAUDIT','NOCOMPRESS','NOT','NOWAIT','NULL','NUMBER','OF', 'OFFLINE',
'ON','ONLINE','OPTION','OR','ORDER','PCTFREE','PRIOR','PRIVILEGES','PUBLIC','RAW','RENAME',
'RESOURCE','REVOKE','ROW','ROWID','ROWNUM','ROWS','SELECT','SESSION','SET','SHARE','SIZE','SMALLINT',
'START','SUCCESSFUL','SYNONYM','SYSDATE','TABLE','THEN','TO','TRIGGER','UID','UNION','UNIQUE',
'UPDATE','USER','VALIDATE','VALUES','VARCHAR','VARCHAR2','VIEW','WHENEVER','WHERE','WITH',
);
?>
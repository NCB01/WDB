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
 * https://www.sqlite.org/lang_corefunc.html
 * https://www.sqlite.org/lang_mathfunc.html
 * https://www.php.net/manual/fr/book.math.php
 * https://www.php.net/manual/fr/pdo.sqlitecreatefunction.php
 * https://www.php.net/manual/fr/pdo.sqlitecreateaggregate.php
 * https://www.php.net/manual/fr/pdo.sqlitecreatecollation.php
 */
if(defined('WDB_SQLITE_FCTS') || !isset($this) || !isset($this->dbtype) ||
$this->dbtype!=self::SQLITE || !isset($this->pdo))return;

define('WDB_SQLITE_FCTS', 1); $p = &$this->pdo;

/*  Use in SQL queries:
 *	log(X)		=>		base-10 logarithm for X
 *	log(B, X)	=>		base-B  logarithm for X
 */
function WDB_logBX()
{	if(($n=func_num_args())==1)return log10(func_get_arg(0));
	if($n==2)return log(func_get_arg(1), func_get_arg(0));
	\NCB\WDB\WDB::setIError("FUNCT_ERROR_LOG".($n?'':'0'), $n);
}
function WDB_log2($x){return WDB_logBX(2, $x);}
function WDB_trunc($x){return $x>=0?floor($x):-floor(-$x);}

$p->sqliteCreateFunction('acos', 'acos', 1);
$p->sqliteCreateFunction('acosh', 'acosh', 1);
$p->sqliteCreateFunction('asin', 'asin', 1);
$p->sqliteCreateFunction('asinh', 'asinh', 1);
$p->sqliteCreateFunction('atan', 'atan', 1);
$p->sqliteCreateFunction('atan2', 'atan2', 2);
$p->sqliteCreateFunction('atanh', 'atanh', 1);
$p->sqliteCreateFunction('ceil', 'ceil', 1);
$p->sqliteCreateFunction('ceiling', 'ceil', 1);
$p->sqliteCreateFunction('cos', 'cos', 1);
$p->sqliteCreateFunction('cosh', 'cosh', 1);
$p->sqliteCreateFunction('degrees', 'rad2deg', 1);
$p->sqliteCreateFunction('exp', 'exp', 1);
$p->sqliteCreateFunction('floor', 'floor', 1);
$p->sqliteCreateFunction('ln', 'log', 1);
$p->sqliteCreateFunction('log10', 'log10', 1);

$p->sqliteCreateFunction('log', 'WDB_logBX');
$p->sqliteCreateFunction('log2', 'WDB_log2', 1);
$p->sqliteCreateFunction('mod', 'fmod', 2);
$p->sqliteCreateFunction('pi', 'pi', 0);
$p->sqliteCreateFunction('pow', 'pow', 2);
$p->sqliteCreateFunction('power', 'pow', 2);
$p->sqliteCreateFunction('radians', 'deg2rad', 1);

$p->sqliteCreateFunction('sin', 'sin', 1);
$p->sqliteCreateFunction('sinh', 'sinh', 1);
$p->sqliteCreateFunction('sqrt', 'sqrt', 1);
$p->sqliteCreateFunction('tan', 'tan', 1);
$p->sqliteCreateFunction('tanh', 'tanh', 1);
$p->sqliteCreateFunction('trunc', 'WDB_trunc', 1);
?>
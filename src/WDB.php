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
 */
namespace NCB01\WDB;

class WDB
{	const CUBRID=-1, FIREBIRD=-2, INFORMIX=-3, MYSQL=-4, ORACLE=-5,
	POSTGRESQL=-6, SQLITE=-7, SQLSERVER=-8, DB2=-9, SYBASE=-10,
	ERROR_SILENT=0, ERROR_WARNING=1, ERROR_THROW=2, MARIABD=-4,
	IBM=-3;

	static public $selfdir='', $lastI=null,
	$display_error_line = false;

	static private $iniMsgs=false,
	$pnb=0, # generateur de noms des paramètres;
	$cIns = null, //current instance of WDB
	$errobj=null, $orKWs=null, $firKWs=null; // motts réservés oracle et firebird

	private $error='', $dbtype=false,
	$pdo=null, $state=0, $stack=false,
	$pdoS=null, $exec_state=0, //1==query prepared, 2==query executed
	$version='',	# Original version from the DB, without any processing
	$vers='',		# Processed version, contains only numbers and points.
	$qv='"', $qs='\'',  # caractères utilisé pour echapper les IDs et STR

	$ci=false,		# instruction courante, utilisé pour SELECT, UPDATE
	$tr=false,		# Vaut true si une transaction est en cours.

	# This variable lock the error reporting mode, if it is not already blocked by the $security variable. 
	$lock_er_rep=false,

	/*	This variable prevents manipulation of the pdo entity from the pdo() function
	 *	-1	:	Not fixed. The pdo(), setAttribute() and error_reporting() functions works normally. The security_mode() function can be called to change this behavior
	 *	TRUE:	Fixed. The pdo(), setAttribute() and error_reporting() functions are blocked. The security() function can no longer be called to change this behavior
	 *	FALSE:	Fixed. The pdo(), setAttribute() and error_reporting() functions works normally. The security_mode() function can no longer be called to change this behavior
	 */
	$security = -1,

	$auto_exec = true,

	/**	Gestion des erreurs
	 *	2 === PDO::ERRMODE_EXCEPTION = throw errors, stops script
	 *	1 === PDO::ERRMODE_WARNING = display errors, script continue
	 *	0 === PDO::ERRMODE_SILENT = do nothing
	 */
	$error_rep = \PDO::ERRMODE_EXCEPTION,

	/**	variable contenant les paramètres PDO de la ou des requêtes simultanées.
	 *	Lorsqu'il ne vaut pas FALSE, il sera un Array sous la forme suivante:
	 *
	 *	array(
	 *		"queries"   =>    array 
	 *		(	array( 'query'=>query_0, 'params'=>params_0)  ,
	 *			. . .
	 *			array( 'query'=>query_n, 'params'=>params_n)  ,
	 *		),
	 *		"allparams" =>	array( . . . ),
	 *		"ptypes" 	=>	array( . . .),
	 *		"UAnonymes"	=>	array(i_extern0 => i_extern0, ..., i_extern_n => i_intern_n),
	 *      "UNamed"	=>	array(paramName_0, ..., paramName_k),
	 *		"NBSupplied"=>	nombre,
	 *
	 *		"ref" 		=>	Array(),
	 *		"exec" 		=>	true,
	 *		"bcol" 		=>	array(),
	 *		"ORA_E_PARAMS_COL" => array(inPar => column, ..., inPar => column),
	 *		"ORA_E_BLOB" =>	array(inPar => exPar, ..., inPar => exPar), // Explicitly reported LOBs
	 *	)
	 *
	 * - query_i	:	la requête numéro i
	 * - params_i	:	le tableau des paramètres utilisé dans la requete i. Il est indexé par les noms des paramètres, les valeurs sont NULL. Ces paramètres sont nommés.
	 *
	 * - allparams	:	Array de tous les paramètres nommés ou annonymes de toutes les requêtes
	 * - ptypes		:	Array des types PDO des valeurs des params fournis par l'utilisateur
	 * - NBsupplied	:	Nombre de parametres utilisateur dont la valeur est déjà données.
	 * - UAnonymes	:	Array de tous les params anonymes utilisateur. Indexé par le rang des parametres(>=0), les valeurs sont les noms générés pour eux.
	 * - UNamed     :	Array (liste) des noms de tous les params utilisateur nommés
	 * - ref     	:	Array. Existe seulement s'il existe des paramètres liés à des variables par references
	 * - exec     	:	TRUE - Existe seulement si la requête a été executée au moins une fois
	 * - bcol     	:	Array - Les élements de bindColumn
	 * - ORA_E_PARAMS_COL :	Array - Existe seulement sous ORACLE, et contiendra les paramètres PDO (ils sont des clés et les valeurs sont des noms colonnes) auxquels l'utilisateur pourrait signaler comme LOBS.
	 * - ORA_E_BLOB :	Array - Existe seulement sous ORACLE, et contiendra les données signaleés par l'utilisateur comme des LOBs avec la fonction bind()
	 */
	$params=false,

	/**	Utilisé en interne par les fonction wdb::update() et wdb::add()
	 *	Sera un Array de 3 elements comme suit:
	 *
	 *	array(
	 *		'ref'	=> premier_element_de_la_serie
	 *		'Qkeys'	=> array_de_clés_quotées
	 *	)
	 */
	$temp = false,

	$extra_return = false,
	$boolF = array('begintransaction'=>0, 'rollback'=>0, 'commit'=>0, 'fakebool'=>0);

	public
	/** Chaîne d'introduction d'expressions et de variables
	 */
	$exp_begin = '#',
	$value_operators=true,
	$key_operators=true;


	/**	$cf can be either an instance of pdo, the dsn string, an array or
	 *	the absolute path of the PHP file containing the connection infos.
	 *
	 *	1) $cf can be an instance of pdo
	 *	2) $cf can be an dsn string.
	 *	3) $cf can be a PHP array, in which case it should contain the following elements:
	 *	- type		:	The server or DB type, required. The types are as follows:
	 *
	 *					mysql, sqlite, postgresql (or pgsql), cubrid, firebird,
	 *					informix, sqlserver (or sqlsrv), oracle
	 *
	 *	- server	:	(host) The server name, required if the type is other than 'sqlite'
	 *	- port		:	The port number to use, optionnal.
	 *	- dbname	:	The DB name, optionnal. This will be the full path to the database if the type is sqlite
	 *	- charset	:	The charset to use, optionnal. If necessary, utf8mb4 will be used by default.
	 *	- user		:	The user name, required if the type is other than 'sqlite'.
	 *	- pswd		:	(psw, password) The user password, required if the type is other than 'sqlite'.
	 *
	 *
	 *	SIGNATURES
	 *		wdb($pdo, $dbtype [, $options ])
	 *		wdb($array)
	 *		wdb($dsn, $user, $password, $dbtype [, $options ])
	 */
	function __construct($cf=false)
	{	try
		{	$opts = null;
			if(($n=func_num_args())>1)
			{	if(($n=func_num_args())==2 || $n==4)$dbtype=func_get_arg($n-1);
				elseif($n==3 || $n==5)
				{	$dbtype=func_get_arg($n-2); $opts=func_get_arg($n-1); }
				else $this->IniErr('BADPRAMNUM');
			}
			elseif($n==1){
				if(!is_array($cf))$this->IniErr('BADPRAMTYP');
				if(!($dbtype=@$cf['type']))$dbtype=@$cf['dbtype'];
				$opts = @$cf['options'];
			}
			else $this->IniErr('BADPRAMNUM');

			if(!$this->dbtype($dbtype))die();
			$opts = $this->setOptions($opts);

			if(is_string($cf) || is_array($cf))
			{	if(is_array($cf))
				{	if($dsn=@$cf['dsn']){$user=@$cf['user'];
						if(!($psw=@$cf['psw']) && !($psw=@$cf['pswd']))$psw=@$cf['password'];
					}
					elseif(!($dsn=self::dsn($cf, $dbtype, $dsn2, $user, $psw)))
					{ return $this->IniErr('CONSTERR');}
				}
				elseif(!($dsn=$cf))return $this->IniErr('DSNVOID');

				if((!isset($user)||!$user) && $n>=2)$user=func_get_arg(1);
				if(isset($user) && $user && (!isset($psw) || !$psw) && $n>=3)$psw=func_get_arg(2);

				if(isset($user))$this->pdo = new \PDO($dsn, $user, isset($psw)?$psw:'', $opts);
				else $this->pdo = new \PDO($dsn, '', '', $opts);
			}
			elseif(is_object($cf))
			{	if(function_exists('is_a'))
				{	if(!(@is_a($cf, 'PDO')))return $this->IniErr('CONSTERR2');
				}elseif(!($cf instanceof \PDO)) return IniErr('CONSTERR2');

				foreach($opts as $key => $val)$cf->setAttribute($key, $val);
				$this->pdo = $cf;

			}else {	$this->IniErr('CONSTERR3'); } self::$lastI=$this;

		}catch(\Exception $e)
		{
			return $this->setErr('PDO Error: '.$e->getMessage());
		}
		if($this->dbtype==self::SQLITE)include self::$selfdir."/sqlite.math-functions.php";
		$this->c();
		#$this->dbtype = self::ORACLE;
	}

	static function version(){ return '1.0.0';}

	/** Debut d'une transaction
	 * @return boolean TRUE for success and FALSE for failure
	 */
	function beginTransaction(){$this->c(); $x=true;if(!$this->tr &&
		($x=$this->pdo->beginTransaction()))$this->tr=1; return $x;
	}
	/** Annule une transaction
	 * @return boolean TRUE for success and FALSE for failure
	 */
	function rollback(){ $this->c(); $x=true;if($this->tr && ($x=$this->pdo->rollback()));
		$this->tr=0; return $x;}
		
	/** Validate a transaction
	 * @return boolean TRUE for success and FALSE for failure
	 */
	function commit(){ $this->c(); $x=true;if($this->tr && ($x=$this->pdo->commit()));
		$this->tr=0; return $x;}

	function quote($val, $type = \PDO::PARAM_STR)
	{ return ($r=@$this->pdo->quote($val, $type))!==FALSE?$r:
		wdb::escapeEntity($str, $this->$dbtype, "'");
	}

	function quoteIdent($identifier, $multifield=false)
	{ return $multifield?wdb::escapeMultifieldId($identifier, $this->dbtype):
			 wdb::escapeEntity($identifier, $this->dbtype, $this->dbtype==self::MYSQL?'`':'"');
	}

	function sqliteCreateFunction($func_name, $callback, $num_args = -1, $flags = 0)
	{	return $this->dntype!=self::SQLITE?FALSE:
		$this->pdo->sqliteCreateFunction($func_name, $callback, $num_args, $flags);
	}

	function sqliteCreateAggregate($func_name, $step_func, $finalize_func, $num_args)
	{	return $this->dntype!=self::SQLITE?FALSE:
		$this->pdo->sqliteCreateAggregate($func_name, $step_func, $finalize_func, $num_args);
	}


	/**
	 * Returns some configuration items for the current WDB instance and the current connection
	 * @return array Depends on the type of database. Contains among others the following elements: "AUTOCOMMIT", "ERRMODE", "CASE", "TIMEOUT",
	 * "CONNECTION_STATUS", "ORACLE_NULLS", "PERSISTENT", "PREFETCH", "SERVER_INFO",
	 * "SERVER_VERSION","CLIENT_VERSION","DRIVER_NAME"
	 */
	function infos()
	{	$attr = array("AUTOCOMMIT", "ERRMODE", "CASE", "TIMEOUT",
		"CONNECTION_STATUS", "ORACLE_NULLS", "PERSISTENT", "PREFETCH", 
		"SERVER_INFO","SERVER_VERSION","CLIENT_VERSION","DRIVER_NAME"
		); $this->c();

		foreach($attr as $val) {
			if(is_null($v=@constant('PDO::ATTR_'.$val)))$r[$val] = null;
			else try{ $r[$val] = @$this->pdo->getAttribute($v); }
			catch(\Exception $e){ $r[$val] = null; }
		}
		$r['DBTYPE'] = $this->dbtype; $r['DBMS'] = $this->dbms();
		return $r;
	}

	/**
	 * Reinitialization of a WDB instance for example after an error. 
	 * @return WDB
	 */
	function init($init_auto_exec=true)
	{	$this->c();if(!$this->error)self::$errobj=null;
		$this->error=''; $this->stack=$this->ci=false;
		if($this->pdoS && $this->exec_state)$this->pdoS->closeCursor();
		$this->exec_state=$this->state=0;
		$this->pdoS = $this->params = $this->temp = null;
		if($init_auto_exec)$this->auto_exec=true; return $this;
	}

	/**
	 *	Sets / Returns the PDO object of the WDB instance.
	 *	
	 *	SIGNATURES:
	 *		pdo()
	 *		pdo(PDO_Object, $dbtype)
	 *
	 *	@return  PDO|FALSE 	A PDO object on success or FALSE if the entity does not contains a PDO object.
	 */
	function pdo()
	{	$this->c(); //return ($this->security===TRUE)? NULL : $this->pdo;

		if($this->security===TRUE)return null;
		if(($n=func_num_args()) && $n!=2)return $this->IniErr('BADPRAMNUM2', 'WDP::pdo()');
		if($n==2)
		{	if(!is_object(func_get_arg(0)))return $this->IniErr('BADPRAMTYP3');
			if(function_exists('is_a'))
			{	if(@!is_a(func_get_arg(0), \PDO))return $this->IniErr('BADPRAMTYP3');
			}elseif(!(func_get_arg(0) instanceof \PDO))return $this->IniErr('BADPRAMTYP3');
			if(!$this->dbtype(func_get_arg(1)))return $this->IniErr('BADPRAMTYP3');
			$this->init(); $this->pdo = func_get_arg(0);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		return $this->pdo;
	}

	function security_mode($enabled = TRUE)
	{	$this->c(); $T=$this; if($this->security != -1)return $T;
		if($enabled && !$T->lock_er_rep)$T->lock_er_rep=TRUE;
		if($this->security == -1)$this->security = $enabled?TRUE:FALSE;
		return $this;
	}

	/* This function prevents the next instruction from being automatically executed.
	 * @return WDB
	 */
	function not_execute(){	$this->c();$this->auto_exec = false; return $this;}

	/*	The function fixes the behavior of WDB in the event of an error.
	 *
	 *		error_reporting( [ int $errMode ] ) : int
	 *
	 *	@param	$errMode int  The error mode. Must be one of the following values: ERRMODE_SILENT, PDO::ERRMODE_WARNING or PDO::ERRMODE_EXCEPTION
	 *
	 *	@return int The current error mode.
	 */
	function error_reporting()
	{	$this->c();$rep=$this->error_rep;
		if($nb = func_num_args())
		{	if($this->lock_er_rep)return $this->error_rep;
			if(($val=func_get_arg(0))===\PDO::ERRMODE_EXCEPTION
			|| $val===\PDO::ERRMODE_WARNING
			|| $val===\PDO::ERRMODE_SILENT)$this->error_rep=$val;
		}
		return $rep;
	}

	function lock_error_reporting()
	{	$this->c(); if($this->security != -1)return $this;
		$this->lock_er_rep = 1; return $this;
	}

    /**
     * Returns the version of the installed database engine.
     * @param boolean $process If it is FALSE, the raw value is returned as is. If it is TRUE (default value), processing is performed to return a value in the form num1.num2.num3 etc ...
     * @return string|boolean String for success and FALSE for failure
	 *
	 *	https://www.techonthenet.com/oracle/questions/version.php
	 *  https://community.oracle.com/tech/developers/discussion/2250946/how-to-check-the-oracle-database-version
	 *  https://www.muniqsoft-training.de/oracle/muso_training/r/schulung/tipp-anzeige?p1001_tippauswahl=48&p1001_cs=4167587968&request=Oracle_Version_abfragen
	 *  https://www.basisguru.com/how-to-determine-sybase-database-version/
	 *  https://stackoverflow.com/questions/43531346/query-full-informix-database-version-using-sql-query
     */
	function dbVersion($process=true)
	{	$this->c();if($this->version) return $process?$this->vers:$this->version;
		if(!$this->pdo) return false;
		if($this->dbtype==self::SQLSERVER)$q='SELECT SERVERPROPERTY(\'ProductVersion\') as version';
		elseif($this->dbtype==self::MYSQL)$q='SELECT VERSION() as version';
		elseif($this->dbtype==self::POSTGRESQL)$q='SELECT VERSION() as version';
		elseif($this->dbtype==self::SQLITE)$q='SELECT sqlite_version() AS version';
		elseif($this->dbtype==self::CUBRID)$q='SELECT VERSION() as version';
		elseif($this->dbtype==self::FIREBIRD)$q="SELECT rdb\$get_context('SYSTEM', 'ENGINE_VERSION') AS version from rdb\$database;";
		else return false; $r=false;try{$r=$this->pdo->query($q);}
		catch(\Exception $e){ $this->setErr('PDO Error: '.$e->getMessage()); return false;}
		if(!$r || !($x=$r->fetch()))return false; $r=null;
		if(isset($x[0]))$x=$x[0];elseif(isset($x['version']))$x=$x['version'];
		elseif(isset($x['VERSION']))$x=$x['VERSION'];else return '';
		$this->version=$x; $i=0;$l=strlen($x);
		while($i<$l && (($r=$x[$i])<'0' || $r>'9')){$i++;}
		while($i<$l && (($r=$x[$i])<='9' && $r>='0' || $r=='.')){$i++;$this->vers.=$r;}
		return $process?$this->vers:$this->version;
	}


	/**
	 * Sets / Returns the numeric type of the database engine. This value can be
	 * one of the following values: WDB::CUBRID, WDB::FIREBIRD, WDB::INFORMIX,
	 * WDB::MYSQL, WDB::ORACLE, WDB::POSTGRESQL, WDB::SQLITE, WDB::SQLSERVER,
	 * WDB::DB2, WDB::SYBASE
	 * 
	 * Signatures
	 *     wdb->dbtype($dbtype)
	 *     wdb->dbtype()
	 *
	 * @return int|FALSE int for success or FALSE for failure
	 */
	function dbtype()
	{	$this->c();if(func_num_args() === 1)
		{	$dbtype=func_get_arg(0);
			if(is_int($dbtype))
			{	if($dbtype<-10 || $dbtype>-1){ $this->IniErr('BADDBTYP', $dbtype); return false;}}
			elseif(is_string($dbtype) && 
			!($dbtype=self::TypeFromString($dbtype)))
			{ return $this->IniErr('BADDBTYP2');}
			$this->dbtype=$dbtype; $this->qs='\'';
			$this->qv=$dbtype==self::MYSQL?'`':'"';
		}
		return $this->dbtype;
	}

	/**
	 * Returns the literal type of the database engine. It is a string whose value can be:
	 * MYSQL, SQLITE, POSTGRESQL, CUBRID, INFORMIX, SQLSERVER, FIREBIRD, ORACLE DB, DB2, SYBASE.
     * @return string|FALSE String on success or FALSE on failure
	 */
	function dbltype(){	$this->c();return self::sdbtype($this->dbtype);}

	/**	SIGNATURES
	 *
	 * @signature1 select($fields)
	 * @signature2 select($tabname, $fields)
	 * @signature3 select($tabname, $fields, $where)
	 *
	 * @param
	 *	- $tabname
	 *		Array or String containing the name(s) of the table(s).
	 *		If it is a string, the names of the tables will be separated
	 *		by a comma ,(if there are several).
	 *
	 *	- $fields
	 *		The name(s) of the column(s) to select, in the form of a character
	 *		string or an Array. If several names are given in a string, they 
	 *		will be separated with a comma.
	 *
	 *	- $where
	 *		The condition. It can be a string or a PHP Array. If it is a character
	 *		string, it must produce a Boolean result. If it is an array, each
	 *		key is combined with the corresponding value to form part of the
	 *		result. The different parts are then combined to produce the final
	 *		condition. By default the OR operator is used to combine the different
	 *		parts of the result.
	 * 
	 */
	function select()
	{	$this->c(); if($this->error)return $this;
		//if($this->state)return $this->IniErr('BADSLCT');
		$this->init(false); $tble=false; $qv=$this->qv; $db = $this->dbtype;

		if($this->auto_exec===false)$this->auto_exec='';
		elseif($this->auto_exec==='')$this->auto_exec=true;

		if(($n=func_num_args())==0){$cols='*';}
		elseif($n==1){$cols=func_get_arg(0);}
		elseif($n==2){$tble=func_get_arg(0);if(!($cols=func_get_arg(1)) && $cols!=='0') $cols='*'; $where=false;}
		elseif($n==3){$tble=func_get_arg(0);if(!($cols=func_get_arg(1)) && $cols!=='0') $cols='*'; $where=func_get_arg(2);}
		else return $this->IniErr('BADPRAMNUM3', 'WDB::select()', 1, 3, $n);

		if($n>=2)
		{
			if($tble && !is_string($tble) && !is_array($tble))
			{	return $this->IniErr('BADPRAM2'); }
		}

		if($n==1 || $n>=2)
		{	if(is_object($cols))$cols = (array)$cols;

			// NE FONCTIONNE PAS SUR POSTGRESQL
			if(is_numeric($cols) && !is_string($cols))$cols=$this->addInternalVal($cols, 0, 0);

			elseif( !is_numeric($cols) && !is_string($cols) && !is_array($cols)
			|| is_string($cols) && $cols!='*' && !($cols=$this->analyse_expr($cols))
			|| is_array($cols) && ($cols=$this->stringify_select_columns_array2($cols)) === FALSE)
			return $this->IniErr('WCOLLIST');
		}

		$this->stack[]='SELECT '.$cols; $this->ci='SELECT';

		if($n>=2) # version raccourcie, avec table
		{	// Si la table n'est pas donnée on remplace par RDB$DATABASE
			if(!$tble && $db==wdb::FIREBIRD)$tble = 'RDB$DATABASE';
			if(!$tble && $db==wdb::ORACLE)$tble = 'DUAL';
			$this->state=2; if($tble)$this->from($tble); $this->state=1;
			$this->where($where); $this->state=7;
		}
		else $this->state=2; # version longue. Sans table

		/*	Si l'appel se fait avec table et qu'il n'y pas de parametres
		 *	on execute la requête et on sort.
		 */
		return ($n>=2 && $this->auto_exec && !$this->UParamNbr())? $this->exec() : $this;
	}


	/*	SIGNATURES:
	 *		from($tables)
	 *		from()
	 *
	 *	$tables
	 *		- Comma separated String of table names
	 *		- Array of tables names
	 *
	 *	Les noms des tables devront être donnnés tels qu'ils sont dans la DB.
	 *
	 *	S'ils sont listés dans une chaine de caractères, les noms des tables
	 *	ne doivent pas contenir de virgules, celles-ci étant interpretées comme
	 *	séparateurs de noms. Mais, les noms de tables dans une liste peuvent
	 *	cependant contenir des vides et/ou d'autres caractères non conventionnels
	 *	au milieu. Ils seront automatquement échappés.
	 *
	 *	Si les noms des tables sont donnés dans une table, une entrée sera pour
	 *	le nom d'une seule table. Ces noms-là peuvent donc contenir tous types
	 *	de caractères. Ils seront aussi automatiquement échappés.
	 */
	function from()
	{	if($this->error)return $this; $qv=$this->qv; $t=''; $dbt=$this->dbtype;
		if($this->state!=2)return $this->InitErr('BADCMD', 'SELECT');
		if(($n=func_num_args())>1)return $this->IniErr('BADPRAMNUM4', 'WDB::from()', 1, $n);
		if($n)
		{ 	$tbls = func_get_arg(0); $this->state=1;
			if(is_array($tbls) && //!($t=self::stringify_ident_list($tbls, $dbt))
			!($t=self::stringify_select_tables_array($tbls, $dbt))
			|| is_string($tbls) && !($t=self::escape_grpby_expr($tbls, $dbt))
			|| !is_array($tbls) && !is_string($tbls)
			)return $this->IniErr('BADTABLIST', 'WDB::from()');

			$this->stack[count($this->stack)-1].="\n".'FROM '.$t;
		}
		else $this->state=0; return $this;
	}

	/*	SIGNATURE
	 *		xjoin($tables)
	 *
	 *	$tables
	 *		String or Array representing the tables to join.
	 *
	 *		A - If it is a string, it must contain a valid SQL code of the tables to join.
	 *
	 *		B - If it is an Array
	 *
	 *			1 -	If a key is a String, it will be considered as table name and 
	 *				the value as condition.
	 *
	 *			2 -	If it's not the case, the corresponding value must be a string
	 *				containing the valid SQL code for the table join.
	 *
	 *	https://dev.mysql.com/doc/refman/5.6/en/join.html
	 *	https://www.google.fr/url?sa=t&rct=j&q=&esrc=s&source=web&cd=&cad=rja&uact=8&ved=2ahUKEwj5otOmib_xAhVohf0HHa1GDc0QFjABegQIBRAD&url=https%3A%2F%2Fstackoverflow.com%2Fquestions%2F7780890%2Fhow-to-use-aliases-with-mysql-left-join&usg=AOvVaw2b_MkwjzGCzIZgEdH7mPb2
	 *	http://lgatto.github.io/sql-ecology/03-sql-joins-aliases.html
	 */
	function join($tables, $cond=false){return $this->xjoin($tables, '', $cond);}
	function ljoin($tables, $cond=false){return $this->xjoin($tables, 'l', $cond);}
	function rjoin($tables, $cond=false){return $this->xjoin($tables, 'r', $cond);}
	function fjoin($tables, $cond=false){return $this->xjoin($tables, 'f', $cond);}


	/*	SIGNATURE
	 *		where($cond)
	 *
	 *	$cond	:	Non-empty string
	 *	$cond	:	Non-empty Array
	 *	$cond	:	FALSE | TRUE | NULL | "" | number
	 */
	function where($cond)
	{	if($this->error)return $this; $iS=$iR=false;
		if($this->state!=1 && $this->state!=3)return $this->IniErr('BADCMD', '"WHERE"');

		if(	!is_array($cond) && !is_scalar($cond) ||
			is_string($cond) && $cond && ($cond=$this->analyse_expr($cond))===FALSE ||
			($cond = $this->stringify_cond($cond, false, $qIndice=0))===FALSE 
		)return $this->IniErr('BADCOND', 'WDB::where()');

		$this->stack[count($this->stack)-1].="\nWHERE ".$cond;
		$this->state=4; return $this;
	}

	/*
	 *	$str : 	Une liste de colonnes sous formes de string ou de Array
	 *
	 *	Exemples :	userid, sales.productid
	 */
	function groupby($str)
	{	$qv=$this->qv; $dbt=$this->dbtype; if( (func_num_args()<2) &&
		(is_string($str) && ($str===''||($str=self::escape_grpby_expr($str, $dbt))
		=== FALSE) || is_array($str) && !($str=self::stringify_ident_list($tbls, $dbt))
		|| !is_string($str) && !is_array($str)) ||
		!($str=self::stringify_ident_list(func_get_args(), $dbt)) )
		return $this->IniErr('BADPRAM', 'WDB::groupby()');

		if(!$this->xyz($str, 'GROUP BY', 'groupby', 5, array(0, 2, 5, 6, 7)))return $this;
		return $this;
	}

	function having($str)
	{	if($this->stringify_cond($str)===FALSE)
		return $this->InitErr('BADPRAM', 'WDB::groupby()');
		if(!$this->xyz($str, 'HAVING', 'having', 6,  array(0, 2, 6, 7))){ }
		return $this;
	}

	/*
	 *	$str	: 	Une chaîne non vide contenant la suite de la commande ORDER BY.
	 *				Une 
	 *	Exemples :	userid ASC 
	 *				userid ASC, productid DESC
	 *
	 *	$str	:	Un array, par exemple
	 *				array
	 *				(	'column1',
	 *					'column2 DESC',
	 *					'column3' => 'ASC'
	 *					'column4' => 'DESC'
	 *				)
	 */
	function orderby($str)
	{	$qv=$this->qv; $dbt=$this->dbtype; if(!$str){$str=false;}
		elseif( ($n=(func_num_args()))==1 && !is_array($str))
		{	$str = stripos($str, ',')===FALSE? self::escape_orderby_col($str, $dbt) :
			self::escape_grpby_expr($str, $dbt);
		}
		else $str = self::stringify_orderby_array($n==1?$str:func_get_args(), $dbt);

		if($str)$this->xyz($str, 'ORDER BY', 'order by', 7,  array(0, 2, 7));

		return $str?$this : $this->IniErr('BADPRAM', 'WDB::orderby()');
	}

	# https://docs.microsoft.com/en-us/sql/t-sql/queries/select-order-by-clause-transact-sql?view=sql-server-ver15
	# https://www.w3schools.com/sql/sql_top.asp

	/*
	 *	$nbr	: nombre d'elements max à renvoyer
	 *	$offset	: nombre à ignorer au debut avant de commencer, >=0
	 *
	 *	SQLSERVER necessite l'utilisation prealable de ORDER BY sinon une erreur
	 *	sera lévée
	 */
	function limit($nbr, $offset=false)
	{	$T=&$this; if($T->ci!='SELECT')return $T->IniErr('BADCMD', 'LIMIT');
		$c = count($T->stack)-1;

		if(($d=$T->dbtype)==self::FIREBIRD){ $s='SELECT FIRST '.$nbr.($offset?' SKIP '.$offset:'');
			$T->stack[$c] = $s.substr($T->getLastSQL(), 6); return $T;
		}
		if($d==self::SQLSERVER)
		{	if(!strpos($T->getLastSQL(), 'ORDER BY '))return $T->IniErr('OrderbyREQ');
			$s="\nOFFSET ".($offset?$offset:0). " ROWS\nFETCH NEXT $nbr ROWS ONLY";
		}
		elseif($d==self::ORACLE)$s=($offset? "\nOFFSET $offset ROWS":'')."\nFETCH FIRST $nbr ROWS ONLY";
		else $s = "\nLIMIT ".$nbr.($offset?' OFFSET '.$offset:''); 
		$T->stack[$c].=$s; $T->state=7; return $T;
	}


	/*	Cette fonction clôture la sous-requete en cours.
	 *
	 *	En interne, il est question d'éliminer le dernier tampon après ajout de son
	 *	contenu à l'avant dernier tampon. Effectivement, si l'entité ne contient pas
	 *	au moins 2 tampons rien n'est fait.
	 *
	 *	Le premier tampon (index 0) est celui de la requête SELECT principale, les
	 *	autres correspondent chacun à une sous-requête d'une requête ou sous-requête
	 *	SELECT placée plus haut dans la hiérarchie.
	 *
	 *	ETAT2 : Après Select sans table
	 */
	function end($alias='')
	{	$this->c();if($this->error || !$this->stack)return $this;
		if($this->state==0||$this->state==2)return $this->IniErr('BADCMD2');
		if(count($this->stack) == 1) return $this;
		if(!$alias)$alias='alias'; $q=array_pop($this->stack);
		$this->stack[count($this->stack)-1].="\nFROM(\n".$q."\n) AS $alias";
		$this->state=1; return $this;
	}


	/*	Lie des valeurs à des parametres de la requête de l'utilisateur.
	 *
	 *	SIGNATURES
	 *		bind($param, $value[, $datatype])
	 *		bind($params[, $typeArray])
	 *
	 *
	 *	- $param	:	Un nom de parametre (:nom_param) ou rang d'une interrogation (?)
	 *	- $value	:	Une valeur à affecter au parametre identifié par $param
	 *	- $params	:	Un Array PHP dont les clés sont les parametres et leur valeur correspondantes
	 *
	 *	utilisée pour associer une seule valeur à son correspondant
	 *	utilisée pour associer +sieurs valeurs à leurs correspondants
	 *
	 *	Exemples
	 *		bind(':param', 2);
	 *		bind(1, 4, PDO::PARAM_INT);
	 *
	 *
	 *	IMPORTANT:
	 *	Certains SGBD comme SQLSERVER requierent absolument le 3-ìème parametre,
	 *	sinon la methode fetch() ne renvoie aucun resultat
	 */
	function bind()
	{	if($this->error)return $this; $dt=$this->dbtype; 
		$oraUI=$this->ORAC_UI(); $ex=false; $m=$this->UParamNbr(); //$bf=&$this->pVals; 

		if(!$this->stack && !$m)return $this->IniErr('BADCMD3', 'WDB::bind()');
		if($this->stack && count($this->stack) > 1){ $this->end(); if(!$this->error) return $this; }

		if(($n=func_num_args())==0)return $this->IniErr('BADCMD4');

		// t est le tableau des params ou le nom du param s'il y en a un seul
		if(!($isA=is_array($t=func_get_arg(0))) && !is_string($t) && (!is_int($t)
		|| $t<=0)){	return $this->IniErr('BADPRAMn1', 'WDB::bind()');}

		// $s est le tableau des types ou la valeur du param s'il y en a un seul
		$s = $n>=2?func_get_arg(1):null; $tr=($n>=3)?func_get_arg(2):null;

		if($isA)
		{	if($s && !is_array($s))return $this->IniErr('BindMSG');
			if(!$this->SetUParamVals($t, $s))return $this;
		}
		else
		{	if(!($iPar=$this->SetUParamVal($t, $s, $tr)))return $this->IniErr('BindErr', $t);

			if($oraUI && ($tr == \PDO::PARAM_LOB ||
			is_resource($s) && !$tr))$this->params['ORA_E_BLOB'][$iPar]=$t;
		}

		$egal = $this->UParamNbr()==$this->UParamSubmited();
		if($oraUI && $egal)$this->ORACLE_ELobs();

		return ($this->auto_exec && $egal)?$this->exec() : $this;

	}


	function bindVar($p, &$var, $type=false)
	{	$Q=&$this->params; if(($int=is_int($p)) && !isset($Q['UAnonymes'][$p-1]) ||
		!$int && !isset($Q['allparams'][$p]))return $this->IniErr('BindErr', $p);

		$iInd = $int? $Q['UAnonymes'][$p-1]:$p;	$Q['allparams'][$iInd]=&$var;

		if(!isset($Q['NBsupplied']))$Q['NBsupplied']=1;
		else $Q['NBsupplied']++; $this->ref[] = $iInd;

		if($type)$Q['types'][$iInd] = $type;

		if($type==\PDO::PARAM_LOB || !$type && is_resource($var))
			$this->params['ORA_E_BLOB'][$iInd]=$p;

		$egal = $this->UParamNbr()==$this->UParamSubmited();
		if($this->ORAC_UI() && $egal)$this->ORACLE_ELobs();

		return $this;
	}

	function bindColumn($col, &$var, $type=\PDO::PARAM_STR, $maxLength = 0, $driverOptions = null)
	{	if($this->error) return $this;
		if(!$this->stack && !@$this->params['queries'])return $this->setErr();
		if($this->pdoS)
		{	if(!$this->pdoS->bindColumn($col, $var, $type, $maxLength, $driverOptions))
			return $this->IniErr('FCTFAIL', 'WDB->bindColumn()');
		}
		$this->params['bcol'][] = array($col, &$var, $type, $maxLength, $driverOptions);
		return $this;
	}


	/*	WDB construit la requête au fur et à mésure de l'éxécution des fonctions.
	 *	Cette construction prend en compte la valeur et le type des données passées
	 *	en paramètres. Ceci peut créer quelques problèmes sous ORACLE dans le cas d'un
	 *	INSERT ou UPDATE contenant un LOB, si l'utilisateur décide d'utiliser le LOB
	 *	avec un paramètre PDO.
	 *
	 *	Supposons une requête comme suit:
	 *
	 *	$db->update
	 *	(	$tablename
	 *		array($col1 => $val1, $col2 => ':param2'),
	 *		$condition
	 *	)
	 *	->bind(':param2', $resource, PDO::PARAM_LOB);
	 *
	 *	Dans ce cas précis, la fonction update() qui ne connait absolument pas que le
	 *	parametre ':param2' sera déclaré comme LOB, va construire sa requête comme suit:
	 *
	 *		UPDATE tablename SET col1=val1, col2=:param2 WHERE condition
	 *
	 *	Après cela, l'utilisateur déclare la valeur et explicitement ou non, son type.
	 *	Si on n'est pas sous oracle pas de problème. Mais si c'est le cas, la requête
	 *	construite ne tient plus. Elle doit alors être transformée sous la forme:
	 *
	 *		UPDATE tablename SET col1=val1, col2=EMPTY_BLOB() WHERE condition
	 *		RETURNING col2 INTO :param2
	 *
	 *	C'est exactement ce que fait cette fonction. Elle est éxécutée seulement sous
	 *	ORACLE et uniquement si l'utilisateur a déclaré avec la fonction bind() des
	 *	parametres de type LOBS, explicitement ou non. L'éxécution se fait quand tous
	 *	les paramètres ont recu leur valeur.
	 *
	 */
	private function ORACLE_ELobs()
	{
		$P = &$this->params;
		if(!$P || !@$P['ORA_E_BLOB'] || @$P['exec'])return;

		foreach($P['queries'] as &$tab)
		{
			if(!@$tab['params'])continue;

			if(($q=@$tab['query']))
			{
				$parm=''; foreach($P['ORA_E_BLOB'] as $iP =>$eP)
				{	if(strpos($q, $iP)===FALSE)continue;
					if($parm)return $this->IniErr('ORACLE_NO_MORE AS_1LOB_ERR');
					$parm=$iP;
				}

				if($parm)
				{	if(strpos($q, ' RETURNING ')!==FALSE)return $this->IniErr('ORACLE_NO_MORE AS_1LOB_ERR');
					$r=array('('.$parm.',', '('.$parm.')', ','.$parm.')', ','.$parm.',', '='.$parm.',', '='.$parm.')', '='.$parm.' ');
					$r2 = array('(EMPTY_BLOB(),', '(EMPTY_BLOB())', ',EMPTY_BLOB())', ',EMPTY_BLOB(),', '=EMPTY_BLOB(),', '=EMPTY_BLOB())', '=EMPTY_BLOB() ');

					$tab['query'] = str_replace($r, $r2, $q).
					' RETURNING '.$this->params['ORA_E_PARAMS_COL'][$parm]." INTO $parm";
				}
			}
		} return $this;
	}

	/*	Execute la requête enregistrée. S'il s'agit d'une requête
	 *	SELECT comportant des sous-requete non cloturées, celle-ci
	 *	sont cloturées avant execution.
	 *
	 *	SIGNATURES:
	 *		exec()
	 *		exec($param, $value[, $datatype])
	 *		exec($params[, $typeArray])
	 */
	function exec()
	{	$this->auto_exec=true; if($this->error) return $this;

		# Si la requête a déjà été éxécutée et qu'il
		# n'y a pas de nouveu params soumis on sort
		if(($n=func_num_args())==0 && $this->exec_state==2) return $this;

		# Si la fonction est appélée alors qu'il n'y a pas de requête on sort.
		if(!$this->stack && !@$this->params['queries'])return $this->IniErr('NoQuery', 'WDB::exec()');

		if($n>0)
		{	call_user_func_array(array($this, 'bind'), func_get_args());
			if($this->error) return $this;
		}

		// Si le nombre de params soumis n'est pas ok, on sort
		if($this->UParamNbr() != $this->UParamSubmited())
			return $this->IniErr('ParamValExpected');

		if($this->stack && !$this->getQuery())
		{	if(count($this->stack) > 1)$this->end();
			$this->addQuery($this->stack[0], 0);
			if($this->error) return $this;
		}

		$P = &$this->params; $exec = @$P['exec'];

		try
		{	foreach($P['queries'] as &$tab)
			{
				if($this->pdoS)
				{	$this->pdoS->closeCursor();

					// On detruit cet object s'il s'agit d'une nouvelle requête
					if(!$exec)$this->pdoS=null;
				}

				if(@$tab['params'])
				{
					# We prepare the request only if it had not already been executed

					if(!$exec && !($this->pdoS = $this->pdo->prepare($tab['query'])))
					{	$E = $this->pdo->errorInfo();
						if(!$E[2])return $this->IniErr('QueryExecFail');
						return $this->IniErr('PDOError', 'PDO::prepare()', $E[0].'('.$E[1].')', $E[2]);
					}

					foreach($tab['params'] as $pname => $val)
					{	if(is_null($val = $P['allparams'][$pname]))$val='NULL';
						if(($t = @$P['types'][$pname])!==\PDO::PARAM_INT && $t!=\PDO::PARAM_STR && $t!=\PDO::PARAM_LOB)
						$t = is_resource($val)? \PDO::PARAM_LOB : (is_int($val)?\PDO::PARAM_INT:\PDO::PARAM_STR);
						$isVar = @($P['ref'] && in_array($pname, $P['ref']));

						if(!@$this->pdoS->bindValue($pname, $val, $t))
						{
							if($P['UNamed'] && in_array($pname, $P['UNamed']))
								return $this->IniErr('BindErr', $pname);

							if($P['UAnonymes'] && in_array($pname, $P['UAnonymes']))
								return $this->IniErr('BindErrA', array_search($pname, $P['UAnonymes'], true) +1);

							return $this->IniErr('InternError', 'PDOStatement::bind() fail.');
						}
					}

					if(!@$this->pdoS->execute())
					{	$E = $this->pdo->errorInfo();
						if(!$E[2])return $this->IniErr('QueryExecFail');
						return $this->IniErr('PDOError', 'PDOStatement::prepare()', $E[0].'('.$E[1].')', $E[2]);
					}
				}
				elseif(!$this->pdoS = $this->pdo->query($tab['query']))
				{	$E = $this->pdo->errorInfo();
					if(!$E[2])return $this->IniErr('QueryExecFail');
					return $this->IniErr('PDOError', 'PDO::query()', $E[0].'('.$E[1].')', $E[2]);
				}

				$this->exec_state = 2;
			}
		}catch(\Exception $e)
		{	$this->pdoS=null; return $this->PException($e);
		}


		# S'il y a des colonnes à attacher on le fait.
		if(@$this->params['bcol'])
		{	foreach($this->params['bcol'] as $t)
			{
				@$this->pdoS->bindColumn($t[0], $t[1], $t[2], $t[3], $t[4]);
			}
			unset($this->params['bcol']);
		}

		$this->state=0; $P['exec'] = true; return $this;
	}


	function query($query)
	{	$this->c(); if($this->error)return $this; $this->init(false);
	
		if($this->auto_exec===false)$this->auto_exec='';
		elseif($this->auto_exec==='')$this->auto_exec=true;

		if(!($q=$this->analyse_expr($query)))return $this->IniErr('QueryFail');
		$this->addQuery($q, 0); $this->ci="QUERY";

		if(!$this->UParamNbr() && $this->auto_exec)
		{	try{ $this->pdoS=$this->pdo->query($q); }catch(\Exception $e)
			{ $this->pdoS=null; return $this->setErr('PDO Error: '.$e->getMessage());}

			if(!$this->pdoS)
			{	$msg = $this->pdo->errorInfo();
				return $this->setErr('PDO Error: '. $msg[2]);
			}
			$this->exec_state=0; $this->auto_exec=true;
		}
		return $this;
	}


	function fetch($fetch_style=\PDO::FETCH_ASSOC, $cursor_orientation=\PDO::FETCH_ORI_NEXT, $cursor_offset=0)
	{	return $this->isOK()?$this->pdoS->fetch($fetch_style,  $cursor_orientation,
		$cursor_offset) : null;
	}

	/*	SIGNATURE:
	 *	fetchAll ([ int $fetch_style [, mixed $fetch_argument [, array $ctor_args = array() ]]] ) 
	 *
	 */
	function fetchAll()
	{	if(!$this->isOK()){ return null;}
		try
		{	$args=func_get_args(); if(!($n=func_num_args()))return $this->pdoS->fetchAll(\PDO::FETCH_ASSOC);
			if($n==1)return $this->pdoS->fetchAll($args[0]);
			elseif($n==2)return $this->pdoS->fetchAll($args[0], $args[1]);
			else return $this->pdoS->fetchAll($args[0], $args[1], $args[2]);
		}catch(\Exception $e){ $this->PException($e); return null;}
	}

	function listAll($col=0){return $this->fetchAll(\PDO::FETCH_COLUMN, $col);}

	function nextRowset(){ return $this->isOK()?$this->pdoS->nextRowset():null;}

	function rowCount(){ return $this->isOK()?$this->pdoS->rowCount():0;}

	function columnCount(){ return $this->isOK()?$this->pdoS->columnCount():0;}

	private function isOK()
	{	if($this->error)return null;
		if($this->exec_state!=2 || !$this->pdoS)$this->exec();
		return $this->error? null:true;
	}

	/*
	 *	$table: nom de la table
	 *	$keys : le nom ou les noms de clés de la table. S'il ya une seule clé est pourra
	 *			être donnée sous forme de String ou Array. S'il y a plusieurs clés, elles
	 *			doivent être données sous forme de Array
	 *
	 *	$keyValues:
	 *			La valeur ou les valeurs des clés. Si la clé est donnée sous forme de String
	 *			cette valeur sera aussi une valeur unique. Si la clé ou les clés sont données
	 *			sous forme de Array, la valeur sera aussi sous forme de Array. Dans ce tableau
	 *			chaque valeur une valeur de la clé. Si la clé est multiple, chaque valeur doit
	 *			être aussi multiple.
	 *
	 *	$cols : Les noms des colonnes à renvoyer. Si omis, toutes les colonnes sont renvoyées.
	 */
	function get($table, $keys, $keyValues, $cols=false)
	{	$this->c();$auto=$this->gAE(); if($this->error())return $this;
		$v=$keyValues; $k=$keys; $c=(($ia=is_array($k)) && $k)?count($k):0; $op='';

		// Ici la valeur n'est pas un array 
		if(!is_array($v))
		{	if($ia)return $this->IniErr('BADPRANGET', 'WDB::get()', 'second',
			'an Array', 'third', 'an Array', 'An '.gettype($v)); $w[$k] = $v;
		}
		// ici la valeur est un array et la clé n'est pas un array
		elseif(!$ia)$w[$k] = $v;

		// Dans la suite la valeur et la clé sont tous des array
		elseif(!$k || !$v)return $this->IniErr('BADPRAM', 'WDB::get()');

			//si la première valeur n'est pas un array, on suppose que c'est une valeur multiple
		elseif(!is_array(reset($v)))
		{	if(!($w=@array_combine($k, $v)))return $this->IniErr('BADPRAM', 'WDB::get()');
			$w[0] = 'AND';
		}
		else foreach($v as $t)
		{	if(!is_array($t) || count($t)!=$c) return $this->IniErr('BADPRANGET2', $c);
			if($op)$op.=' ';else $op='AND'; $w[$op] = array_combine($k, $t);
		}

		$this->auto_exec=$auto; $this->select($table, $cols?$cols:'*', $w);
		return $this->exec_state?$this->fetchAll() : $this;
	}

	/*
	 *	insert($table, $rows)
	 *	insert($table, $row1, $rows2, ..., $row_n)
	 *
	 *	Les differentes données doivent avoir les mêmes clés.
	 *
	 *
	 *
	 *	NB: Cette fonction échoue sur une table possédant une contrainte ou fonction BEFORE_INSERT 
	 *	ou AFTER_INSERT si plusieurs lignes doivent être inserées et que des parametres ? ou :param
	 *	sont utilisés.
	 */
	function insert()
	{	//if($this->ci)return $this->IniErr('STATMENT', $this->ci);
		$this->c(); if($this->error)return $this; $this->init(false);
		$this->ci = "INSERT";

		if($this->auto_exec===false)$this->auto_exec='';
		elseif($this->auto_exec==='')$this->auto_exec=true;

		try
		{	$list = func_get_args();
			if(($n=func_num_args())<2)return $this->IniErr('BADPRAMNUM2','WDB::insert()');
			if(!($tab=func_get_arg(0)) || !is_string($tab))return $this->IniErr('BADTAB', 'WDB::add()');

			if($n==2 && ($p1=func_get_arg(1)) && (is_object($p1)
			|| is_array($p1) && !is_array($e1=next($p1)) && !is_object($e1))){ $i=1; }

			elseif($n==2)
			{	if(!is_array($list[1]))return $this->IniErr('BADPRAMFRMT1','WDB::insert()','2nd');
				$i=0; $list=$list[1];
			}else $i=1;

			$dbt=$this->dbtype; $tab2=self::escapeMultifieldId($tab, $dbt); $s=''; 
			$keys=''; $l=count($list); $nb=$l-$i; $oracle=($nb>1 && $dbt==self::ORACLE);

			for($k=$i; $k<$l; $k++)
			{
				if(!isset($lc))$lc = count($list[$k]);
				else{ if(($lc2=count($list[$k]))!=$lc) return $this->IniErr('INSERT_COL_ERROR');
					$lc = $lc2;
				}

				if(!($x=$this->joinValues($list[$k], 0, false, false, $orS)))
				{	return $this->IniErr('InsMSG','wdb::insert()');}

				/* FOR ORACLE ONLY. $ors is non-empty if lobs are present.
				 * If several elements containing lobs must be processed, it is an error
				 */
				if($nb>1 && $orS)return $this->IniErr('ORACLE_SEVERAL_LOB_ERR');

				if(!$keys)$keys = join(',', $this->temp['Qkeys']);
				if($oracle) $s.="\n	INTO $tab2($keys) VALUES($x)";
				else $s.=($s?',':'').'('.$x.') '.$orS;
			}

			if($oracle) $this->stack[0] = 'INSERT ALL'.$s."\nSELECT 1 FROM DUAL";
			else $this->stack[0] = 'INSERT INTO '.$tab2.'('.$keys.')VALUES'.$s;
		}
		catch(\Exception $e){ return $this->PException($e);}

		return ($n>=2 && $this->auto_exec && !$this->UParamNbr())? $this->exec() : $this;
	}


	function add(){ return call_user_func_array(array($this, 'insert'),func_get_args()); }


	/*	SIGNATURES
	 *	update($table, $data, $where)
	 *	update($table, $index, $row_list)
	 *	update($table, $index, $row1, $row2)
	 *
	 *	Les données n'ont pas besoin d'être uniformes
	 */
	function update()
	{	$this->c(); $this->init(false); $dbt=$this->dbtype;

		if($this->auto_exec===false)$this->auto_exec='';
		elseif($this->auto_exec==='')$this->auto_exec=true;

		if(($n=func_num_args())<3)return $this->IniErr('BADPRAMNUM6','WDB::update()','3',$n);
		if(!is_string($tab=func_get_arg(0)) || $tab==='')return $this->IniErr('UpdtMSG');
		$where=$index=null;$wprs=false; $qv=$this->qv;

		if(!is_array($e1=func_get_arg(1)) && !is_string($e1) && !is_object($e1)||!$e1)return $this->IniErr('BADPRAMFRMT1', 'WDB::update()', 'second');

		// Première signature (pas d'index)
		if(($n==3) && is_array($e1) && !is_array($next=next($e1)) 
			&& self::isAssociativeArray($e1) ||is_object($e1))
		{
			if(is_bool($where=func_get_arg(2)) || $where===null || $where=='' || is_numeric($where))
			{	$this->IniErr('BADTABLIST', 'WDB::update()');
				#$where='1=1';
			}

			if(!is_string($where) && !is_array($where)
				|| is_numeric($where))return $this->IniErr('BADPRAMn3','WDB::update()');

			if(($where=$this->stringify_cond($where))===FALSE)
					return $this->IniErr('BADCOND','WDB::update()');

			// les données à sauvegarder sont dans le Array $list
			$list=func_get_args(); $j=$k=1; $index=false;
		}

		// Signatures avec index
		else
		{	if( ($x=(!($index=$e1) || !is_string($e1) && !is_array($e1))) ||
			!($list=func_get_arg(2)) || !is_array($list))
			{
				return $this->IniErr('UpdtMSG33', $x?'second':'Third');
			}

			if(self::HasArray($list)) # Toutes les données sont dans un tableau.
			{	if($n > 3)return $this->IniErr('UPDATE_SIGNATURE_ERROR');
				$j=0; $nb=count($list); $k=$nb-1;
			}
			else # Les données sont listées les une après les autre à la suite de la clé
			{
				$list=func_get_args(); $j=2; $k=count($list)-1; $nb=count($list)-2;
			}
		}

		if($index){ if(is_string($index))$qindex=self::escapeMultifieldId($index, $dbt); }
		elseif(!$where)return $this->IniErr('UpdtMSG1');
		$qtab = self::escapeEntity($tab, $dbt); $orS='';

		try
		{   for($i=$j; $i<=$k; $i++)
			{
			    if(($q=$this->joinValues($list[$i], ($qIndice=$i-$j), true, $index, $orS))===FALSE)
				{	return $this->IniErr('UpdtMSG2'); } $w='';

				if($index)
				{	// Cas d'index composite
					if(is_array($index))
					{
						foreach($index as $cl)
						{	if(is_array($list[$i]) && ($t=1) && !array_key_exists($cl, $list[$i]) ||
							   is_object($list[$i]) && ($t=2) && !property_exists($list[$i], $cl)
							)return $this->IniErr('UpdtMSG3', $cl);

							if(!($w1=$this->addInternalVal(
								($t==1)?$list[$i][$cl]:$list[$i]->$cl, $qIndice)))
								return $this->IniErr('UpdtMSG5');

							$w .= ($w?' AND ' :'').self::escapeEntity($cl, $dbt).'='.$w1;
						}
						$q.=' WHERE '.$w;
					}

					// Cas d'un index simple
					else
					{	if(is_array($list[$i]) && ($t=1) && !array_key_exists($index, $list[$i]) ||
						   is_object($list[$i]) && ($t=2) && !property_exists($list[$i], $index)
						)return $this->IniErr('UpdtMSG3', $index);

						if(!($w=$this->addInternalVal(
							($t==1)?$list[$i][$index]:$list[$i]->$index, $qIndice)))
							return $this->IniErr('UpdtMSG5');

						$q.=' WHERE '.self::escapeEntity($index, $dbt).'='.($w?$w:$v);
					}
				}
				else{ $q.=' WHERE '.$where; }
				$q = 'UPDATE '.$qtab.' SET '.$q.' '.$orS;

				$this->addQuery($q, $qIndice);
			}

			$this->ci='UPDATE';
			return $this->auto_exec && !$this->UParamNbr()? $this->exec() : $this;

		}catch(\Exception $e)
		{	return $this->PException($e);
		}
	}
	
	static function HasArray($var)
	{	if(is_array($var) && $var)foreach($var as &$val) if(is_array($val))return true;
		return false;
	}

	/*	Cette fonction joint les valeurs d'un objet ou Array donné, pour obtenir une
	 *	chaîne de aractères utilisable dans l'instruction UPDATE ou INSERT.
	 *
	 *	$obj	: l'objet de données à assembler. Object ou Array
	 *	$qIndice: L'indice de $obj dans le set à traiter, >=0
	 *
	 *	$upd	: Vaut TRUE si les données sont utilisées dans le cadre d'un UPDATE,
	 *			  et FALSE si celles-ci le sont dans le cadre de INSERT
	 *
	 *	keyCol	: Indiquera le nom de l'index de la table. Si donné, cette colonne
	 *			  n'est pas incluse parmi les données serialisées
	 *
	 *	$OracleR: Requête pour oracle. Ne sera present que si le DBMS est oracle, et
	 *			  si au moins une des colonnes a une valeur de type ressource
	 *
	 *	Seules les valeurs sont jointes. La partie WHERE (cas de UPDATE) n'est pas créée.
	 */
	private function joinValues($obj, $qIndice, $upd=false, $keyCol=false, &$OracleR=false)
	{	$f=false; if(is_object($obj))$obj=(array)$obj; $dbtp=$this->dbtype; $eb=$this->exp_begin;

		/*
		 *	Qkeys
		 *	Sera un tableau des noms des colonnes quotés. Dans le cas $upd=FALSE
		 *	Ces noms de colonnes sont rassemblés plutard pour former la chaîne
		 *	située entre les mots-clés INSERT et VALUES. Dans le cas $upd=TRUE,
		 *	ces noms de colonnes sont utilisés pour ne pas avoir à repeter toujours
		 *	le processus de quotage des mêmes clés.
		 */

		if(!$this->temp){ $this->temp=array('ref'=>$obj, 'Qkeys'=>false); $ref=&$obj; }
		else{ $ref=&$this->temp['ref']; } $Qkeys=&$this->temp['Qkeys'];

		if($keyCol && !is_string($keyCol) && !is_array($keyCol))return FALSE;

		if($keyCol && is_string($keyCol) &&
		(!array_key_exists($keyCol, $obj) || !is_scalar($obj[$keyCol])))return FALSE;

		$s=''; $qv=$this->qv; $ins=!$upd; $OracleR=''; $isS=is_string($keyCol);
		$isA=is_array($keyCol); $ORA=($dbtp==self::ORACLE);

		foreach($ref as $key => $val)
		{	if($keyCol && ($isS && $key===$keyCol || $isA && in_array($key, $keyCol)))continue;

			$ikey=$key; $op=$p=0; if($ins && !array_key_exists($key, $obj))
			{ return $this->IniErr('MisCol', "\"$key\"");return $f;}

			$par=1; if($upd && is_string($key))$op=self::isUOp($key);

			if((!$Qkeys || !array_key_exists($key, $Qkeys)))
			{	$Qkeys[$key] = $k = self::escapeEntity($key, $dbtp);
			}elseif($upd){$k = $Qkeys[$key];} 

			$isr=is_resource($v = $obj[$ikey]); $v2=$v;
			if(is_bool($v))$v=$v?1:0; 
			elseif(is_null($v)){ $v='NULL'; $par=0;}
			elseif(!is_string($v) && !is_numeric($v) && !$isr){return $f;}

			if($par)
			{	$v = $this->addInternalVal($v, $qIndice, 1);
				if($ORA && $isr && !$OracleR)
				{ $OracleR = " RETURNING $k INTO $v"; $v='EMPTY_BLOB()'; }
			}

			if($upd)
			{	$SF = ($upd==self::SQLITE || $upd==self::FIREBIRD);
				if($p)$v=$p; $s.=($s?',':'').$k.'='.(($op=='.=')?
				($SF? $k.'||'.$v :'CONCAT('.$k.','.$v.')'):($op?$k.$op[0]:'').$v);
			}
			else $s.=($s?',':'').($p?$p:$v);

			if(!$isr && $ORA && is_string($v2) && $v2 && strpos($v2[0], $eb)===0 &&
			($v2=trim(substr($v2, strlen($eb)))) && ($v2=='?' || $v2 && $v2[0]==':'))
			{	$this->params['ORA_E_PARAMS_COL'][$v] = $k; }
		}
		return $s?$s:false;
	}


	/*	Fonctions generale de jointure. Voici toutes les signatures
	 *
	 *		xjoin($tabname, $x, $cond)
	 *		xjoin($tables, $x)
	 *
	 *		$tabname: nom de l'unique table, string
	 *
	 *		$tables : array(
	 *			'tabname1'	=> $cond1,
	 *				. . .
	 *			'tabnamej'	=> $condj,
	 *		)
	 *
	 *		$cond	:	Optionnel, une condition WDB pour la table à joindre. Sera
	 *					present seulement dans le cas de la première signature.
	 *
	 *		$x		:	''|'l'|'r'|'f'
	 */
	private function xjoin($tables, $x='', $cond=false)
	{	if($this->error)return $this; $q=''; $f=$x.'join';
		$kw = (($x=='l')?'LEFT ':($x=='r'?'RIGHT ':($x=='f'?'FULL ':''))).'JOIN';

		if($this->state!=1 && $this->state!=3)return $this->IniErr('BADCMD', $kw);

		if(!$tables||!is_string($tables)&&!is_array($tables))
			return $this->IniErr('BADPRAMTYP4', $f); $dbt=$this->dbtype;

		if(is_array($tables))foreach($tables as $key => $val)
		{	if(is_string($key))
			{	if(!$key || $key && !trim($key))return $this->IniErr('BADPRAMTYP4', $f);

				$key = self::escapeEntity($key, $dbt);

				if(($val=$this->stringify_cond($val, false)) === FALSE)
				{	return $this->IniErr('BADCOND ','WDB::join()');}

				$q .= "\n".$kw.' '.$key.' ON '.($val && is_string($val)?$val: '1').'';
			}
			elseif(is_string($val))
			{	if(!($val=trim($val))) continue; if(stripos($val, ' ON ')===FALSE)
				{	$val.=' ON 1=1'; $q .= "\n".$kw.' '.$val.''; }

				else $q .= "\n".$this->analyse_expr($q).'';
			}
			else return $this->IniErr('BADPRAMTYP5', $f);
		}
		else
		{	$tables = self::escapeEntity($tables, $dbt);
			if(!($cond = $this->stringify_cond($cond, false)))return $this->IniErr('BADCOND', 'WDB::'.$f.'()');
			$q = "\n".$kw.' '.$tables.' ON '.$cond.'';
		}

		$this->state=3;
		$this->stack[$this->stack?(count($this->stack)-1):0].=$q;
		return $this;
	}


	/*	Verifie si l'etat actuel de l'entité est compatible par rapport au états
	 *	prohibés ou permis. Si les états prohibés sont donnés, la compatibilité
	 *	sera verifiée si l'état actuel de l'entité n'en fait pas partie. Si par
	 *	contre c'est les états permis qui sont fournis, la compatibilité sera vé-
	 *	rifiée si l'état de l'entité en fait partie.
	 *
	 *	- $cond:		:	Une chaîne de caractères non vide obligatoire
	 *	- $statement	:	La requête SQL impliquée, en majuscule.
	 *	- $fonct		:	La fonction impliquée.
	 *	- $dest_state	:	L'état suivant de l'entité, si tout se passe bien
	 *	- $states_prohibed	:	L'etat ou les états prohibés
	 *	- $states_accepted	:	L'etat ou les états permis
	 *
	 *
	 *	Si la compatibilité est vérifiée l'entité est retournée, sinon FALSE
	 *	est renvoyé.
	 *
	 *	Si la compatibilité est vérifiée, la chaine $cond est ajoutée à la requête
	 *	en cours. Cette dernière doit normalement contenir la chaîne statement au
	 *	au debut. Si ce n'est pas le cas, la chaîne statement est ajoutée au debut
	 *	de $cond avant que $cond ne soit ajoutée à la requête en cours.
	 *
	 */
	private function xyz($cond, $statement, $fonct, $dest_state, $states_prohibed=false, $states_accepted=false)
	{	if($this->error)return $this; $S=$this->state; $f=false;
		$sp = &$states_prohibed; $sa = $states_accepted;

		/*	If the current state is in the list of prohibited states or
		 *	If the current state is not in the list of allowed states, Error!
		 */
		if($sp!==FALSE && (is_int($sp) && $S==$sp || in_array($S, $sp))
		|| $sa!==FALSE && (is_int($sa) && $S!=$sa || !in_array($S, $sa))
		){ return $this->IniErr('BADCMD', $statement);}

		if(!is_string($cond)||!trim($cond)){ $this->IniErr('BADPRAMTYP6'); return $f;}

		$statement=trim($statement);
		if(strpos($cond, '  ')!==FALSE) $cond=str_replace(array('   ', '  '), ' ', $cond);
		if(stripos($cond, $statement)!==0) $cond = $statement.' '.$cond;

		#$this->stack[$this->stack?(count($this->stack)-1):0].="\n".$cond;
		$this->stack[count($this->stack)-1].="\n".$cond; 
		$this->state=$dest_state; return $this;
	}

	/*	Renvoie le code SQL de la dernière requête si elle n'est pas multiple. Si la requête
	 *	est multiple (UPDATE, INSERT) la requête correspondant à l'index $i est renvoyée si
	 *	$i est fourni. Sinon la requête d'index 0 est renvoyée.
	 */
	function getLastSQL($i=0){
		$this->c();return ($t=@$this->stack)?$t[count($t)-1] : (($t=$this->getQuery($i))?$t:'');
	}

	function sql($i=false){return $this->getLastSQL($i);}

	function raw($i=false)
	{	$this->c();$P=$this->params; if(!($Q=@$P['queries']) && !$this->stack)return '';

		// Si le nombre de params soumis n'est pas ok, on sort
		if($this->UParamNbr() != $this->UParamSubmited())
		{ $this->IniErr('ParamValExpected'); return '';} $s='query'; $Q0=@$Q[0];

		if(($t=$this->stack)){$q=$t[count($t)-1]; if(!($tab=@$Q[0]))return $q;}
		else{ if($i==false)$i=0; if(!($tab=@$Q[$i]))return ''; $q=@$tab[$s];}

		if(@$tab['params'])
		{	foreach($tab['params'] as $name => $v)
			{	$t=''; if(is_null($v=$P['allparams'][$name])){$v='NULL';}
				elseif(is_bool($v)){$v=$v?1:0;}
				elseif(is_resource($v))$v=$name;
				elseif(!($t=@$this->params['ptypes'][$name]) && !is_int($v)
				|| $t==\PDO::PARAM_STR)$v = $this->pdo->quote($v);//$v=self::escapeEntity($v, $this->dbtype, "'");

				$search[]=$name; $rep[]=$v;
			}

			$m=''; if(($Z=strpos($q, ' RETURNING '))!==FALSE && 
			strpos($q, ' INTO ')!==FALSE){ $m=substr($q, $Z); $q=substr($q, 0, $Z); }

			$q = str_replace($search, $rep, $q).$m;
		} return $q;
	}

	/*	Signatures
	 *		delete($table, $where)
	 *		delete($table, $keyname, $keyValueList)
	 *		delete($table, $keyname, $val1, ... $valj)
	 */
	function delete($table, $x)
	{	$this->c();$this->init(); if(!$table||!is_scalar($table))
		return $this->IniErr('BADTABLIST', 'WDB::delete()');

		if(($n=func_num_args())==2)
		{	if(!($w=func_get_arg(1)) || is_bool($w) || is_numeric($w))
			return $this->IniErr('BADCOND', 'WDB::delete()');
			$w=$this->stringify_cond($w);
		}else
		{	if(!($key=func_get_arg(1)))return $this->IniErr('NOKEY');
			if($n==3)$val=func_get_arg(2);
			else
			{	$t=func_get_args(); $val=false;
				for($i=2; $i<=$n-1; $i++)$val[]=$t[$i]; unset($t);
			}

			$w=$this->combineKeyValue($key, $val);
			if($this->error)return $this;
		}

		if(!($w=$this->stringify_cond($w)))return $this->IniErr('BADCOND', 'WDB::delete()');
		if($this->error)return $this;

		$table=self::escapeMultifieldId($table, $this->dbtype);
		$this->addQuery('DELETE FROM '.$table.' WHERE '.$w, 0);

		return ($this->auto_exec &&!$this->UParamNbr())? $this->exec() : $this;
	}

	
	private function gAE(){$au=$this->auto_exec;$this->auto_exec=false;return $au;}


	function combineKeyValue($key, $val)
	{	$f = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$f='WDB::'.(isset($f[1])?$f[1]:$f[0])['function'].'()';

		if(is_array($key) && count($key)==1){reset($val); $key=current($key);}
		if(!$key||$key===TRUE||!$val)return $this->IniErr(!$val?'NOKEYVAL':'NOKEY2');
		if(!is_array($key)){if(!is_numeric($key))$key.=''; return array($key=>$val);}

		// Si la valeur n'est pas un array
		if(($n=count($key)) && !is_array($val))
		{	if($n!=1) return $this->IniErr('RowIndexVal', $f, $n);
			#reset($val); return array($key => current($val));
			reset($key); return array(current($key) => $val);
		}

		// Dans toutes la suite $key est un Array avec au moins 2 elements
		// et $val est aussi un Array
		if(count($val)!=$n && !is_array(current($val)))return $this->IniErr('RowIndexVal', $f, $n);
		reset($val); reset($key); $w=false; $vIa=0; // indique si les valeurs sont des array ou non

		foreach($val as $v)
		{	if(is_array($v))
			{	// Cas ou une valeur est scalaire alors que les premières sont des array
				// ou Cas ou une valeur est un array dont le nombre d'elements != $n
				if($vIa===false || $vIa && count($v)!=$n)return $this->IniErr('KeyError2', $f, $n);
				if(!$w)$w[0]='OR'; if(!($x=@array_combine($key, $v)))return $this->IniErr('KeyError2', $f, $n);
				$x[0]='AND';
				$w[]=$x; $vIa=true; continue;
			}
			$k = current($key); next($key); if(!$w)$w[0]='AND';
			if(!$k || !is_scalar($k) || $vIa===true)
				return $this->IniErr($vIa===true?'KeyError4':'KeyError3', $f);
			$w[$k] = $v; $vIa=false;
		}
		return $w;
	}

	/* 
	mysql:unix_socket=/tmp/mysql.sock;dbname=testdby
	mysql:host=localhost;dbname=testdb;charset=utf8mb4

	mysql, sqlserver, oracle, cubrid, firebird, ibm, informix,
	postgresql

	https://www.oos-shop.de/doc/php_manual_de/html/ref.pdo-sqlsrv.html
	https://www.oos-shop.de/doc/php_manual_de/html/ref.pdo-sqlsrv.connection.html
	https://www.php.net/manual/fr/ref.pdo-dblib.php
	https://www.oos-shop.de/doc/php_manual_de/html/ref.pdo-odbc.connection.html
	*/
	static function dsn($cf, &$dbtype=0, &$nbr=0, &$user='', &$psw='')
	{	if(!$cf||!is_array($cf)||!($tp=@$cf['type']))return false; $dbtype=0;
		if(!is_numeric($tp) && !($tp=wdb::TypeFromString($tp)))return self::setIError('BADDBTYP2');

		$user=isset($cf['user'])?$cf['user']:(isset($cf['username'])?$cf['username']:'');
		$psw=isset($cf['psw'])?$cf['psw']:(isset($cf['pswd'])?$cf['pswd']:
		(isset($cf['password'])?$cf['password']:'')); if(!$nbr)$nbr=0;

		if(is_string($tp))$tp=strtolower($tp); $pf=@$cf['prefix']; $dbtype=$tp;
		if($nbr>3 && $tp!=self::SQLSERVER && $n>1 && $tp!=self::SQLITE)return false;


		switch($tp)
		{
			case wdb::MYSQL: return self::dsn_str($cf, 'mysql:', '!host=', 'server|host',
			';port=', '', ';dbname=', 'db|dbname|database', ';charset=', '');

			case wdb::SQLITE: $user=$psw=null;
			return self::dsn_str($cf, $nbr?'sqlite2:':'sqlite:', '!', 'file|path|db|dbpath|dbfile|filepath|dbname');


			case wdb::SQLSERVER:case wdb::SYBASE:
			/*	Avec SQL Server, il faut specifier le prefix utilisé,
			 *	ce dernier dépendant du driver et du type de base de
			 *	données utilisé.Voici les prefixes utilisés:
			 *	Pour SQL Server - sqlsrv: et mssql: si on utilise
			 *	Pour Sybase - sybase: et dblib:
			 *
			 *	Ces prefixes correspondent au drivers suivants:
			 *	pdo_sqlsrv, pdo_dblib
			 */
			if(!($pf=@$cf['prefix']) || !($pf=trim(strtolower($pf))))
			return self::setIError('NoPrefix');

			return ($pf=='sqlsrv:')? self::dsn_str($cf, $pf, 
			'!Server=','server|host', ',','port', ';Database=','db|dbname|database',
			';APP=','', ';Encrypt=','', ';Failover_Partner=','',
			';MultipleActiveResultSets=','', 'WSID','',
			';QuotedId=','', ';TraceFile=','', ';TraceOn=','',
			';TransactionIsolation=','', ';TrustServerCertificate=',''):

			self::dsn_str($cf, $pf, '!host=','server|host', ';dbname=',
			'db|dbname|database', ';charset=','', ';appname=','', ';secure=','');

			# oci:dbname=//hostname:port-number/database;charset
			case wdb::ORACLE:
			return self::dsn_str($cf, 'oci:', '!dbname//', 'server|host',
			':','port', '!/', 'db|dbname|database', ';charset', '');


			case 'cubrid':	#"cubrid:dbname=demodb;host=localhost;port=33000";
			return self::dsn_str($cf, 'cubrid:', '!host=', 'server|host',
			';port=', 'port', ';dbname=', 'db|dbname|database');
			break;

			case self::FIREBIRD:
			$dbtype=self::FIREBIRD;
			return self::dsn_str($cf, 'firebird:', '!dbname=', 'dbname|database|file|path|db|dbpath|dbfile|filepath',
			';charset=', 'charset', ';dialect=','dialect', ';role=','role');
			break;

			# ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=database;HOSTNAME=hostname;PORT=port;PROTOCOL=TCPIP;UID=username;PWD=password;
			# https://www.php.net/manual/fr/ref.pdo-ibm.connection.php

			# informix:host=host.domain.com; service=9800;database=common_db; server=ids_server; protocol=onsoctcp;EnableScrollableCursors=1
			# Attention, il faudra traiter les cas IBM et Informix differemment
			case self::INFORMIX:
			if(!$s)return false;
			$dsn='ibm:DRIVER={IBM DB2 ODBC DRIVER}';
			if(!empty($cf['dbname']))$dsn.=';DATABASE='.$cf['dbname'];
			$dsn.=';HOSTNAME='.$s;
			if(!empty($cf['port']))$dsn.=';PORT='.$cf['port'];
			if(empty($cf['charset']))$dsn.=';charset='.$cf['charset']; 
			$dsn.=';PROTOCOL=TCPIP;'; $dbtype=self::INFORMIX; break;

			case self::DB2:
			if(!$s)return false;
			$dsn='ibm:DRIVER={IBM DB2 ODBC DRIVER}';
			if(!empty($cf['dbname']))$dsn.=';DATABASE='.$cf['dbname'];
			$dsn.=';HOSTNAME='.$s;
			if(!empty($cf['port']))$dsn.=';PORT='.$cf['port'];
			if(empty($cf['charset']))$dsn.=';charset='.$cf['charset']; 
			$dsn.=';PROTOCOL=TCPIP;'; $dbtype=self::INFORMIX; break;

			case self::POSTGRESQL:
			# POSTGRESQL a besoin du nom utilisateur et mot de passe dans le dsn
			return self::dsn_str($cf, 'pgsql:', '!host=', 'server|host',
			';port=', 'port', ';dbname=', 'db|dbname|database', ';user=','user', 
			';password=', 'psw|pswd|password');

			case self::DB2:

			default: return false;
		}
		return $dsn; 
	}

	function sgbd(){return $this->dbms();}
	function dbms(){$this->c();return self::sdbtype($this->dbtype);}
	
	/*	Generation d'un nom de parametre PDO. $forExt vaudra TRUE si 
	 *	Le nom est généré pour un param anonyme utilisateur, ceci pour juste
	 *	distinger les noms.
	 */
	static function pname($forExt=false)
	{self::$pnb++;return ':wdb'.($forExt?'E':'I').'prm'.self::$pnb;}

	static function sdbtype($dbtype)
	{	if($dbtype==self::MYSQL) return 'MYSQL';
		if($dbtype==self::SQLITE) return 'SQLITE';
		if($dbtype==self::POSTGRESQL) return 'POSTGRESQL';
		if($dbtype==self::CUBRID) return 'CUBRID';
		if($dbtype==self::INFORMIX) return 'INFORMIX';
		if($dbtype==self::SQLSERVER) return 'SQLSERVER';
		if($dbtype==self::FIREBIRD) return 'FIREBIRD';
		if($dbtype==self::ORACLE) return 'ORACLE DB';
		if($dbtype==self::DB2) return 'DB2';
		if($dbtype==self::SYBASE) return 'SYBASE';
		return '';
	}


	static function TypeFromString($str)
	{	if(!$str||!is_string($str))return false;
		if(strpos(($str=trim(strtoupper($str))), 'MYSQ')===0 ||
		strpos($str,'MAR')===0) return self::MYSQL;
		if(strpos($str,'SQLI')===0)return self::SQLITE;
		if(strpos($str,'POS')===0 || $str=='PGSQL')return self::POSTGRESQL;
		if(strpos($str,'CUB')===0)return self::CUBRID;
		if($str=='IFMX' || strpos($str,'INF')===0)return self::INFORMIX;
		if(strpos($str,'SQLS')===0 || strpos($str,'SQL S')===0
		|| $str=='SQLSRV')return self::SQLSERVER;
		if($str=='FRBD' || strpos($str,'FIRE')===0)return self::FIREBIRD;
		if(strpos($str,'ORA')===0)return self::ORACLE;
		if(strpos($str,'SYB')===0)return self::SYBASE;
		if(strpos($str,'DB2')===0)return self::DB2;
		return false;
	}


	####
	static function Not($tab, $key)
	{	return (!$tab||!is_array($tab)||!isset($tab[$key])||empty($tab[$key]));}

	/*
	 *	Replaces multiple white characters in the string with a single
	 *	space and returns the resulting string. If $trim ist TRUE the
	 *	result ist trimed
	 */
	static function rpmw($str, $trim=true)
	{	if(!$str)return $str; $l=strlen($str);$r='';$i=0;$b='';
		while($i<$l){ if(($c=$str[$i])==' '||$c=="\r"||$c=="\n"||$c=="\t")$b=' ';
			else {if($b&&$trim&&!$r)$r=$c; else $r.=$b.$c;$b='';} $i++; 
		} return ($trim||!$b)?$r:$r.$b;
	}

	function error(){$this->c();return $this->error;}
	static function isInt($val){ return (is_numeric($val) && is_int($val*1))?true:false;}

	private function PException(&$e)
	{	$this->error=$m=$e->getMessage(); $this->auto_exec=true; $ns=__NAMESPACE__;
		if(is_a($e, $ns.'\WDBException')||is_a($e, $ns.'\PDOException'))
		{	if($this->error_rep==\PDO::ERRMODE_WARNING)echo $m;
			elseif($this->error_rep == \PDO::ERRMODE_EXCEPTION){throw $e;}
		}else $this->IniErr('IntErr2', $e->getLine(), $m); return $this;
	}


	static function ExeptionError($error, &$full_error=false, $error_rep=PDO::ERRMODE_EXCEPTION)
	{	$E = new WDBException($error); $html = $E->ErrorHtml();
		$full_error = $E->__toString();
		if($error_rep==\PDO::ERRMODE_WARNING)echo $html;
		elseif($error_rep == \PDO::ERRMODE_EXCEPTION){ throw $E;}
		return $html;
	}

	static function WDBException($msg)
	{	$ctx=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
		$ctx=$ctx[1]; //$msg='Error in file '.$ctx['file'].', line '.$ctx['line'].': '.$msg;
		$msg = $msg.' - LINE '.$ctx['line'].', FILE '.$ctx['file'].'.';
		throw new WDBException($msg, $ctx['file'], $ctx['line']); return null;
	}

	private function setErr($error)
	{	$this->auto_exec=true;
		WDB::ExeptionError($error, $this->error, $this->error_rep); return $this;
	}


	/*	La signature
	 *	setIError($code[, $var1, ..., $varn] [, $obj])
	 *
	 *	- $code				: le code d'une chaine dans le fichier errors.ini
	 *	- $var1, ... $varn	: des variables optionnelles de la chaîne referencée par $code
	 *	- $obj				: un Array ou object (optionnel), dernier parametre. Il sera la
	 *						  donnée, ou contiendra les élements ayant causé l'erreur
	 */
	static function setIError($code)
	{	#if(($e=self::$error_rep)!=\PDO::ERRMODE_SILENT &&
		#$e!=\PDO::ERRMODE_WARNING)$e=\PDO::ERRMODE_EXCEPTION; self::$errobj=null;

		$e=\PDO::ERRMODE_EXCEPTION;;
		$p = func_get_args(); if(($n=func_num_args())>=2 && 
		!is_scalar($p[$n-1])){ self::$errobj = array_pop($p);}

		$y=false; self::ExeptionError(call_user_func_array(array(__NAMESPACE__ .'\WDB', 'ErrorFromFile'), 
		$p), $y, $e); return false;
	}


	/*	La signature
	 *	IniError($code[, $var1, ..., $varn] [, $obj])
	 *
	 *	- $code				: le code d'une chaine dans le fichier errors.ini
	 *	- $var1, ... $varn	: des variables optionnelles de la chaîne referencée par $code
	 *	- $obj				: un Array ou object (optionnel), dernier parametre. Il sera la
	 *						  donnée, ou contiendra les élements ayant causé l'erreur
	 */
	function IniErr($code)
	{	$this->c();$p = func_get_args(); if(($n=func_num_args())>=2 && 
		!is_scalar($p[$n-1])){ self::$errobj = array_pop($p);}
		$this->auto_exec=true;
		namespace\WDB::ExeptionError(call_user_func_array(array(__NAMESPACE__ .'\WDB', 'ErrorFromFile'), $p), 
		$this->error, $this->error_rep); return $this;
	}

	/*	SIGNATURES
	 *	ErrorFromFile($code)
	 *	ErrorFromFile($code, $param1[..., $param_n])
	 */
	static public function ErrorFromFile($code)
	{	if(!self::$iniMsgs)self::$iniMsgs = 
		parse_ini_file(self::$selfdir.'/errors.ini', false);
		$error = self::$iniMsgs[$code];

		if(func_num_args()>1){ $tab=func_get_args();
			array_shift($tab); array_unshift($tab, $error);
			$error = call_user_func_array('sprintf', $tab);
		}
		return $error;
	}

	/* This function should be improved in future releases.
	 * Retourne l'identifiant de la dernière ligne insérée ou la valeur d'une séquence
	 */
	function lastInsertId($table='', $key='')
	{	$this->c();if($this->error)return 0;
		try{
		$T=&$this; if(($t=$T->dbtype)==wdb::SQLSERVER)
		{	$T->query("select IDENT_CURRENT('$table') AS 'id'");
			if($T->error || !($r=$T->fetch()))return 0;return $r['id'];
		}elseif($t==wdb::FIREBIRD)
		{	$r=$T->select(array($key))->from(array($table))
			->orderby(array($key=>'DESC'))->limit(1)->listAll();
			return (!$r||$T->error)? 0:$r[0];
		}
		return $T->pdo->lastInsertId();
		}catch(\Exception $e){ $T->er=$e->getMessage(); return 0;}
	}

	/*	setAttribute($array)
	 *	setAttribute($at, $val [, $at2, $val, ... $at_n, $val_n])
	 */
	function setAttribute()
	{	$T=$this; $T->c(); if($T->security===TRUE)return $T;
		try
		{	if(($n=func_num_args())==1 && is_array($t=func_get_arg(0)))
			{	foreach($t as $p => $v)
				{  if($p==\PDO::ATTR_ERRMODE)$this->error_reporting($v);
				   else $T->pdo->setAttribute($p, $v);
				} return $T;
			}

			$t=func_get_args(); $i=0;
			while($i<$n){ $p=$t[$i]; $i++; if($i>=$n)break;
			  if($p==\PDO::ATTR_ERRMODE)$this->error_reporting($t[$i]);
			  else $T->pdo->setAttribute($p, $t[$i]); $i++; 
			}
		}catch(\Exception $e){ return $this->PException($e);}
		return $T;
	}

	function getAttribute($attr)
	{	if($attr == \PDO::ATTR_ERRMODE)return $this->error_rep;

		if($attr == \PDO::ATTR_CURSOR_NAME)
		{	return ($this->dbtype==self::FIREBIRD && $this->pdoS)?
			@$this->pdoS->getAttribute($attr) : NULL;
		}

		return (($r=@$this->pdo->getAttribute($attr)) || !$this->pdoS)?$r :
		@$this->pdoS->getAttribute($attr);
		
	}

	function setAttributes(){ return call_user_func_array(array($this, 'setAttribute'),func_get_args()); }

	private function setOptions(&$options)
	{	$r = array(
			\PDO::ATTR_DEFAULT_FETCH_MODE =>  \PDO::FETCH_ASSOC,
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_EMULATE_PREPARES => TRUE,
		);

		if($options && is_array($options))
		foreach($options as $key => $val)
		{	if($key == \PDO::ATTR_ERRMODE)
			{	if(!$this->lock_er_rep)
				{	if($val == \PDO::ERRMODE_SILENT)$this->error_rep=\PDO::ERRMODE_SILENT;
					elseif($val == \PDO::ERRMODE_WARNING)$this->error_rep=\PDO::ERRMODE_WARNING;
					elseif($val == \PDO::ERRMODE_EXCEPTION)$this->error_rep=\PDO::ERRMODE_EXCEPTION;
				}
			}else $r[$key] = $val;
		}
		return $r;
	}


	/*	We want to stringify a condition Array. The WDB::createCond()
	 *	function is used.
	 *
	 *	- $cond	:	A conditional string or a PHP array
	 *	- $dkey	:	A column name, or variable that must be used as the
	 *				default key in the processing of $cond if it is an Array.
	 *				should be given as it appears in the DB, without having 
	 *				been escaped. The function takes care of escaping it if
	 *				necessary.
	 *
	 *	- $qIndice:	Mostly needed for UPDATE and INSERT statements.
	 *		As these instructions can have several elements to process,
	 *		$qIndice ndicates the index of the element being processed.
	 *		His value is >= 0
	 *
	 *	Operators supported at the end of keys or the beginning of
	 *	values:		=  >  >=  <  <=  <>  []   [[   ]]   ][
	 *
	 *	NB:
	 *	The $col parameter might not be a string, might be empty, might
	 *	contain more than one column, or might be "*". All possibilities
	 *	must be taken into account in order to return the correct result to
	 *	each situation.
	 *
	 */
	private function stringify_cond($cond, $dkey=false, $qIndice=0)
	{
		/* If the condition is absent, ie if it is "", FALSE or NULL, 
		 * a always-true condition is returned
		 */
		if($cond==='' || $cond===FALSE || is_null($cond) ||
		   !$cond && !is_scalar($cond)) return '1=1';

		if(is_numeric($cond))return $cond*1?'1=1':'1=0';

		if(is_string($cond)) return $this->analyse_expr($cond, $qIndice);

		if(is_object($cond))$cond = (array)$cond;


		/* If there are multiple column names, the condition is processed
		 * without using a default variable.
		 */
		if(is_array($dkey) || $dkey=='' || $dkey=='*' || is_int($dkey) ||
		   is_string($dkey) && strpos($dkey, ',')!==FALSE
		)$dkey='';elseif($this){}

		return ($r=$this->createCond($dkey, $cond, $qIndice))!==FALSE?$r:
			self::Err('Wrong conditional parameter', $this);
	}


	/*	Prend un tableau d'identificateurs (noms de colonnes)
	 *	et les transforme en une chaine où les noms étant séparés les uns
	 *	les autres par une virgule.
	 *
	 *	Chaque entrée peut avoir une clé de type chaîne et une valeur chaine
	 *	ou une valeur string seulement.
	 *
	 *	1- Chaque entrée concerne uniquement un et un seul nom de table ou
	 *	de colonne.
	 *
	 *	2- Le point dans un nom est un séparateur de champs.
	 *
	 *	NB: Utilisé pour les Array de colonnes de la commande SELECT
	 *
	 */
	/*	Nouvelle version. Celle-ci prend en compte
	 *	la présence possible du hashtag (expression) devant un nom de colonne
	 *	ou d'alias.
	 */
	private function stringify_select_columns_array2(&$list, $qIndice=0)
	{	if(!is_array($list)||!$list)return false;
		$r=''; $qv=$this->qv; $dbt=$this->dbtype;
		$eb=$this->exp_begin; $l=strlen($eb);

		foreach($list as $key => $val)
		{	if(is_int($key) && $key<0 || !is_int($key) &&
			is_numeric($key) && !is_string($key))
			{	if(!is_string($val))return FALSE;
				$r.=($r?',':'').$key.' AS '.(strpos($val, $eb)===0?
				substr($val, $l) : self::escapeUnifieldId($val, $dbt));
			}
			elseif(is_int($key)) $r.=($r?',':''). 
			(is_numeric($val)? $val : (strpos($val, $eb)===0?
				substr($val, $l) : $this->escapeExpEntity($val, 1, $qIndice)));
			else { $r.=($r?',':'').(strpos($key, $eb)===0?
				substr($key, $l) : $this->escapeExpEntity($key, 1, $qIndice)).
			' AS '.(strpos($val, $eb)===0?substr($val, $l) : self::escapeUnifieldId($val, $dbt));}
		}
		return $r?$r:false;
	}


	/*	Cette fonction prend un array des noms de tables et les joint sous forme
	 *	de chaîne de caractères. Voici le format de la variable $list:
	 *
	 *	array
	 *	(	"table1",
	 *		"table2" => "alias",
	 *		. . .
	 *		"table_n"
	 *	)
	 *
	 *	Cette fonction prend en compte la présence possible du hashtag devant un nom
	 *	de table ou d'alias. La fonction renvoie une chaîne de caractères si tout se
	 *	passe bien et FALSE sinon.
	 */
	private function stringify_select_tables_array(&$list, $qIndice=0)
	{	if(!is_array($list)||!$list)return false; $qv=$this->qv; 
		$r=''; $dbt=$this->dbtype; $eb=$this->exp_begin; $l=strlen($eb);

		foreach($list as $key => $val)
		{	if(is_int($key) && $key<0 || !is_int($key) && !is_string($key)) return FALSE;

			if(is_string($key))$key=(strpos($key, $eb)===0?
			substr($key, $l) : self::escapeMultifieldId($key, $dbt));

			if(is_string($val))$val = (strpos($val, $eb)===0?
			substr($key, $l) : self::escapeMultifieldId($val, $dbt));

			$r.=($r?',':'').(is_int($key)?$val:"$key AS $val");
		}
		return $r?$r:false;
	}

	/*	Prend un tableau d'identificateurs (noms de colonnes ou de tables)
	 *	et les transforme en une chaine, les noms étant séparés les uns
	 *	les autres par une virgule.
	 *
	 *	chaque entrée peut avoir une clé de type chaîne et une valeur chaine
	 *	ou une valeur string seulement.
	 *
	 *	1- Chaque entrée concerne uniquement un et un seul nom de table ou
	 *	de colonne.
	 *
	 *	2- Le point dans un nom est un séparateur de champs.
	 *
	 *	NB: Utilisé pour les Array de la commande ORDER BY
	 *
	 *	NON UTILISÉE
	 */
	static private function stringify_ident_array(&$list, $dbtyp)
	{	if(!is_array($list)||!$list)return false; $r='';
		foreach($list as $key => $val)
		{	if(is_int($key)) $r.=($r?',':'').self::escapeMultifieldId($val, $dbtyp);
			else $r.=($r?',':'').self::escapeMultifieldId($key, $dbtyp).' '.
			self::escapeMultifieldId($val, $dbtyp);
		}
		return $r?$r:false;
	}


	/*	Transforme en chaine un tableau utilisé par les commandes
	 *	GROUP BY.
	 *
	 */
	static function stringify_ident_list($list, $dbtyp)
	{	if(!is_array($list)||!$list)return false; $r='';
		foreach($list as $val)
		{	$val .=''; $r.=($r?',':'').self::escapeMultifieldId($val, $dbtyp);}
		return $r?$r:false;
	}


	/*	Echappement d'un nom d'element du type utilisé par la commande ORDER BY.
	 *	L'élement en question sera un nom de colonne suivi ou pas de l'un des mots
	 *	ASC ou DESC. La fonction echappe le nom de colonne sans toucher au terme qui
	 *	le suit.
	 */
	static function escape_orderby_col($val, $dbtype)
	{	if( (!($p=stripos($v=trim($val), ' ASC')) || $p && (($p+4)<($l=strlen($v))) )
		&& (!($p=stripos($v=trim($val), ' DESC')) || $p && (($p+5)<($l=strlen($v))) )
		)return self::escapeMultifieldId($val, $dbtype); $v2=trim(substr($v, 0, $p));
		return self::escapeMultifieldId($v2, $dbtype). substr($v, $p);
	}

	/*	Transforme en chaine un tableau utilisé par la commande ORDER BY
	 *	Dans ce tableau, chaque entrée represente uniquement une colonne,
	 *	comme le montre l'exemple qui suit:
	 *
	 *	array
	 *	(	'column1',
	 *		'column2 DESC',
	 *		'column3' => 'ASC'
	 *		'column4' => 'DESC'
	 *	)
	 */
	static function stringify_orderby_array($list, $dbtype)
	{	if(!is_array($list)||!$list)return false; $r='';
		foreach($list as $key => $val)
		{	if(is_int($key) && $key >=0)$r.=($r?',':'').self::escape_orderby_col($val, $dbtype);
			elseif(($v=strtolower(trim($val)))!='asc' && $v!='desc')return false;
			else $r.=($r?',':'').self::escape_orderby_col($key, $dbtype).' '.$val;
		}
		return $r?$r:false;
	}
	

	/*	Column names can be introduced with operators at the beginning or 
	 *	at the end. This function analyzes a character string and extracts 
	 *	the operator at the beginning (or at the end) if its exists.
	 *
	 *	- $str	:	La chaîne à analyser
	 *	- $pos	:	Indicates the position where the operator must be searched,
	 *				'b' for the start of the string and 'e' for the end.
	 *	- $remOP:	If the operator is present, it is normally removed from the
	 *				string, unless the split parameter is FALSE
	 *
	 *	Operators supported:
	 *		=  >  >=  <  <=  <>  !=  []   [[   ]]   ][	~  LK  IN
	 *
	 *	RESULT
	 *	If an operator is found it is returned and the string $str comes out
	 *	with the part of the string that does not contain an operator. If an
	 *	error is occurs the function returns FALSE. If no operator is found and
	 *	there is no error, an empty string is returned.
	 */
	static function OP(&$str, $pos='e', $remOP=true, $dbtype=0, $error_reporting=\PDO::ERRMODE_EXCEPTION)
	{	if(!is_string($str)){ return (is_numeric($str))?'':false; }
		$l=strlen($str); $op=''; $ok=false;

		if($pos=='b')
		{	$i=0;
			if(!$str || $str[0]==' ')return '';

			//self::pvide($str, $i);
			if(!($op=self::OP2($str, 'LK', $i, 'b')) && !($op=self::OP2($str, 'IN', $i, 'b')))
			{	while($i<$l && (($c=$str[$i])=='='||$c=='>'||$c=='<'||$c=='|'||
				$c=='['||$c==']'||$c=='!'||$c=='~')){$i++;$op.=$c;}
			}
		}else
		{	$i=$l-1; if($str[($i=$l-1)]==' ')return '';

			$i=$l-1; if($str[($i=$l-1)]==' ')
			{ $str=rtrim($str); $l=strlen($str); $i=$l-1;}

			if( (($h=$i-2)>=0 && self::isB($str, $h) ||
			($h=$i-3)>=0 && self::isB($str, $h) ||
			($h=$i-4)>=0 && self::isB($str, $h)) &&
			(($h=strtoupper(substr($str, $h+1)))=='LK'||$h=='IN'||$h=='!IN'
			||$h=='BTW'||$h=='!BTW')){ $ok=true; $op=$h; $i=$i-strlen($h);}

			else
			{	while($i>=0 && (($c=$str[$i])=='='||$c=='>'||$c=='<'||$c=='|'||
				$c=='~'||$c=='['||$c==']'||$c=='!')){$i--;$op=$c.$op;}
			}
		}
		if(!$op){$str=trim($str);return '';} $op=strtoupper($op);

		if($op!='='&&$op!='>'&&$op!='>='&&$op!='<'&&$op!='<='&&$op!='<>'&&
		$op!='!='&&$op!='[]'&&$op!='[['&&$op!=']]'&&$op!=']['&&$op!='LK'
		&&$op!='![]'&&$op!='![['&&$op!='!]]'&&$op!='!]['&&$op!='||'&&
		$op!='=='&&$op!='!==' && 
		$op!='~'&&$op!='!~'&&$op!='IN'&&$op!='!IN'&&$op!='BTW'&&$op!='!BTW')
		{	#return ($op=='['||$op==']')? '':
			#self::ExeptionError('Operator not supported: '.htmlentities($op), $l, $error_reporting); return false;
			return '';
		}

		if($remOP){ $str=trim(($pos=='b')?substr($str, $i):substr($str,0,$i+1)); }
		if($op=='||'&&$dbtype==self::SQLSERVER)return '+';
		if($op == '!~')return ' NOT LIKE ';
		if($op == '!IN')return 'NOT IN';

		return ($op=='!=')?'<>':(($op=='LK'||$op=='~')?' LIKE ':$op);
	}


	/*
	 *	$i	:	debut des recherches
	 *	$pos:	vaut 'b' (pour begin) ou 'e' (pour ende)
	 */
	static function OP2($str, $word, &$i, $pos='e')
	{	if(($j=($b=($pos=='b'))?$i:($x=($i-strlen($word)+1)))<0
		|| $j<$i||$j>=strlen($str))return false;
		if(($r=self::we($str, $word, $j, false, $b, true)))
		{ $i=$b?$j:$x;} return $r;
	}


	/*
	 *	This function joins two elements into one to form a condition.
	 *	It is assumed that the first element has a comparison operator at
	 *	its end, or that the second element has a comparison operator at
	 *	the start. If neither of the two elements has a comparison operator
	 *	where indicated, the = operator is used to link the two elements.
	 *
	 *	RESULT
	 *	If all is well, the resulting string, which is a condition is returned.
	 *	Otherwise FALSE is returned.
	 *
	 *	Examples:
	 *		createCond(2, A)			==>	 2=A
	 *		createCond(A, '< B')		==>	 A<B
	 *		createCond('A<>', B)		==>	 A<>B
	 *		createCond('A[]', [B, C])	==>	 A BETWEEN B AND C
	 *		createCond(A, [B, C, D])	==>	 A=B OR A=C OR A=D
	 *		createCond('A<', [B, C, D])	==>	 A<B OR A<C OR A<D
	 *
	 *	IMPORTANT:
	 *	Numeric keys will not be considered in the linearization of the
	 *	Array of condition. La variable $key ne sera pas echappée par la
	 *	Au besoin, celle-ci doit être échappée avant d'être passée à la
	 *  fonction.
	 *
	 *	- $dkey	:
	 *		Clé par défaut, sera pas échappé avant appel de la fonction, 
	 *		car pouvant contenir un opérateur.
	 *
	 *	- $val	: La valeur de droite. Peut être un Array ou une chaine.
	 *
	 *	- $qv	:
	 *		Le caractère à utiliser pour echapper les identificateurs.
	 *		Pour MYSQL ce caractère est `. Pour tous les autres il vaut " .
	 *
	 *	- $params:	Un tableau contenant les parametres de requette.
	 *		Il contient ou peut contenir les élements suivants:
	 *
	 *		- extern: un tableau de tous les parametres-utilisateur nommés
	 *		- intern: un tableau de tous les parametres nommés créés par WDB
	 *		- nonamed: le nombre total des parametres-utilisateur anonymes
	 *		- total: le nombre total des parametres annonymes
	 *		- cumul: tableau de tous les parametres nommés, utilisateur et WDB.
	 *
	 *		Tous ces élements sont cumulatifs. C'est à dire que les nouveaux
	 *		élements s'ajouterons à ceux existants si le tableau n'est pas vides,
	 *		ou que le nombre ne vaut pas 0.
	 *		
	 *
	 *	- escapeKeys:
	 *		Les valeurs de gauche sont supposées être des clés. Si ce
	 *		n'est pas le cas elles seront entourées de (). Pour les clés
	 *		non entourée de ( ), le parametre escapeKeys indique s'il faut
	 *		echapper les clés ou non.
	 *
	 *	- $dOP		:	l'opérateur logique par défaut.
	 *
	 *
	 */
	/*
	 *	Si $dkey existe, il ne doit pas être quotté avant d'être passé à la fonction.
	 * 
	 */
	private function createCond($dkey, $val, $qIndice=0, $dOP='OR')
	{	$DP = ($dkey=='Price ==');

		// if no condtion is supplied, a always-true condition is returned
		if(is_null($val) || is_bool($val) || $val==='') return '1=1';

		$f=false; if($dkey===false || is_null($dkey))$dkey='';
		if(!is_scalar($dkey)){return $f;}
		$xB = $this->exp_begin; $qv=$this->qv; $dbt=$this->dbtype;
		$lop=$this->key_operators; $rop=$this->value_operators;

		$op=$op2=$btw1=$btw=$isI=$isN=''; if(($isS=is_string($dkey)) && $dkey && $lop)
		{	if(($op=self::OP($dkey,'e'))===false)return $f; $btw1=self::btw($op);
			if($isN=is_numeric($dkey)){$isI=is_int($dkey*1);}
		}else{$isN=true; $isI=is_int($dkey);}

		$isC2=is_scalar($val); $ktype=$vtype=0; $isN2=false;
		if(($isS2=is_string($val)) && $rop && !$op){ if(($op2=self::OP($val,'b'))===$f)return $f;
		$isA2=0; if(($isN2=is_numeric($val)))$isI2=is_int($val*1);}
		elseif($isA2=is_array($val)){}else $isN2=is_numeric($val);

		# We test some errors
		if($isS2 && ($op2 && ($btw=self::btw($op2)))
		|| !$isA2 && $isS && $op && $btw1

		|| $isA2 && (!($nb=count($val)) || $btw1 && ($nb!=2 || 
			!array_key_exists(0, $val) || !array_key_exists(1, $val) ||
			!is_scalar($val[0]) || !is_scalar($val[1])))
		|| $op && $op2 && $op!=$op2
		|| ($dkey===''||$val==='')&&($op||$op2)){return $f;}

		/*	We escape the value, in case it is a non-numeric
		 *	string, we also escape the value in case it is not an Array
		 */
		if($isS2 && !$isN2 && ($val=$this->escapeExpEntity($val, false, $qIndice))
		=== FALSE){return $f;}

		// Here we determine the operator to use for the binding.
		// j contains the operator to the right of "dkey" 
		$j=$op; if(!$op){$op=$op2?$op2:'='; if($op=='=')$btw='';}
		else{$btw=$btw1;} if($btw==='')$btw=self::btw($op);


		// On crée une version échappée de la clé si faisable
		$edkey = $dkey && is_string($dkey)?
		self::escapeMultifieldId($dkey, $dbt) : $dkey;

		// If the value is scalar ...
		if($isC2)
		{	// ... and the operator is for intervals, error !
			if($btw1 || $btw || $j && (!$dkey&&$dkey!==0 || !$val&&$dkey!==0)) return false;

			$val = $this->addInternalVal($val, $qIndice, 1);

			# ... and the key is not empty
			if($dkey && ($op=='==' && !($not=0)||$op=='!==' && ($not=1)))
				return self::iEgal($edkey, $val, $not);

			$x = ($op==' LIKE ' || $op==' NOT LIKE ');
			if($dkey || $isN)return ($x?'(':'').$edkey.$op.$val.($x?')':'');

			return $isN2? ($val===0? '0<>0':$val.'='.$val) : $val;
		}

		//	If the operator is for intervals
		if($btw)return $this->between($edkey, $op, $val[0], $val[1], $qIndice);


		/*	If the value is not a array
		 *	We return the result depending on whether $key is integer or not.
		 *
		 *	Warning: This part seems to be a repetition, because it has
		 *			 been dealt with by two previous parts.
		 */
		$op2=$cond=''; $exit0=$keyCond=0;


		//////		In the following $val is an array		////

		if(!$dOP || !is_string($dOP) || ($dOP=strtoupper($dOP))!='AND')$dOP='OR';

		/* If $dkey is OR or AND, it is used as operator to build the 
		 * condition of the Array. If $dkey is different from OR and AND
		 * we take as operator the element of index 0 of the Array, if this 
		 * is OR or AND. Otherwise the operator OR is used
		 *
		 */
		if(!$isS || ($X=strtoupper($dkey))!='AND' && $X!='OR')
		{	if(!array_key_exists(0, $val) || !is_string($val[0]) || !$val[0]
			|| ($X=strtoupper($val[0]))!='AND' && $X!='OR'){$X=$dOP;}
			else $exit0=1;
		}else $keyCond=1;// when $X=OR OR $X=AND

		foreach($val as $i => $v)
		{	if($i===0 && $exit0)continue; $op3=$op4=$u='';$ktype=0; $x='';

			if(is_array($v))
			{	if(is_string($i) && (($u=strtoupper($i))=='OR' || $u=='AND')
				|| is_int($i) && $i>=0){ if(!$isI)$i=$dkey; $esc=!$isI;}else $esc=false;

				if(is_string($i) && ($z=$i) && $lop && (($x=self::OP($z, 'e'))=='IN' || $x=='NOT IN'))
				{	if(($c=$this->ArrayToList($v, $qIndice))===FALSE)return false;
					$c = '('.self::escapeMultifieldId($z, $dbt)." $x ".$c.')';
				}
				elseif(!$v || ($c=$this->createCond($i, $v, $qIndice, trim($u) ))===false){return false;}
			}
			else
			{	if(is_bool($v))$v = $v?1:0;

				if(($ISS=is_string($v)) && $rop && (($op3=self::OP($v, 'b'))===FALSE ||
				$op3 && self::btw($op3)) || is_string($i) && $lop &&
				(($op4=self::OP($i))===false || $op3 && $op4 && $op3!=$op4) )return false;

				$k=$op3; if(!$op3)$op3=$op4?$op4:'=';

				/* Ceci est nouveau
				 * Une valeur string sans clé valable crée une erreur si elle
				 * n'est pas une expression
				 */
				if(is_int($i) && $i>=0 && !$dkey && $dkey!=='0' && (!$ISS ||
				!$v || strpos($v, $xB)!==0)){return false; }

				if(!is_null($v) && ($v = $this->addInternalVal($v, $qIndice, 1))===FALSE){return false;}

				# Si une clé est entiere et positive
				if(is_int($i) && $i>=0)
				{
					# ???  C'est OR ou bien AND
					# $isN == $dkey is nummeric (veut dire que $dkey ne compte pas)
					# $keyCond == 1 when $dkey='OR' OR $dkey='AND'
					if( is_int($dkey) && $dkey>=0 || $keyCond)
					{	if($k){return false;} $c=$v; # condition éxacte
					}
					else 
					{
						# $j contains the operator to the right of "dkey", if it exists
						# $k contains the operator to the left of the value if it exists

						/* Si $dkey n'existe pas et que la valeur possede un opérateur,
						 * ou si les opérateurs de $dkey et de la valeur sont différents
						 * on sort sur une erreur.
						 */
						if(!$dkey && $k || $j && $k && $j!=$k){return false;}

						if(($op_=$j?$j:$op3)=='=='&&!($not=0)||$op_=='!=='&&$not=1)
							$c = self::iEgal($dkey, $v, $not);
						else $c = $dkey? $edkey.($j?$j:$op3).$v : (is_null($v)?'NULL':$v);
					}
				}
				else
				{
					if(is_string($i) && !is_numeric($i) && 
					($i=$this->escapeExpEntity($i, true, $qIndice))===FALSE)return false;

					if(is_null($v))
					{	if(!($op3=='<>'||$op3=='='||$op3=='=='&&!($not=0)||$op3=='!=='&&($not=1)))return false;
						$c = !($op3=='<>'||$op3=='=')? self::iEgal($i, $v, $not):
						self::cpNULL($i, $this->dbtype, $op3=='<>'?true:false);
					}
					else
					{	if($i && ($op3=='==' && !($not=0)||$op3=='!==' && ($not=1)))
						{ $c=self::iEgal($i, $v, $not); }else{
						$x = ($op3==' LIKE ' || $op3==' NOT LIKE ');
						$c=($x?'(':'').$i.$op3.$v.($x?')':''); }
					}

				}
			}
			if(!$cond)$cond=$c;else $cond .= ' '.$X.' '.$c;
		}

		return '('.$cond.')';
	}


	/*
	 *	https://stackoverflow.com/questions/50917152/sql-server-compare-to-null
	 *
	 *	https://modern-sql.com/feature/is-distinct-from
	 *	
	 *	IS NOT DISTINCT FROM
	 *
	 *	Comparaison avec NULL
	 */
	static function cpNULL($key, $dbtype, $not=false)
	{	return '('.$key.' IS '.($not? 'NOT ':'').'NULL)';}

	static function iEgal($key, $val, $not=false)
	{	return "($key IS ".($not?'':'NOT ').'DISTINCT FROM '.
		(is_null($val)?'NULL':$val).')';}

	static function btw(&$op){ return is_string($op) && ($l=strlen($op))==2 &&
		$l==2 && ($op=='[]'||$op=='[['||$op==']]'||$op=='][') || $l==3 &&
		($op=='![]'||$op=='![['||$op=='!]]'||$op=='!]['||$op=='BTW') ||
		($l==4 && $op=='!BTW');
	}


	static private function Err($msg, &$This=false, $error_reporting=\PDO::ERRMODE_EXCEPTION)
	{	$x=0; if($This)$This->setErr($msg);else self::ExeptionError($msg, $x, $error_reporting); return false;}


	/*	This is a utility function.
	 *	It crosses all white characters and stops at the next non-white 
	 *	character. The variable $i thus stands out with the position of the 
	 *	next non-white character.
	 */
	static function pvide($str, &$i, &$l=false)
	{	if(!$l)$l=strlen($str); $r='';
		while(($i<$l) and (($c=$str[$i])==' '||$c=="\n"||$c=="\r"||
		$c=="\t")){$r.=$c; $i++;} return $r;
	}


	/*
	 *	Cette fonction extrait de la chaîne $S un commentaire ou un
	 *	identificateur quoté commencant à l'index $i. On suppose que
	 *	l'identificateur ou le commentaire est correctement quoté.
	 *	De facon que la fonction ne quote pas une nouvelle fois,
	 *	mais remplace les quotes existantes par les quotes adéquates
	 *	si necessaire.
	 *
	 *	- $S		:	la chaine à parcourir
	 *	- $i		:	la position de debut de la chaine ou ID
	 *	- $qv		:	le caractère à utiliser pour quoter
	 *	- $add_quotes:	la chaine rencontrée est normalement renvo-
	 *				 	yée entourée des quotes ($qv), sauf si ce
	 *					parametre est passé à FALSE. Dans ce dernier
	 *					cas les quotes entourantes sont manquantes.
	 *
	 *	Cette fonction tient compte des spécificités de Firebird et Oracle. Ceci
	 *	veut dire que s'il s'agit d'un identificateur, celui-ci n'est quoté que
	 *	si necessaire. C'est à dire s'il est non conventionnel.
	 *
	 *	C'est le premier caractère rencontré qui détermine le type d'élément dont
	 *	il s'agit. Une chaîne ('), ou un identificateur (", `).
	 *
	 *	RETOUR
	 *	La fonction renvoie le commentaire ou l'ID rencontré, ou
	 *	FALSE si le commentaire (ou l'ID) est mal quoté. Le param 
	 *	$i ressort avec la position du caractère qui suit l'element.
	 */
	static function readComment($S, &$i, $dbt, $add_quotes=true)
	{	$f=$ok=false; if(($i>=($l=strlen($S)))||($l-$i)<2
		|| ($c=$S[$i])!='\'' && $c!='"' && $c!='`')return $f;

		$OF=($dbt==self::FIREBIRD || $dbt==self::ORACLE);

		$i++; $t=''; $n=1; $q=($c=="'")?$c:($dbt==self::MYSQL?'`':'"');

		while($i<$l){	if($S[$i]==$c){ $n++; $i++;
				if(($i>=$l || $S[$i]!=$c) && ($n%2)==0){$ok=true;break;}
				$n++; $t.=$q.$q;
			}else{ $t .=($S[$i]==$q)?$q.$q:$S[$i];} 
			$i++;
		}

		if(!$OF || !$ok || $q=="'" || !$add_quotes || 
		($OF && !self::identIsNormal($t, $dbt)))
		{ return $ok?($add_quotes?$q.$t.$q:$t):$f; }

		return $t;
	}


	/*	Extrait de la chaîne $str et à partir de l'index $i une sous-
	 *	chaîne entourée de {} ou []. $i doit déjà pointer sur un des
	 *	caractères { ou [ sinon la fonction échoue. On suppose que, si
	 * 	la sous-chaîne est entourée de [] il s'agit d'un identificateur,
	 *	ou d'une chaîne SQL dans le cas des {}.
	 *
	 *	La sous-chaîne est extraite et transformée de manière à ce qu'elle
	 *	soit cotée avant renvoie. Ce qui veut dire que si elle contient
	 *	des caractères tels ` ' " ceux-ci sont doublés ou non, selon la
	 *	valeur du caractère du cotage, qui peut être $qv ou '.
	 *
	 *	La sous-chaîne ainsi extraite sera cotée, sauf si le parametre
	 *	$addQuote vaut FALSE.
	 *
	 */
	static function readBEntity(&$str, &$i, $dbt, $addQuote=true)
	{	if(($d=$str[$i])!='[' && $d!='{')return false;
		$i++; $r=''; $l=strlen($str); $d=($d=='[');
		$qte=$d?($dbt==self::MYSQL?'`':'"'):"'";

		while(($i<$l) && (($c=$str[$i])||!$c) &&
		($d && $c!=']'||!$d &&$c!='}')){$r.=($c==$qte)?$c.$c:$c; $i++;}

		if(($i>=$l)||$d&&$str[$i]!=']'||!$d&&$str[$i]!='}')return false;
		$i++; $OF=($dbt==self::FIREBIRD || $dbt==self::ORACLE);

		return $addQuote && (!$OF || $qte=="'" ||
		!self::identIsNormal($r, $dbt))?$qte.$r.$qte:$r;
	}

	/*
	 *  - $str		:	La chaîne à échapper. Elle doit être une expression SQL
	 *  - $qIndice	:	L'indice de la requête dans $this->params["queries") à
	 *					laquelle se rapporte l'action courante. Commence à 0
	 */
	function analyse_expr($str, $qIndice=0)
	{
		/*	Si l'expression est un simple identificateur avec des
		 *	caractères correctes on échappe et on sort
		 */
		$qv= $this->qv; $dbtyp=$this->dbtype;//if(preg_match("/^[0-9A-Za-z_]+$/", $str))return $qv.$str.$qv;

		//if(is_string($str) && is_numeric($str)) return 0+$str;

		$l=strlen($str); $r=$pa=''; $i=0;

		while($i<$l && ($c=$str[$i])!=='')
		{
			if($c=='"' || $c=='\'' || $c=='`')$x=self::readComment($str, $i, $dbtyp); 

			elseif($c=='['||$c=='{')$x=self::readBEntity($str, $i, $dbtype);

			else
			{	if($c==':'){ $pa=':';}
				elseif($c=='?'){$r.=$this->addparamA($qIndice);$i++;continue;}
				elseif($pa && !($c=='_' || $c>='0' && $c<='9'
				|| (($c>='A'&&$c<='Z') || ($c>='a'&&$c<='z'))))
				{	if($pa==':')return false;
					$this->addparamN($qIndice, $pa); $pa='';
				}

				if($pa && $c!=':')$pa.=$c;

				$r.=$str[$i]; $i++;continue;
			}

			if($x===FALSE)return $x; $r.=$x;
		}

		if($pa){ $this->addparamN($qIndice, $pa);} return $r;
	}


	/**
	 *	Cette fonction echappe une expression du type qui suit les mots-
	 *	clés GROUP BY ou FROM. La fonction n'echappe pas automatiquement
	 *	les noms non conformes. Il faut les mettre entre [ ], " " ou ` `
	 *	en prenant soin à les échapper convenablement dans les deux der-
	 *	niers cas. Le point (.) est utilisé comme séparateur.
	 *
	 *	Les identificateurs non conformes et non quotés causeront une erreur.
	 *
	 *	Pour GROUP BY, FROM, ORDER BY.
	 *
	 *	NB: Ne permet pas des parametres PDO
	 */
	static function escape_grpby_expr($str, $dbt)
	{	$l=strlen($str); $r=''; $i=0;

		while($i<$l && ($c=$str[$i])!=='')
		{
			if($c=='"' || $c=='\'' || $c=='`')$x=self::readComment($str, $i, $dbt); 

			elseif($c=='['||$c=='{')$x=self::readBEntity($str, $i, $dbt);

			else{ $r.=$str[$i]; $i++;continue;}

			if($x===FALSE)return $x; $r.=$x;
		}

		return $r;
	}

	/*	Analyse et echappement de la chaine $str. La chaine peut representer une chaine
	 *	SQL, un identificateur SQL ou une expression SQL (commence alor avec #)
	 *
	 *	$id : indique ce que la chaine represente si elle n'est pas une expression
	 *			true :	un identificateur sql
	 *			false :	une identificateur sql
	 */
	private function escapeExpEntity(&$str, $id=false, $qIndice=0)
	{	if(!is_string($str)||!$str)return false;
		if((($l=strlen($str))>1) && ($xB=$this->exp_begin) && (strpos($str, $xB)===0))
		{ return $this->analyse_expr(substr($str, strlen($xB)), $qIndice); }

		if($id)return self::escapeMultifieldId($str, $this->dbtype);
		return $this->addparamN($qIndice, '', $str);
	}


	/*
	 *	Echappement d'un identificateur unique non composé ou d'un
	 *	string SQL. C'est la quote passée à la fonction qui indique
	 *	le type d'elements (chaine ou identificateur) auquel on a à
	 *	faire.
	 *
	 *	- $str : L'élement SQL à échapper, sera considéré comme pro-
	 *	venant directement de la DB, sans échappement préalable (iden-
	 *	tificateur ou string SQL)
	 *
	 *	- $quote : Le caractère à utiliser pour l'échappement. Sera
	 *	 ", ' ou ` selon le type de DB et selon que $str est considéré
	 *	 comme un identificateur ou un string SQL.
	 *
	 *	NOTE
	 *	Cette fonction suppose que la chaine ou l'identificateur n'est
	 *	pas échappé
	 */
	static function escapeEntity(&$str, $dbt, $qv=false)
	{	$OF = ($dbt==self::FIREBIRD||$dbt==self::ORACLE);
		if(!$qv)$qv = ($dbt==self::MYSQL?'`':'"');
		return $str===false?$str : ($OF && $qv=='"' &&
		self::identIsNormal($str, $dbt)? $str:
		$qv.str_replace($qv, $qv.$qv, $str).$qv);
	}


	/*
	 *	Echappement intelligent d'un identificateur unique et uni-champ.
	 *	Ledit identificateur peut être entre [], ou pourrait être déjà
	 *	échappé, la fonction n'echappera pas 2 fois.
	 *
	 *	- $S :	La chaîne à echapper.
	 *
	 *	- $qv : le caractère à utiliser pour l'échappement.
	 *	 		Sera " ou ` selon le type de DB
	 *
	 *	L'identificateur ne devrait pas contenir de point en dehors de 
	 *	[] ou de ' '. S'ils y sont présents malgré tout, il sont quottés
	 *	comme les autres caractères.
	 *
	 *	L'identificateur ne sera quoté que si necessaire.
	 *
	 */
	static function escapeUnifieldId(&$S, $dbtype)
	{	$f=false; $i=0; $r=''; $q = ($dbtype==self::MYSQL?'`':'"');
		$act=($q=="'"); $l=strlen($S);

		if(!$S||($S[0]===' '||$S[$l-1]===' ')&&!($S=trim($S)))return $f;
		if(is_numeric($S))return $q.$S.$q; $l=strlen($S);

		if($S[0]=='[' && $S[$l-1]==']'){$s=substr($S,1,$l-2); return self::escapeEntity($s, $dbtype); }
		if($S[0]=='"' || $S[0]=='`')
		{	$i=0; if($r=self::readComment($S, $i, $dbtype))return $r;
		}
		return self::escapeEntity($S, $dbtype);
	}


	/*	Echappement d'un ID unique, pouvant avoir des points comme 
	 *	separateurs de champs. Il peut aussi y avoir des [] comme
	 *	protecteur de champs. Les champs correctements échappés ne le
	 *	sont plus, mais si les quotes utilisées ne sont pas adaptées,
	 *	elles sont correctement changées.
	 *
	 *	Cette fonction peut échapper les identificateur multi ou uni-
	 *	field
	 *
	 *	- Cette version n'échoue pas.
	 *
	 *	- Exemple d'echappement:
	 *		col		=>	"col"
	 *		[col]	=>	"col"
	 *		"col"	=>	"col"
	 *		tab . col 	=>	"tab"."col"
	 *		süß . col 	=>	"süß"."col"
	 *		my tab.col  =>	"my tab.col"
	 *		my-tab.col  =>	"my-tab"."col"
	 *		[my tab].col =>	"my tab"."col"
	 */
	static function escapeMultifieldId(&$S, $dbtype)
	{	if(!$S)return false; $i=$pt=$nq=0; $r=$x='';
		$qv = ($dbtype==self::MYSQL?'`':'"');
		if(is_numeric($S))return $qv.$S.$qv; $l=strlen($S);

		/*  $r   le résultat final
		 *  $x   contenu du champ courant
		 *  $v   les vides trouvés après le champ courant
		 *  $nq  indique que $x ne devra plus être quoté, car déjà quoté
		 */
		while($i<$l && ($c=$S[$i])!==FALSE)
		{
			if($c=='.')
			{	if(!$x)return self::escapeUnifieldId($S, $dbtype);
				$r.=$nq?$x:self::escapeEntity($x, $dbtype);
				$r.='.'; $i++; $x=''; $pt=1; $nq=0;
				if($i<$l && $S[$i]==' ')self::pvide($S, $i); continue;
			}

			if(!$x && ($c=='[') && (!$r||$pt) && ($j=strpos($S, ']', $i+1)))
			{	$y=substr($S, $i+1, $j-$i-1); $x=$y; //$x=self::escapeEntity($y, $dbtype);

				$j++;$v=($j<$l && $S[$j]==' ')?self::pvide($S, $j):'';

				if($j<$l && $S[$j]!='.')
				{	$x = '['.$y.']'.$v.$S[$j]; $i=$j+1; continue; }

				$i=$j; continue;
			}

			# Est uniquement vrai après un .
			if(!$x && ($c=='"'||$c=='`') && (!$r||$pt))
			{	$j=$i; $pt=$nq=0;
				if(($x=self::readComment($S, $i, $dbtype))===FALSE)
				{	$x.=$c; $i=$j+1; $nq=1;continue;}

				if($i<$l && $S[$i]==' ')$v=self::pvide($S, $i);

				if($i<$l && $S[$i]!='.')
				{	$x = substr($S, $j, $i-$j+1); // sans le point
					$i++; continue;
				}
				$nq=1; continue;
			}

			if($c===' ')
			{	if(!$x){ $i++; continue; }
				$v=self::pvide($S, $i);
				if($i>=$l || $S[$i]=='.')continue;

				return self::escapeUnifieldId($S, $dbtype); # NEW
				$x.=$v; continue;
			}
			$i++; $x.=$c;
		}

		if($x)$r.= $nq?$x:self::escapeEntity($x, $dbtype);
		return $r;
	}
	
	
	/*	Ancienne version de la fonction précedente. Les differences ne
	 *	sont pas grandes.
	 */
	static function escapeMultifieldId__(&$S, $dbtype)
	{	if(!$S)return false; $i=$pt=$nq=0; $r=$x='';
		$qv = ($dbtype==self::MYSQL?'`':'"');
		if(is_numeric($S))return $qv.$S.$qv; $l=strlen($S);

		while($i<$l && ($c=$S[$i])!==FALSE)
		{
			if($c=='.')
			{	if(!$x)return false;
				$r.=$nq?$x:self::escapeEntity($x, $dbtype);
				$r.='.'; $i++; $x=''; $pt=1; $nq=0;
				if($i<$l && $S[$i]==' ')self::pvide($S, $i); continue;
			}

			if(!$x && ($c=='[') && (!$r||$pt) && ($j=strpos($S, ']', $i+1)))
			{	$y=substr($S, $i+1, $j-$i-1); $x=$y; //$x=self::escapeEntity($y, $dbtype);

				$j++;$v=($j<$l && $S[$j]==' ')?self::pvide($S, $j):'';

				if($j<$l && $S[$j]!='.')
				{	$x = '['.$y.']'.$v.$S[$j]; $i=$j+1; continue; }

				$i=$j; continue;
			}

			if(!$x && ($c=='"'||$c=='`') && (!$r||$pt))
			{	$j=$i; $pt=$nq=0;
				if(($x=self::readComment($S, $i, $dbtype))===FALSE)
				{	$x.=$c; $i=$j+1; $nq=1;continue;}

				if($i<$l && $S[$i]==' ')$v=self::pvide($S, $i);

				if($i<$l && $S[$i]!='.')
				{	$x = substr($S, $j, $i-$j+1); // sans le point
					$i++; continue;
				}
				$nq=1; continue;
			}

			if($c===' ')
			{	if(!$x){ $i++; continue; }
				$v=self::pvide($S, $i);
				if($i>=$l || $S[$i]=='.')continue;
				$x.=$v; continue;
			}
			$i++; $x.=$c;
		}

		if($x)$r.= $nq?$x:self::escapeEntity($x, $dbtype);
		return $r;
	}

	static function qv($dbt, &$qs=false){ $qs="'"; return $dbt==self::MYSQL?'`':'"';}

	/**
	 * This function joins together the values of a list, to get something of the form (val_1, val2, .., val_n)
	 * @param array $ar An array of scalar values. The keys of said array do not matter, because it is assumed that they are positive integers
	 * @param int $qIndice >=0 The index of the data set being processed.
	 * @return boolean testExpr Indicates when is TRUE that it is necessary to test if each of the values of the supplied array is an expression and to treat it as such. If it is FALSE, the values are not tested, it is assumed that they cannot be expressions
	 */
	private function ArrayToList(&$ar, $qIndice, $testExp=1)
	{	if(!is_array($ar)||!$ar)return FALSE; $r='';
		foreach($ar as $v)
		{	if(!is_scalar($v)) return false;
		$r.=($r?',':''). $this->addInternalVal($v, $qIndice, $testExp);
		}
		return '('.$r.')';
	}

	/*	This function detects if an operator is at position $i of a string.
	 +
	 *	- $str		:	the string involved.
	 *	- $i		:	the index where the operator is supposed to be
	 *	- $goOver	:	If it is TRUE and an operator is indeed present,
	 *					the variable $ i emerges with the value of the
	 *					index following the operator.
	 *
	 *	RESULT
	 *	The function returns the operator encountered or FALSE if there is
	 *	no operator at the specified position
	 */
	static function isOp(&$str, &$i=0, $goOver=false)
	{	if(!is_string($str) || !($l=strlen($str)))return false; 
		$j=($isI=is_int($i))?$i:0; $c=$str[$j]; $c2=''; $x=0;

		if($c!='*' && $c!='/' && $c!='+' && $c!='-' &&
		$c!='=' && $c!='%' && $c!='>' && $c!='<')return false; $x++;

		if($l>=2 && $isI)
		{   $c2=$str[$j+1];
			if($c=='<' && ($c2=='>' || $c2=='=') || $c=='>' && $c2=='=')$x++;
			else $c2='';
		}
		if($isI && $goOver)$i+=$x; return $c.$c2;
	}

	/*
	 *
	 * - $type	:
	 *		1)	+   -   *   /   %	MOD 		(algebrics)
	 *		2)	<<  >>  &  |   ^ #(xor)  -		(bynary)
	 *		3)	=   >   >=  <  <=  !=  <>		(comparison)
	 *		4)	AND(&&) BETWEEN DIV DISTINCT ILIKE IN IS ISNULL LIKE MATCH
	 *			MOD NOT NOTNULL OR(||) XOR			(logicals)
	 *		6) ||								(Depend on DBS)
	 */
	static function isOp2(&$str, &$i=0, &$type=0, $dbtype=0, &$isWord=false)
	{	if(!is_string($str) || !($l=strlen($str)))return false; $isWord=false;
		$j=($isI=is_int($i))?$i:0; $i=$j; $c=$str[$j]; $c2=''; $x=0;

		if($c=='='){$i++; $type=3; return '=';}
		if($c=='*' || $c=='/' || $c=='+' || $c=='-' || $c=='%'){$i++; $type=1; return $c;}

		$j++; $c2=($j<$l)?$str[$j]:''; $type=3;

		if($c=='>'){  if($c2=='='||$c2=='>'){$c.=$c2;$i+=2; if($c2=='>')$type=2;}else $i++;return $c; }
		if($c=='<'){ if($c2=='='||$c2=='>'||$c2=='<'){$c.=$c2;$i+=2;if($c2=='<')$type=2;}else $i++;return $c; }
		if($c=='!'){ if($c2=='='){$c='<>';$i+=2;}else{$i++;$type=4;} return $c; }
		if($c=='|')
		{	if($c2=='|')
			{	$i+=2; $c.=$c2;
				if($dbtype==self::MYSQL) $type=4; // = OR
				elseif($dbtype==self::SQLSERVER) $type=1; //
				else $type=1; // STRING CONCAT
			}
			else{$i++; $type=2;} return $c;
		}

		if($c=='&') // Binary AND
		{	if($c2=='&'){ $i+=2; $c='AND'; $type=4; }
			else{$i++; $type=2;} return $c;
		}

		# binary XOR
		if($c=='^' || $c=='#'){ $type=2; $i+=1; return $c;} $j=$i;

		if(($r=self::we($str, 'MOD', $i))){ $type=1; $isWord=true; return $r;}

		if(($r=self::we($str, 'AND', $i)) || ($r=self::we($str, 'OR', $i))
		|| ($r=self::we($str, 'XOR', $i)) 
		|| ($r=self::we($str, 'BETWEEN', $i)) || ($r=self::we($str, 'DIV', $i))
		|| ($r=self::we($str, 'DISTINCT', $i)) || ($r=self::we($str, 'ILIKE', $i))
		|| ($r=self::we($str, 'IN', $i)) || ($r=self::we($str, 'IS', $i))
		|| ($r=self::we($str, 'ISNULL', $i)) || ($r=self::we($str, 'LIKE', $i))
		|| ($r=self::we($str, 'MATCH', $i)) || ($r=self::we($str, 'NOT', $i))
		|| ($r=self::we($str, 'NOTNULL', $i)) || ($r=self::we($str, 'NOT', $i))
		){ $type=4; $isWord=true; return $r;} return false;
	}


	/*	Cette fonction cherche un opérateur unaire à la fin de la
	 *	chaine $str et renvoie celui-ci s'il est trouvé ou FALSE sinon.
	 *	Si l'opérateur est trouvé, la chaine $str est aussi mise à jour
	 *	et renvoyée sans ledit opérateur. Les opérateur recherchés sont:
	 *	+=  -=  /=  *=
	 *
	 *	C'est cette fonction qui est utilisée dans la fonction update()
	 */
	static function isUOp(&$str)
	{	if(!is_string($str) || !($l=strlen($str)) || $l<3)return false;
		$i=$l-1;if($str[$i]=='=' && (($c=$str[$i-1])=='+'||$c=='-'||$c=='*'||$c=='/'||$c=='.'))
		{ $str=trim(substr($str, 0, $l-2)); return $c.'=';}
		return '';
	}


	/*
	 *	This function searches for a given word at a specific position in
	 *	the string $str.
	 *
	 *	- $str	:	The search string
	 *	- $word	:	The searched word
	 *	- $i	:	the search start index
	 *	- $cs	:	Indicates whether the search is case sensitive (TRUE) or not (FALSE)
	 *	- $whiteEnd: If it is TRUE and the word is found, the search is considered conclusive only if the word is followed by at least one white character
	 *	- $passBlank: If it is TRUE, any white characters present at the index $i are ignored, just like those following the word found.
	 *
	 *	RESULT
	 *	If the specified word is found at the given index and all is well,
	 *	the function returns TRUE and the index $i is positioned after the
	 *	word. If there is failure the function returns FALSE.
	 */
	static function we(&$str, $word, &$i=0, $cs=false, $whiteEnd=true, $passBlank=true)
	{	$j=$i; if(!($l=strlen($str)) || !($m=strlen($word)) || ($m>$l)) return false;

		if($passBlank && self::isB($str, $j))self::pvide($str, $j);
		if(($j>=$l) || (($l-$j) < $m))return false; $t=0; $next=''; $r='';

		if($cs)while($t<$m && ($word[$t]==$str[$j])){$r.=$str[$j]; $t++; $j++;} 
		else 
		{	$w=strtoupper($word); $w2=strtolower($word);
			while(($t<$m) && (($w[$t]==$str[$j]) || ($w2[$t]==$str[$j]))){$r.=$str[$j]; $t++; $j++;}
		}

		if($t<$m)return false; $b=$op=false;
		if($whiteEnd && ($j<$l && (!($b=self::isB($str, $j)) && !($op=self::isOp($str, $j))))) return false;
		$i=$j; return $r;
	}

	static function wordExists(&$str, &$word, &$i=0, $cs=false, $whiteEnd=true, $passBlank=true)
	{ return self::we($str, $word, $i, $cs, $whiteEnd, $passBlank);}


	static function isA(&$str, $i=0)
	{	if(!($l=strlen($str)) || ($l<=$i))return false;
		return (($c=$str[$i])>='a' && $c<='z' || $c>='A' && $c<='Z' || $c=='_' || $c=='$');
	}

	static function isB(&$str, $i=0)
	{	if(!($l=strlen($str)) || ($l<=$i))return false;
		return (($c=$str[$i])==' '||$c=="\n"||$c=="\r"||$c=="\t")?true:false;
	}

	static function rpath($path){ return (@($r=realpath($path)))?$r:$path;}


	/*	On suppose que $key a été échappé convenablement.
	 *
	 */
	private function between($key, $op, &$v1, &$v2, $qIndice, $testExp=1)
	{
		if(!($s=is_string($v1)) && !is_numeric($v1)||
		!($s2=is_string($v2)) && !is_numeric($v2)) return false;

		if(($w1 = $this->addInternalVal($v1, $qIndice, $testExp))===FALSE||
		($w2 = $this->addInternalVal($v2, $qIndice, $testExp))===FALSE)return FALSE;

		if($op=='BTW') return '('.$key.' BETWEEN '.$w1.' AND '.$w2.')';
		if($op=='!BTW') return '('.$key.' NOT BETWEEN '.$w1.' AND '.$w2.')';

		if($op=='[]') return '('.$key.'>='.$w1.' AND '.$key.'<='.$w2.')';
		if($op=='[[') return '('.$key.'>='.$w1.' AND '.$key.'<'.$w2.')';
		if($op=='][') return '('.$key.'>'.$w1.' AND '.$key.'<'.$w2.')';
		if($op==']]')return '('.$key.'>'.$w1.' AND '.$key.'<='.$w2.')';

		if($op=='![]') return '('.$key.' NOT BETWEEN '.$w1.' AND '.$w2.')';
		if($op=='![[') return '('.$key.'<'.$w1.' OR '.$key.'>='.$w2.')';
		if($op=='!][') return '('.$key.'<='.$w1.' OR '.$key.'>='.$w2.')';
		if($op=='!]]') return '('.$key.'<='.$w1.' OR '.$key.'>'.$w2.')';
	}


	/*	Cette fonction essai de construire une chaîne dsn à partir d'un array de parametres.
	 *
	 *	- $tab		:	$array de parametres, contenant les elements de connexion
	 *	- $prefix	:	le(s) prefixes dsn. Ceux-ci seront séparés par | s'ils sont plusieurs
	 *	- $key_f	:	une clé finale pouvant faire partie du dsn
	 *	- $key_i	:	la clé ou les clés dans $tab devant contenir la valeur de $key_f
	 *					S'il y a +sieurs clés, elles seront séparés par un caractère |
	 *					De plus, si la clé $key_f doit impérativement être présente dans
	 *					le dsn, elle sera precedée d'un ! - De cette facon une erreur est
	 *					lévé si les parametres ne contiennent pas sa valeur
	 *
	 *	Plusieurs  prefixes pourront être donnés s'il y a plus d'une possibilité et si on
	 *	ne sait pas précisement le prefixe qui fonctionne. C'est le cas par exemple si on
	 *	utilise SQL Server ou Sybase. 
	 *
	 *
	 *	NB: Les paires  $key_f, $key_i  devront être données autant que necessaires.
	 *	Les clés $key_f doivent être données avec les séparateurs adéquats.
	 *	Si une clé $key_f vaut la même chose que $key_i, cette dernière pourra être
	 *	passée à false ou ''
	 */
	static function dsn_str(&$tab, $prefix, $key_f, $key_i)
	{
		if(($n=func_num_args())<4)return self::ExeptionError('Very few parameters for the WDB::dsn_str() function');
		$dsn=''; for($i=2; $i<=$n-2; $i=$i+2)
		{	$kf=func_get_arg($i); $val=false;
			if($kf && $kf[0]=='!'){$imp=1; $kf=substr($kf, 1);}else $imp=0;

			if(($ki=func_get_arg($i+1))===''||$ki==false)$ki=trim($kf, '=;,:');

			if(($ki=strtolower($ki)) && strpos($ki, '|')!==FALSE)
			{	$kx=explode('|', $ki); foreach($kx as $ki)
				{	if(!array_key_exists($ki, $tab) && !
					array_key_exists($ki=strtoupper($ki), $tab)) continue;
					$val = $tab[$ki]; break;
				}
			}elseif(array_key_exists($ki, $tab)||
			array_key_exists($ki=strtoupper($ki), $tab))$val=$tab[$ki];

			if($val===FALSE){ if($imp)return false;continue;}
			$dsn .=($kf?$kf:'').$val;
		}
		if(!$prefix || strpos($prefix, '|')===FALSE) return $prefix.$dsn;;
		$prefix=explode('|', $prefix); foreach($prefix as $p)$r[]=$p.$dsn; return $r;
	}

	# https://qastack.fr/programming/5879043/php-script-detect-whether-running-under-linux-or-windows
	# https://www.php.net/manual/fr/function.php-uname.php
	static function iswin(){ return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');}

	static public function PT()
	{	if(!$nbr=func_num_args()) return;
		for($i=0; $i<$nbr; $i++)
		{
			$param =  func_get_arg($i);
		?>
			<pre style="font-size:18px;"><?php if(is_scalar($param))
			{echo is_bool($param)?($param===TRUE?'BOOL(TRUE)':'BOOL(FALSE)'):$param;}elseif(is_null($param))echo 'NULL';else print_r($param);?></pre>
		<?php
		}
	}

	/**
	 *	Création d'un objet WDBRow pour la manipulation des données par Active Record
	 *
	 *	Les signatures:
	 *
	 *	- row($tab, $key)
	 *	- row($tab, $onekey, $oneValue)
	 *	- row($tab, array($key, $key2))
	 *
	 *	@param string $table nom de la table dans la DB
	 *	@param mixed $key Le nom de la colonne INDEX ou des colonnes INDEX de la table. Peut être un String ou un Array de String
	 *	@return WDBRow Objet WDBRow
	 *	@access public
	 */
	function row($table, $key){
		if(($n=func_num_args())<2){ $this->IniErr('BADPRAMNUM6', 'WDB::row()', '2', $n); return null;}
		if(($n=func_num_args())>3){ $this->IniErr('BADPRAMNUM6', 'WDB::row()', '3', $n); return null;}
		if($n==3)$key = array($key => func_get_arg(2));
		if(!class_exists(__NAMESPACE__ .'\WDBRow'))require self::$selfdir.'/WDBRow.php';
		return new wdbrow($table, $key, $this);
	}

	/**	Fonction privée. Enregistre un paramètre nommé utilisateur ou interne
	 *
	 * Les signatures:
	 *
	 *   - addparamN($indice, $name)		pour un paramètre utilisateur
	 *   - addparamN($indice, $name, $val)	pour un paramètre interne.
	 *
	 *	Pour un paramètre interne, le nom pourra être passé à vide pour permettre la
	 *	génération automatique de celui-ci.
	 *
	 * @param int $qIndice	>=0 est l'indice du set de données pour lequel le paramètre est ajouté.
	 * @param string $name	nom du paramètre à ajouter. S'il vaut '' ou false il est généré automatiquement
	 * @param scalar $val	Valeur du paramètre à ajouter. Ne sera present que s'il s'agit d'un paramètre interne
	 * @return string Le nom du paramètre ajouté.
	 */
	private function addparamN($qIndice, $name)
	{	$this->newQueryEntry($qIndice); $q=&$this->params['queries'][$qIndice];

		if($name && isset($q['params'][$name]))return $name;
		if(!$name)$name=self::pname(); $n=func_num_args();
		$q['params'][$name] = null;

		$AP=&$this->params['allparams'];

		if(!$AP || $n==3 || $n==2 && !array_key_exists($name, $AP) )
		{	if($n==2)$this->params['UNamed'][] = $name;
			$AP[$name]=($n==2)?FALSE : func_get_arg(2);
		}

		return $name;
	}


	/**	On ajoute un parametre anonyme utilisateur ou interne
	 *	Voici la signature:
	 *
	 *		addparamA($indice)			pour un paramètre utilisateur
	 *
	 *	Tous les paramètres PDO internes et utilisateur, nommés et anonymes
	 *	sont consignés dans l'Array $this->params['allparams']. Les indexes
	 *	de ce tableau sont les noms des params ou leur indice d'enregistrement.
	 *
	 *	En plus de cela, les params utilisateur anonymes sont consignés dans le
	 *	Array $this->params['UAnonymes']. Dans ce tableau, les clés sont des in-
	 *	dices d'appartion des-dits paramètres (les rangs connus de l'utilisateur)
	 *	et les valeurs sont les indices internes, sous la forme suivante:
	 *	array(i_extern0 => i_extern0, ..., i_extern_n => i_intern_n)
	 *
	 *	Ainsi de cette facon, les params anonymes sont indexés dans le Array
	 *	$this->params['allparams'] par les indexes internes, les valeurs sont false
	 *	au debut.
	 *
	 *
	 */
	private function addparamA($qIndice)
	{	$this->newQueryEntry($qIndice); $p=&$this->params;
		$q=&$p['queries'][$qIndice]; $A=&$p['allparams'];

		$this->params['allparams'][$name = self::pname(true)]=false;
		$this->params['UAnonymes'][] = $name; $q['params'][$name]=null;

		return $name;
	}

	/**
	 *	Private function. Creates a PDO parameter internally for a given value.
	 *	$val might be an expression, in which case the parsed expression is returned.
	 *
	 * @param scalar $val	An expression or the value of the PDO parameter to generate. If it's not an expression can be Boolean, String, Number, or Null.
	 * @param int $qIndice	>=0  Index of the dataset for which parameter is generated
	 * @param bool $testvar Indicates when is TRUE that it is necessary to test if the given value is an expression and to treat it as such.
	 *						If it is FALSE, the value is not tested, it is assumed that it is not an expression.
	 * @return string       The parsed expression or the name of the generated PDO parameter if all goes well
	 */
	function addInternalVal($val, $qIndice, $testvar=1)
	{	if(is_null($val)) return 'NULL'; if(is_bool($val))$val=$val?1:0;

		if(is_string($val) && $testvar && !is_numeric($val) && strpos($val, ($xB=$this->exp_begin))===0)
		{
			return $this->analyse_expr(substr($val, strlen($xB)), $qIndice);
		}

		return !is_scalar($val) && !is_resource($val)?false:$this->addparamN($qIndice, '', $val);
	}

	/**
	 * Private function. The function adds in the internal buffer the query of
	 * the dataset whose index is qIndice
	 *
	 * @param string $q	The query to add
	 * @param int $qIndice	>=0  The rank of the dataset
	 * @return null		The function returns nothing
	 */
	private function addQuery($q, $qIndice)
	{	$this->params['queries'][$qIndice]['query'] = $q;}

	private function getQuery($qIndice=0)
	{
		return @$this->params['queries'][$qIndice]['query'];
	}

	/**
	 * Fonction privée. Apprête une nouvelle entrée de requête pour un set de données à éditer
	 * ou à créer. Cette entrée contiendra la requête SQL, les paramètres PDO de la requête etc ...
	 * @param int $indice  >= 0, represente l'indice du set de données
	 * @return null
	 */
	private function newQueryEntry($indice)
	{	if(!$this->params)$this->params=array('queries'=>array(), 'allparams'=>false, 'UAnonymes'=>false, 'UNamed'=>false);
		if(!@$this->params['queries'][$indice])$this->params['queries'][$indice]=array(
		'query'=>'', 'params' => false);
	}

	/**
	 *  Fonction privée. Renvoie le nombre de paramètres PDO utilisateur
	 *  pour la requête UPDATE ou INSERT en cours.
	 *
	 *  @return int Le nombre total de paramètres utilisateurs dans la requêtes
	 */
	function UParamNbr()
	{	$T=&$this->params; return 
		(isset($T['UAnonymes']) && $T['UAnonymes']?count($T['UAnonymes']):0) +
		(isset($T['UNamed']) && $T['UNamed']?count($T['UNamed']):0);
	}

	/**
	 *  Fonction privée. Renvoie le nombre de paramètres PDO utilisateur
	 *  dont la valeur a été renseignée par l'utilisateur
	 *
	 *  @return int Le nombre total de paramètres utilisateurs dans la requêtes
	 */
	function UParamSubmited()
	{	$T=&$this->params; return isset($T['NBsupplied'])?$T['NBsupplied']:0;
	}

	/**	Enregistre la valeur d'un paramètre PDO utilisateur, ainsi que son type si donné.
	 *	
	 *	@param int|string $p L'indice ou bien le nom du paramètre PDO
	 *	@param int|string $v La valeur du paramètre PDO
	 *	@param string $type Le type du paramètre PDO
	 *	@return null Pas de retour
	 *
	 *	Les paramètres PDO sont automatiquement enregistrés pendant l'analyse de la requête.
	 *	Ils sont tous consignés dans le params['allparams'], mais aussi dans le tableau 
	 *	params['UAnonymes'] s'ils sont anonymes. Au moment de cet enregistrement, un nom est
	 *	automatiquement généré pour les params anonymes, ce nom est la valeur dans params['UAnonymes']
	 *	pendant que $p-1 est l'indice. Cette même valeur (le nom généré) est l'indice dans
	 *	params['allparams'] et la valeur est FALSE lorsque celle-ci n'est pas encore fixée.
	 */
	private function SetUParamVal($p, $v, $type=false)
	{	$Q=&$this->params; if(($int=is_int($p)) && !isset($Q['UAnonymes'][$p-1]) ||
		!$int && !isset($Q['allparams'][$p]))return false;

		$iInd = $int? $Q['UAnonymes'][$p-1]:$p;

		# Indique si le param a déjà récu une valeur
		$valExists = $Q['allparams'][$iInd] !== FALSE;
		if(is_resource($v) && $type!=\PDO::PARAM_LOB)$v=stream_get_contents($v);

		if($valExists)
		{	if($this->pdoS && $this->exec_state)
			{	$this->pdoS->closeCursor(); $this->pdoS = null; }
			$this->exec_state = 0;
		}
		else
		{	$Q['allparams'][$iInd]=$v;
			if(!isset($Q['NBsupplied']))$Q['NBsupplied']=1;
			else $Q['NBsupplied']++; 
		}

		if($type)$Q['types'][$iInd] = $type; return $iInd; //return TRUE;
	}


	/*	Enregistre en une seule fois les valeurs des paramètres PDO utilisateur, ainsi
	 *	que leurs types si fournis.
	 *
	 *	@param array $pA le tableau des paramètres PDO
	 *	@param array $types le tableau des types des paramètre PDO
	 *	@return null Pas de retour
	 */
	private function SetUParamVals($pA, $types=false)
	{	foreach($pA as $i => $val)
		{	if(!is_int($i) && !is_string($i))return $this->IniErr('BADPRAMNAME');
			$t = @$types[$i]; $OUI = $this->ORAC_UI();

			if(!($iPar=$this->SetUParamVal($i, $val, $t)))
				return $this->IniErr('PRAMNTFND', is_int($i)?'index':'name', $i);

			if($OUI && ($t == \PDO::PARAM_LOB ||
			is_resource($val) && !$t))$this->params['ORA_E_BLOB'][$iPar]=$i;
		}
		return TRUE;
	}

	/* Renvoie le nombre de parametres PDO utilisateur dont la valeur a déjà été données
	 */
	function NbUSubmitedVals()
	{	$Q=&$this->params; return ($Q && $Q['supplied'])?$Q['supplied']:0;}


	/*	Ces fonctions ne servent qu'á l'affichage et doivent être enlevées
	 *	Plus tard
	 */
	function display()
	{
		foreach(func_get_args() as $var)
		{	if($var=='queries' || $var=='allparams' ||
				$var=='ptypes' || $var=='UAnonymes' || $var=='NBSupplied')
				var_dump(isset($this->params[$var])?$this->params[$var]:null);
			else var_dump($this->{$var});
		}
		return $this;
	}
	
	function NbUP($qIndice=0)
	{	$t=&$this->params;
		return isset($t['queries'][$qIndice]['NbUP'])?$t['queries'][$qIndice]['NbUP']:0;
	}
	
	function Queries($qIndice=0)
	{	$t=$this->params;
		return isset($t['queries'][$qIndice])?$t['queries'][$qIndice]:null;
	}

	/*	Renvoie FALSE si un Array ne possede que des clés entières et TRUE sinon.
	 */
	static function isAssociativeArray(&$arr)
	{	foreach($arr as $k => &$val){ if(!is_int($k)) return true; } return false;}

	/*
	//https://docs.oracle.com/cd/B19306_01/server.102/b14200/ap_keywd.htm#g691972
	private static function setOracleKW()
	{	if(!self::$orKWs)self::$orKWs = 
		array('ACCESS', 'ADD','ALL','ALTER', 'AND', 'ANY','AS','ASC','AUDIT','BETWEEN','BY','CHAR',
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
	}
	*/
	private function c(){self::$cIns=$this;}
	static function lastInstance(){return self::$cIns;}
	private function ORAC_UI(&$update=false)
	{	if($this->dbtype!=self::ORACLE){$update=false;return false;}
		$update=$this->ci=='UPDATE'; return $update?$update:$this->ci=='INSERT';
	}

	/*	 pOut($fonct, success, $return)
	 *	 pOut($fonct, success, $return, $errorCode[, $param1, ..., $param_n])
	 *
	 *	$funct	:  String - The name of the function
	 *	$success:  boolean - Indicates whether the function was successful or not.
	 *	$return	:  Normal return value of the function. The value "" is synonymous with this
	 *	$errorCode : String - An error code, optionnal.
	 *	$param_i:	String - Parameter (s) accompanying the error code, optionnal.
	 *
	 *
	 *	HOW THE FONCTION WORKS
	 *
	 *	If the error code is present, an error is raised. This action can lead to the
	 *	throwing of an exception. If so, the function stops there. If an execption is not
	 *	raised or if the error code is missing :
	 *
	 *	- If $this->extra_return is FALSE, the function returns $return ( normal value ) 
	 *	- Otherwise the function returns $succes if the name of the function is in the
	 *	  $this->boolF array
	 *	- If the name of the function is not in the $this->boolF array the function
	 *	  returns the WDB entity ( $this )
	 *
	 */
	private function pOut($funct, $success, $return)
	{	$r=($return==='')?$this:$return;

		if(($n=func_num_args())>3)
		{  $t=func_get_args(); array_splice($t, 0, 3); $success=false;
		   call_user_func_array(array($this, 'IniErr'),$t);
		}

		# The boolean response should be returned only if the function is in the array boolF
		if($this->extra_return)
			if(array_key_exists($funct, $this->boolF))return $success?true:false;
			else return $this;

		return $return;
	}

	/**
	 *  @brief Brief Allows you to change the response type of a function.
	 *  
	 *  @param [string] $funt The function name
	 *  @param [bool] $isbool If true, the function will now return a boolean type, otherwise the WDB entity.
	 *  @return Return The WDB entity
	 *
	 *  @details
	 *  Most of the common WDB functions return the WDB entity for chaining purposes.
	 *  But a few return a boolean. This function allows you to change the response type
	 *  of a specific function.
	 */
	function changeResponseType($funct, $isbool)
	{	$f=strtolower($funct); if(!$isbool){ unset($this->boolF[$f]);}
		else $this->boolF[$f]=0; $this->extra_return=1;return $this;
	}

	static function identHasNormalChars($name, $dbtype)
	{	$reg = ($t=$dbtype)==self::FIREBIRD?"/^[a-zA-Z][a-zA-Z0-9_\$]*$/":
		($t==self::ORACLE?"/^[a-zA-Z][a-zA-Z0-9_\$#]*$/":"");
		return $reg?preg_match($reg, $name):false;
	}

	static function identIsreservedWord($name, $dbtype)
	{	$name = strtoupper($name);

		if(($db=$dbtype)==self::ORACLE)
		{	if(!self::$orKWs)
			{	include self::$selfdir.'/oracle.res-kw.php';
				self::$orKWs = $or_reserved_kw;
			}
			return in_array($name, self::$orKWs);
		}

		if($db==self::FIREBIRD)
		{	if(!self::$firKWs)
			{	include self::$selfdir.'/firebird.res-kw.php';
				self::$firKWs = $fi_reserved_kw;
			}
			return in_array($name, self::$firKWs);
		}

		return true;
	}

	static function identIsNormal($name, $dbtype)
	{	return ($dbtype==self::FIREBIRD || $dbtype==self::ORACLE)?
		self::identHasNormalChars($name, $dbtype) &&
		!self::identIsreservedWord($name, $dbtype) : false;
	}
	
	function fakeThis($succes=true)
	{
		return $this->pOut('fakethis', $succes, $this);
	}
	
	function fakeBool($succes=true)
	{
		return $this->pOut('fakebool', $succes, $succes);
	}
}


class WDBException extends \Exception
{	static public $display_errors=false;
	private $html='';
	function __construct($message, $code = 0, $previous = null)
	{	parent::__construct($message, $code, $previous);

		$this->message=static::getFullMsg($message, $this->html, $ctx);
		$this->file=@$ctx['file']; $this->line=@$ctx['line'];
		if(static::$display_errors)echo $this->html; 
		if($code!==FALSE)$this->code=$code;
	}

	function __toString(){return $this->message;}

	function ErrorHtml(){return $this->html;}

	static function getFullMsg($msg, &$html=false, &$ctx=false)
	{	// https://www.php.net/manual/fr/function.debug-backtrace.php

		if(wdb::$display_error_line)
		{	$ctx = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS, 4);
			$le = ' - This error was originally raised at line '.$ctx[3]['line'].' of the '.$ctx[3]['file'].'file';
		}else $le='';

		$ctx=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		$ns =__NAMESPACE__.'\\'; $lst=null;
		$wdbfile = WDB::$selfdir."\\WDB.php";
		$wdrfile = WDB::$selfdir."\\WDBRow.php";
		$wddfile = WDB::$selfdir."\\WDD.php";
		$sqmath = WDB::$selfdir."\\sqlite.math-functions.php";

		foreach($ctx as $key => &$tab)
		{	#if(isset($tab['class']) && ($tab['class'] == $ns.'WDBException' || $tab['class'] == $ns.'WDBRow' ||
			#$tab['class'] == $ns.'WDB' || $tab['class'] == $ns.'WDD')) continue;
			if(!($f=@$tab['file']) || $f== $wdbfile || $f==$wdrfile || $f==$wddfile || $f==$sqmath) continue;
			if(class_exists($ns.'\WDD') && $tab['file']==WDD::$selfdir)continue;
			break; // On a trouvé
		}

		$ctx=$tab; $msg = $msg.' - LINE '.$ctx['line'].', FILE '.$ctx['file'].$le;
		$html = ' <span style="padding:5px 5px;background:#fcb603;color:#D8000C;background-color:#FFBABA;display:inline-block;font-size:20px;">'.$msg.'</span> ';
		return $msg;
	}
}

WDB::$selfdir = __DIR__;
?>
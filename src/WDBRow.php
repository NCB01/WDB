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
 *******************************************************************************
 *
 *  Class for simple data manipulation by Active record. The WDB class is necessary, because the
 *  functions implemented here are just shortcuts to the WDB functions.
 */
namespace NCB\WDB;

class WDBRow
{	private $tbdef=null, # tableau conteneur de propriétés
	$tab, $key,	# nom de la table et de la / des clés
	$er='', $q, $r, # derniere erreur et derniere requête, et données raw
	$ck=false, $wdb=null; #	valeur(s) courante(s) de/des clé(s) et entité wdb

	/**
	 *  @brief Brief Constructor of the WDBRow class. Its use requires a database table with an INDEX column or unique key
	 *  
	 *  @param [string] $tab The name of the database table
	 *  @param [string] $key The name of the index or key column of the table
	 *  @param [WDB] $wdb An instance of WDB
	 *  @return WDBRow description
	 *  
	 *  @details More details
	 */
	function __construct($tab, $key='', $wdb=null)
	{	$this->tab=$tab; if($wdb)$this->wdb=$wdb;
		if(!$this->processKey($key))wdb::WDBException('Wrong key name or value');
	}

	private function processKey($key)
	{	$f=false; $t=true; if(is_string($key) && $key){ $this->key=$key; return $t;}

		if(!is_array($key) || !$key)return $f; $n=count($key);

		foreach($key as $kn => $kv)
		{	if(is_string($kn) && !$kn || is_int($kn) && (!is_string($kv) || !$kv)
			|| !is_string($kn) && !is_int($kn)) return $f;

			if($n==1)
			{	if(is_int($kn))$this->key=$kv;
				else{ $this->key=$kn; $this->__set($kn, $kv);}
				return $t;
			}

			if(is_int($kn))$this->key[] = $kv;
			else{ $this->key[] = $kn;  $this->__set($kn, $kv);}
		}
		return $t;
	}

	public function __debugInfo(){
		$t=$this; return array('tab'=>$t->tab,'key'=>$t->key, 'wdb'=>'[WDB entity]',
		'currentkey'=>$t->ck,'columns'=>$t->tbdef,'raw'=>$t->r,'query'=>$t->q,'error'=>$t->er);
	}

	/**
	 *  @brief This function adds a column with its value to the WDBRow object
	 *  
	 *  @param [string] $prop The column name
	 *  @param [mixed] $val  The value of the colum. This value must be a scalar.
	 *  @return 
	 */
	function __set($prop, $val)
	{	if(!$this->tbdef)$this->tbdef=array();
		$this->tbdef[$prop] = $val;
	}
	/**
	 *  @brief Returns the value of a column if it exists, or FALSE otherwise
	 *
	 *  @param [string] $prop A column name
	 *  @return mixed The value of the column
	 */
	function __get($prop)
	{	if(!$this->tbdef || !array_key_exists($prop, $this->tbdef)) return FALSE;
		return $this->tbdef[$prop];
	}
	/**
	 *  This function populates the column names and their respective values in a single call.
	 *	If some column names had already been entered before, they will not be deleted,
	 *	the new column names are just added.
	 *  
	 *  @param array $columns Array indexed by column names, each column name must have its value
	 *  @return WDBRow
	 *  
	 */
	function set($columns){ if($columns && (is_array($columns)||is_object($columns)))
		foreach($columns as $k => $v)$this->$k=$v; return $this;
	}
	/**
	 *  @brief This function resets the WDBRow object. The error message, the name of the table as well as the names of columns and their values are erased. Only the reference to the WDB object remains.
	 *
	 *  @return WDBRow The same WDBRow object is returned
	 */
	function init($table, $key){ $t=$this; $t->tab=$this->key=''; $t->delData();
		if(!$t->processKey($key))$t->er='Wrong key name or value';
		return $t->er?false:true;
	}

	function deleteData(){$T=$this; $T->tbdef=null;$T->er=$T->q=$T->r='';
		$this->ck=false; return $this;
	}

	function removeColumns(){
		$a = func_get_args(); foreach($a as $c)unset($this->tbdef[$c]); return $this;
	}

	/**
	 *  @brief Returns the WDB object used internally
	 *  
	 *  @return WDB|null
	 */
	function wdb(){return $this->wdb?$this->wdb:wdb::$lastI;}

	/**
	 *  @brief Loads one row from the database.
	 *  
	 *  @param mixed $keyVal The value of the key or index of the row to load
	 *  @return boolean TRUE if the line has been loaded and FALSE otherwise
	 * 
	 */
	function load($keyVal=null, $cols='*')
	{	try{ob_start();
		$T=$this; if((is_null($keyVal) || $keyVal===FALSE)&& !($keyVal=$T->gkeyVal()))
		{$this->er='Key value is not set'; return false;} $this->deleteData(); $x=false;
		$k=$T->key; $a=is_array($k); if(!($w=$T->wdb())||!$T->tab||!$k)
		{ $T->er=WDB::ErrorFromFile('RowCheck'); return $x;}

		if(is_array($keyVal)){ $X=new \ArrayObject($keyVal); 
		$cond = $X->getArrayCopy(); $cond[0]='AND';}
		elseif(!is_scalar($keyVal))return false;
		else{ $cond[$k]=$keyVal;}  $ord=$a?$k[0]:$k;

		$w->init()->select($cols)->from($this->tab)->where($cond)->orderby($ord)
		->limit(1)->exec(); $this->q=$w->getLastSQL(); $this->r= $w->raw();
		if(!($this->er=$w->error()) && ($x=$w->fetch()))$this->tbdef=$x;$w->init();
		if(!$a)$this->{$this->key}=$keyVal; ob_end_clean();return $x?true:false;
		}catch(\Exception $e){ $T->er=$w->error()?$w->error():$e->getMessage(); $w->init();}

		ob_end_clean(); return false;
	}

	private function save($upd)
	{	// $W est (si existe) la valeur de la clé dans la DB. S'il c'est un array
		// il sera indexé par les nom des colonnes composant l'index
		try{ob_start();
		$k=$this->key; $AK=$this->ck; $w=$this->wdb(); $W=$this->ck;

		if(!($d=$this->tbdef) && ($c='RowNoData') || !$w &&
		($c='RowNoWDB') || $upd && !$this->keyInData($c))
		{ $this->er=$e=wdb::ErrorFromFile($c); return false;}

		$tb=$this->tab; $w->init(); $ia=is_array($k);

		if($upd){ if($ia){
			if(!$W)foreach($k as $i)$W[$i] = array_key_exists($i, $d)?$d[$i]:null;
			elseif(!is_array($W))$W=array($k =>$W);}else{ if($W && !is_array($W))$W=array($k=>$W); }
			if($W){$W[0]='AND';$w->update($tb, $d, $W);}
			else $w->update($tb, $k, $d); }else{ $w->insert($tb, $d);}

		$this->er=$e=$w->error();

		# Chargement de l'ID si possible et necessaire
		if(!$e && !$upd && !$ia){ $this->{$k} = @$w->lastInsertId($tb, $k);}

		$this->q=$w->getLastSQL(); $this->r= $w->raw(); $w->init(); 
		ob_end_clean(); return $e?false:true;
		}catch(\Exception $E)
		{ $T->er=$w->error()?$w->error():$E->getMessage(); $w->init();}

		ob_end_clean(); return false;
	}

	function getlastSQL(){return $this->q;}
	function raw(){return $this->r;}
	function error(){return $this->er;}
	function add(){return $this->save(0);}
	function insert(){return $this->save(0);}
	function update(){return $this->save(1);}
	function currentkeyval($val){$this->ck=$val; return $this;}

	/*	Renvoie TRUE si les données d'index sont presentes et FALSE sinon.
	 *	Si les données sont absentes $code ressort avec un code d'erreur.
	 */
	private function keyInData(&$code=''){ if(!($d=$this->tbdef) && ($code='RowNoData'))return false; $n=0;
		if(!is_array($k=$this->key) && ($code='RowNoKData'))return ($this->ck || array_key_exists($k, $d) && !is_null($d[$k]));
		foreach($k as $i)if(array_key_exists($i, $d) && !is_null($d[$i]))$n++; $code=$n?'':'RowNoKDataM'; return $n;
	}

	/*	Renvoie un array indexé par les colonnes de la clé, chaque colonne pointant
	 *	sur sa valeur. Si toutes les colonnes de la clé n'ont pas encore recu une
	 *	valeur, la fonction renvoie FALSE
	 */
	function gkeyVal()
	{	$k=&$this->key; $t=&$this->tbdef; if(!$t||!$k)return false;
		if(!is_array($k))return array_key_exists($k, $t)?$t[$k]:false;
		foreach($k as $i => $v){ $col = is_int($i)?$v:$i;
			if(!array_key_exists($col, $t))return false; $r[$col]=$t[$col];
		}
		return $r;
	}
}
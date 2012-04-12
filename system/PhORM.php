<?php
namespace system;
require_once("MDB2.php");

/*
 * Created on Dec 29, 2010
 * John Barlow
 * 
 * PhORM - PholdBox ORM
 *  
 *  The only requirement for PhORM is that every table that is managed by PhORM
 *  have an "id" field that is an auto increment.  This limitation should fit within 90% 
 *  of the applications written in the framework.  I know this could require your tables 
 *  to be a bit larger than they need to be, but it greatly simplifies the ORM logic.  
 *  Besides, if you want to do something more complex (and horribly inefficient) like 
 *  using UUID's for ID fields, or remove my one extra column you need for PhORM, go ahead...
 *  roll your own.  I'm not stopping you :).
 *
 * 	ORM definitions are configured within the ORM array.
 *	
 *	$ORM - dsn - new dsn if not using default system dsn.  Specifying an invalid DSN
 				WILL make your page hang.
 *		   tableName - Database Table
 *		   columns - Array of column names.
 *		   types - Array of column types.
 *		   values - Associative array of values.
 *		   links - defines linked items - ManytoOne, OnetoMany, OnetoOne.
 * 
 */
 //TODO: set up links logic
 class PhORM extends PholdBoxBaseObj
 {
 	protected $ORM = array();
 	protected $db = null;
 
 	function __construct()
 	{
 		$current_dsn="";
 		$this->SYSTEM = $GLOBALS["SYSTEM"];
 		if(array_key_exists("dsn", $this->ORM) && $this->ORM["dsn"] != "")
 		{
 			$current_dsn = $this->ORM["dsn"];
 		}
 		else
 		{
 			$current_dsn = $this->SYSTEM["dsn"]["default"]; 
 		}
 		
 		$this->db = \MDB2::connect($this->SYSTEM["dsn"][$current_dsn]["connection_string"]);
 		
 		if(\PEAR::isError($this->db))
 		{
 			die($this->db->getMessage());
 		}
 		parent::__construct();
 	}
 	
 	/*
 		Name: setValue
 		
 		Does: Sets value of key into the VO
 		
 		Returns: Nothing
 	*/
 	public function setValue($key, $value)
 	{
 		$this->ORM["values"][$key] = $value;
 	}
 	
 	/*
 		Name: getValue
 		
 		Does: Gets value of key into the VO
 		
 		Returns: value
 	*/
 	public function getValue($key)
 	{
 		return $this->ORM["values"][$key];
 	}
 	
 	/*
 		Name: __call
 		
 		Does: This is a php 5 "magic" function that gets called if you call a function that doesn't exist.
 		      The purpose of this is to handle calling "get<value>".  If it doesn't find a key in the object,
 		      it passes the call up to the parent to be handled.
 		      
 		Returns: db value of object, or whatever the parent decides to return (could be an object)
 	*/
 	public function __call($name, $arguments)
 	{
 		$action = substr($name, 0, 3);
 		$prop = lcfirst(substr($name, 3));
 		if($action == "get")
 		{
 			if(in_array($prop, $this->ORM["columns"]))
 			{
 				return $this->getValue($prop);
 			}
 			else
 			{
 				return parent::__call($name, $arguments);
 			}
 		}
 		else if($action == "set")
 		{
 			if(in_array($prop, $this->ORM["columns"]))
 			{
 				$this->setValue($prop, $arguments[0]);
 			}
 			else
 			{
 				parent::__call($name, $arguments);
 			}
 		}
 	}

	/*
		Name: generateSelect()
		
		Does: Generates a select statement based on the values in the DB object.  The where clause is 
		      generated by ANDing the values of the object together.
		
		Returns: SQL select statement (string)
	*/ 
 	protected function generateSelect()
 	{
 		$sql = "select ";
 		$first = true; 
 		$wFirst = true;
 		$where = "";
 		foreach($this->ORM["columns"] as $column)
 		{
 			if(!$first)
 			{
 			 	$sql = $sql . ", ";
 			}
 			$sql = $sql . $column;
 			$first=false;
 			if(array_key_exists($column, $this->ORM["values"]) && $this->ORM["values"][$column] != '')
 			{
 				if(!$wFirst)
 				{
 					$where = $where . " and ";
 				}
 				$where = $where . $column . "='" . $this->ORM["values"][$column] . "'";
 				$wFirst = false;
 			}
 		}
 		$sql = $sql . " from " . $this->ORM["tableName"];
 		if($where != '')
 		{
 			$sql = $sql . " where " . $where;
 		}
 		
 		return $sql;
 	}
 	
 	/**
 	 * query
 	 * 
 	 * This is a wrapper to the underlying PEAR db query function so that you can have custom queries in objects.
 	 * The queries should be in functions named "qMyCustomQueryName".  This is where you put the custom SQL and call
 	 * $this->query($sql);
 	 * 
 	 * @param string $sql SQL string to execute
 	 * @return Object DB object with the result of the query
 	 */
 	public function query($sql)
 	{
 		$result = $this->db->query($sql);
 		
 		// Always check that result is not an error
		if (\PEAR::isError($result)) {
		    die($result->getMessage());
		}
		
		return $result;
 	}
 	
 	/**
 	 * load
 	 * 
 	 * This function loads one object if an ID is supplied, otherwise it will do a
 	 * bulk search across the db and return an array of objects
 	 * 
 	 * @return array Array of matching objects if in bulk mode.
 	 */
 	public function load()
 	{
 		$bulk = false;
 		$returnArray = array();
 		
 		if($this->getId() == '')
 		{
 			$bulk = true;
 		}
 		
 		$sql = $this->generateSelect();	
 		
 		$result = $this->db->query($sql);
 		
 		// Always check that result is not an error
		if (\PEAR::isError($result)) {
		    die($result->getMessage());
		}
		
		//load object
		if($result->numRows() == 1)
		{
			$row = $result->fetchRow();
	
			$resultCols = $result->getColumnNames();
			foreach($this->ORM["columns"] as $column)
			{
				$this->setValue($column, $row[$resultCols[strtolower($column)]]);
			}
 		}
 		//load objects
 		else if($result->numRows() > 1)
 		{
 			$class= get_class($this);
 			$row = $result->fetchRow();
 			while($row != null){
 				
				$obj = new $class;
 				
				$resultCols = $result->getColumnNames();
				foreach($this->ORM["columns"] as $column)
				{
					$obj->setValue($column, $row[$resultCols[strtolower($column)]]);
				}
				array_push($returnArray, $obj);
				$row = $result->fetchRow();
 			}
 		}
 		else
 		{
 			foreach($this->ORM["columns"] as $column){
 				$this->setValue($column, "");
 			}
 			
 		} 	
 		
 		return $returnArray;	
 	}
 	
 	protected function generateUpdate()
 	{
 		$sql = "update ". $this->ORM["tableName"] . " set ";
 		$first = true;
 		
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id")
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$sql = $sql . ", ";
	 			}
	 			$sql = $sql . $column . " = '" . $this->ORM["values"][$column] . "'";
	 			$first=false;
 			}
 			
 		}
 		
 		$sql = $sql . " where id = " . $this->ORM["values"]["id"] . ";";
 		
 		return $sql;
 	}
 	
 	protected function generateInsert()
 	{
 		$sql = "insert into ". $this->ORM["tableName"];
 		$first = true;
 		$colNames = " (";
 		$values = " values (";
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id")
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$colNames = $colNames . ", ";
	 			 	$values = $values . ", ";
	 			}
	 			
	 			//$colNames = $colNames . "'" . $column . "'";
	 			$colNames = $colNames . $column;
	 			$values = $values . "'" . $this->ORM["values"][$column] . "'";
	 			$first=false;
 			}
 			
 		}
 		$colNames = $colNames . ")";
 		$values = $values . ")";
 		$sql = $sql . $colNames . $values . ";";
 		
 		return $sql;
 	}
 	
 	//TODO: Finish this
 	protected function generateBulkInsert($tempTableKey)
 	{
 		$target = '';
 		$sql = '';
 		
 		if($tempTableKey != null)
 		{
 			//TODO: create temp table from $tempTableKey, add to sql. Set table name to temp table key.
 		}
 		else
 		{
 			$target = $this->ORM["tableName"];
 		}
 		
 		$sql .= "insert into ". $target;
 		$first = true;
 		$colNames = " (";
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id")
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$colNames = $colNames . ", ";
	 			}
	 			
	 			$colNames = $colNames . $column;
	 			$first=false;
 			}
 			
 		}
 		$colNames = $colNames . ")";
 		$sql = $sql . $colNames;
 		
 		return $sql;
 	}
 	
 	/*
 	 * Name: generateBulkSelect
 	 * Does: crreates the select statements needed for bulk inserting
 	 */
 	protected function generateBulkSelect()
 	{
 		$sql = "Select ";
 		$first = true;
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id")
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$sql = $sql . ", ";
	 			}
	 			
	 			$sql = $sql . "'" . $this->ORM["values"][$column] . "'";
	 			$first=false;
 			}
 			
 		}
 		//$sql = $sql . ";";
 		
 		return $sql;
 	}
 	
 	/*
 	 * Name: Save
 	 * 
 	 * Does: Saves the object based on the values.  If an ID is given, it will update instead 
 	 * of insert.
 	 */
 	public function save()
 	{
 		$sql = "";
 		if(array_key_exists("id", $this->ORM["values"]) && $this->ORM["values"]["id"] != "")
 		{
 			//if the id is defined, update
 			$sql = $this->generateUpdate();
 		}
 		//else, if the id is not defined, insert.
 		else
 		{
 			$sql = $this->generateInsert();
 		}
 		
 		$result = $this->db->exec($sql);
 		
 		// Always check that result is not an error
		if (\PEAR::isError($result)) {
		    die($result->getMessage());
		}
 		return $result;
 	}
 	
 	/*
 	 * Name: Delete
 	 * 
 	 * Does: removes record from db based on the values in the object
 	 */
 	public function delete()
 	{
 		$sql = "delete from ". $this->ORM["tableName"];
 		//sanity check
 		
		if(!array_key_exists("id", $this->ORM["values"]) || $this->ORM["values"]["id"] == "")
		{
			print(get_class($this) . " - id is undefined");
			exit;
		}
 		$sql = $sql . " where id='". $this->ORM["values"]["id"] ."'";
 				
 		print($sql);
 	}
 	
 	
 	//TODO: create getlist? Function
 	
 	//TODO: create cascade Save Function? (make it queue up queries and send as one)
 	
 	//TODO: finish bulkSave
 	public function bulkSave($items)
 	{
 		$insertSQL = "";
 		$updateSQL = "";
 		$insertCount = 0;
 		$updateCount = 0;
 		
 		foreach($items as $item)
 		{
	 		if(array_key_exists($item->getId() != ""))
	 		{
	 			
	 			//if the id is defined, update
	 			//$sql = $this->generateUpdate();
	 		}
	 		//else, if the id is not defined, insert.
	 		else
	 		{
	 			if($insertCount == 0)
	 			{
	 				$insertSQL = $item->generateBulkInsert() . " ";
	 			}
	 	
	 			$insertSQL = $insertSQL . $item->generateBulkSelect() . " ";
	 			$insertCount++;
	 			
	 			if($insertCount < $this->SYSTEM["dbBatchSize"])
	 			{
	 				$insertSQL = $insertSQL . "UNION ALL ";
	 			}
	 			else
	 			{
	 				$insertCount = 0;
	 				$insertSQL = $insertSQL . ";";
	 				$result = $this->db->exec($insertSQL);
 		
			 		// Always check that result is not an error
					if (\PEAR::isError($result)) {
					    die($result->getMessage());
					}
	 			}
	 		}
	 	}
	 	//todo: remove final union all
 		print($insertSQL);
 		
 		//cleanup section, run these if there are any loop iterations that didn't make it in
 		//the first time.
 		if($insertCount != 0)
 		{
	 		$result = $this->db->exec($insertSQL);
	 		
	 		// Always check that result is not an error
			if (\PEAR::isError($result)) {
			    die($result->getMessage());
			}
		}
		
		if($updateCount != 0)
		{
			$result = $this->db->exec($updateSQL);
	 		
	 		// Always check that result is not an error
			if (\PEAR::isError($result)) {
			    die($result->getMessage());
			}
		}
 		//return $insertSQL;
 	}
 }
?>

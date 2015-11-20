<?php
/**
 * PDO_Wrapper class.
 *
 * Wrapper object for a PDO connection to the database.
 *
 * @author     Leandro Ibarra
 * @copyright  2015
 * @license    MIT License
 */
class PDO_Wrapper {
	/**
	 * Default database configuration constants.
	 *
	 */
	const DB_DRIVER = '';
	const DB_HOST = '';
	const DB_NAME = '';
	const DB_USER = '';
	const DB_PASS = '';
	const DB_PORT = '';

	/**
	 * Enables write all captured errors.
	 *
	 * @var boolean
	 */
	public static $LOG_ERRORS = true;

	/**
	 * Directory path where will be written error file.
	 *
	 * @var string
	 */
	public static $LOG_ERRORS_DIRECTORY = '';

	/**
	 * File where will be written the errors.
	 *
	 * @var string
	 */
	public static $LOG_ERRORS_FILE = '@@__DATE__@@-pdo_wrapper.log';

	/**
	 * Dynamic config credentials.
	 *
	 * @var array - configuration parameters
	 */
	protected $config_params;

	/**
	 * PDO object for the DB connection.
	 *
	 * @var PDO - PDO object
	 */
	protected $pdo_object;

	/**
	 * Store any Exception or PDOException errors to be viewed from the outside.
	 *
	 * @var Exception|PDOException - stores all the exceptions
	 */
	protected $pdo_exception;

	/**
	 * PDO_Wrapper single instance (singleton).
	 *
	 * @var PDO_Wrapper
	 */
	protected static $instance = null;

	/**
	 * Get an instance of the PDO_Wrapper.
	 *
	 * @return PDO_Wrapper
	 */
	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new PDO_Wrapper();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Make protected so only subclasses and self can create this object (singleton).
	 *
	 */
	protected function __construct() {}

	/**
	 * Configure connection credentials to the DB.
	 * 	
	 * @param string $psHost - host name to connect
	 * @param string $psName - database name
	 * @param string $psUser - database user name
	 * @param string $psPass - database user password
	 * @param string $psPort (optional) - port using to connect, default to 3306 port
	 * @param string $psDriver (optional) - driver to be used, default mysql
	 */
	public function configConnection($psHost, $psName, $psUser, $psPass, $psPort='3306', $psDriver='mysql') {
		try {
			if (!$this->validateDriver($psDriver))
				throw new Exception('Error, the database you wish to connect to is not supported by your install of PHP.');

			if (isset($this->pdo_object))
				throw new Exception('Warning, configuration attempt after that connection exists.');
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}

		$this->config_params = array(
			'driver' => $psDriver,
			'host' => $psHost,
			'name' => $psName,
			'user' => $psUser,
			'pass' => $psPass,
			'port' => $psPort
		);
	}

	/**
	 * Create a PDO connection using the credentials provided.
	 * 
     * @param string $psDriver - driver to be used, default mysql
	 * @param string $psHost - host name to connect
	 * @param string $psName - database name
	 * @param string $psUser - database user name
	 * @param string $psPass - database user password
	 * @param string $psPort (optional) - port using to connect, default to 3306 port
	 * @return PDO - PDO object with a connection to the database specified
	 */
	protected function createConnection($psDriver, $psHost, $psName, $psUser, $psPass, $psPort='3306') {
		try {
			if (!$this->validateDriver($psDriver))
				throw new Exception('Error, the database you wish to connect to is not supported by your install of PHP.');
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}

		// Attempt create PDO object and connect to the database
		try {
			// Note: The following drivers are NOT supported yet: odbc, ibm, informix, 4D
			// Build the connection string based on the selected PDO driver
			switch($psDriver) {
				case 'sqlite':
				case 'sqlite2':
					$sConnection = "$psDriver:$psHost";
					break;
				case 'sqlsrv':
					$sConnection = "$psDriver:Server=$psHost;Database=$psName";
					break;
				case 'firebird':
				case 'oci':
					$sConnection = "$psDriver:dbname=.$psName";
					break;
				case 'mysql':
				default:
					$sConnection = "$psDriver:host=$psHost;dbname=$psName";
					break;
			}

			$sConnection .= ";port=$psPort";

			// Initialize PDO object that representing a connection to the database
			$oPdo = new PDO($sConnection, $psUser, $psPass);

			// Set errors report and launch PDO exceptions
			$oPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// Return PDO object
			return $oPdo;
		} catch (PDOException $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

	/**
	 * Grab PDO connection to the DB.
	 *
	 * @return PDO - PDO object
	 */
	protected function getConnection() {
		// If credentials have not been provided, use default credentials
		if (!isset($this->config_params)) {
			$this->config_params = array(
				'driver' => self::DB_DRIVER,
				'host' => self::DB_HOST,
				'name' => self::DB_NAME,
				'user' => self::DB_USER,
				'pass' => self::DB_PASS,
				'port' => self::DB_PORT
			);
		}

		// If we have not created PDO object yet, create it now
		if (!isset($this->pdo_object)) {
			$this->pdo_object = $this->createConnection(
				$this->config_params['driver'],
				$this->config_params['host'],
				$this->config_params['name'],
				$this->config_params['user'],
				$this->config_params['pass'],
				$this->config_params['port']
			);
		}

		return $this->pdo_object;
	}

	/**
	 * Initiates a transaction.
	 *
	 * @return boolean
	 */
	public function beginTransaction() {
		return $this->getConnection()->beginTransaction();
	}

	/**
	 * Commits a transaction.
	 *
	 * @return boolean
	 */
	public function endTransaction() {
		return $this->getConnection()->commit();
	}

	/**
	 * Rolls back a transaction.
	 *
	 * @return boolean
	 */
	public function cancelTransaction() {
		return $this->getConnection()->rollBack();
	}

	/**
	 * Retrieve information from the database, as an array.
	 *
	 * @param string $psTable - table name
	 * @param array $paWhere (optional) - associative array representing the WHERE clause filters
	 * @param integer $piLimit (optional) - maximum rows to be returned, used in LIMIT clause
	 * @param integer $piStart (optional) - row to be start return (indexed by zero), used in LIMIT clause (only $piLimit parameter is not null)
	 * @param array $paFields (optional) - table fields that will be selected
	 * @param array $paOrderby (optional) - array with order by clause
	 * @return array|boolean - associate array representing the fetched table(s) row(s), false on failure
	 */
	public function select($psTable, $paWhere=null, $piLimit=null, $piStart=null, $paFields=null, $paOrderby=null) {
		// Format fields to be selected
		if (count($paFields) > 0)
			$paFields = "`" . implode('`, `', $this->filter($psTable, $paFields)) . "`";
		else
			$paFields = "*";

		// Build SELECT statement
		$sSql = "SELECT $paFields FROM $psTable";

		if (count($paWhere) > 0) {
			// Append WHERE clause
			$sSql .= ' WHERE ';

			// Obtains fields to WHERE clause
			$aFields = $this->filter($psTable, $paWhere);

			// Add each condition
			foreach ($aFields as $key=>$val) {
				if ($key > 0)
					$sSql .= ' AND ';

				$sSql .= "$val = :$val";
			}
		}

		if (count($paOrderby) > 0) {
			// Append ORDER BY clause
			$sSql .= ' ORDER BY ';

			$bAddComma = false;

			// Add each expression from $paOrderby array
			foreach ($paOrderby as $column=>$order) {
				($bAddComma) ? $sSql.=', ' : $bAddComma=true;

				$sSql .= " $column ".strtoupper(trim($order));
			}
		}

		// Attempt bind query parameters and execute query
		try {
			$oPdo = $this->getConnection();

			// Append the LIMIT clause
			if (!is_null($piLimit) && is_numeric($piLimit)) {
	            // Format LIMIT clause depending of the driver
    	        switch ($oPdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        	    	case 'sqlsrv':
            		case 'mssql':
            			// MS SQL Server
            			$sSql = str_ireplace('SELECT', 'SELECT TOP '.$piLimit, $sSql);
            			break;
            		case 'oci':
	            		// Oracle
            			$iWherePos = strpos($sSql, 'WHERE');
            			$iOrderPos = strpos($sSql, 'ORDER');

            			if ($iWherePos!==false || $iOrderPos!==false) {
            				$sOrderClause = ($iOrderPos !== false) ? strstr($sSql, 'ORDER') : '';

            				if ($iWherePos !== false) {
	            				$sWhereCond = trim(substr(strstr($sSql, 'ORDER', true), $iWherePos))." AND ROWNUM<=$piLimit ";
    	        				$sSql = (($iOrderPos !== false) ? substr($sSql, 0, $iWherePos) : $sSql).$sWhereCond.$sOrderClause;
        	    			} else {
            					$sWhereCond = " WHERE ROWNUM<=$piLimit ";
            					$sSql = trim(substr($sSql, 0, $iOrderPos)).$sWhereCond.$sOrderClause;
            				}
            			} else {
	            			$sSql = "$sSql WHERE ROWNUM<=$piLimit ";
    	        		}
            			break;
            		default:
	            		// MySQL and others
    	        		$sSql .= ' LIMIT '.((!is_null($piStart) && is_numeric($piStart)) ? "$piStart, " : '').$piLimit;
        	    		break;
            	}
			}

			$oPdoStmt = $oPdo->prepare($sSql);

			if (count($paWhere) > 0) {
				// Bind values to parameters from array $paWhere
				foreach ($paWhere as $key=>$val) {
					$oPdoStmt->bindValue(':'.$key, $val);
				}
			}

			$oPdoStmt->execute();

			// Returns the results (first row only or all rows)
			return (!is_null($piLimit) && is_numeric($piLimit) && $piLimit==1) ? $oPdoStmt->fetch(PDO::FETCH_ASSOC) : $oPdoStmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

	/**
	 * Retrieve the first row from a select statement.
	 *
	 * @param string $psTable - table name
	 * @param array $paWhere (optional) - associative array representing the WHERE clause filters
	 * @param array $paFields (optional) - table fields that will be selected
	 * @param array $paOrderby (optional) - array with order by clause
	 * @return array|boolean - associate array representing the fetched table row, false on failure
	 */
	public function selectFirst($psTable, $paWhere=null, $paFields=null, $paOrderby=null) {
		return $this->select($psTable, $paWhere, 1, null, $paFields, $paOrderby);
	}

	/**
	 * Add only a new record to a specified table.
	 *
	 * @param string $psTable - table name
	 * @param array $psData - associative array representing the columns and their respective values
	 * @return integer|boolean - new ID (primary key) of the new record inserted, false on failure
	 */
	public function insert($psTable, $psData) {
		// Build SELECT INTO statement
		$sSql = "INSERT INTO $psTable";

		// Obtains fields to be updated
		$aFields = $this->filter($psTable, $psData);

		// Append fields and values into the query string
		$sSql .= ' (`' . implode('`, `', $aFields) . '`)';
		$sSql .= ' VALUES (:' . implode(', :', $aFields) . ')';

		// Attempt bind query parameters and execute query
		try {
			$oPdo = $this->getConnection();

			$oPdoStmt = $oPdo->prepare($sSql);

			// Bind values to parameters from array $psData
			foreach ($psData as $key=>$val) {
				$oPdoStmt->bindValue(':'.$key, $val);
			}

			$oPdoStmt->execute();

			// Returns new ID (primary key)
			return $oPdo->lastInsertId();
		} catch (PDOException $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

	/**
	 * Adds multiple records to a specified table.
	 *
	 * @param string $psTable - table name
	 * @param array $psData - associative array representing the columns and their respective values
	 * @return integer|boolean - number of rows affected, false on failure
	 */
	public function insertMultiple($psTable, $psData) {
		// Build SELECT INTO statement
		$sSql = "INSERT INTO $psTable";

		// Obtains fields to be updated
		$aFields = $this->filter($psTable, $psData[0]);

		// Append fields into the query string
		$sSql .= ' (`' . implode('`, `', $aFields) . '`) VALUES ';

		// Append each VALUES expressions from $paData array
		foreach ($psData as $row_index=>$row_values) {
			// Only append comma after that the first row has been added
			if ($row_index > 0)
				$sSql .= ', ';

			$sSql .= '(';
			$bAddComma = false;

			// Append the row values for each record
			foreach ($row_values as $value_index=>$value) {
				($bAddComma) ? $sSql.=', ' : $bAddComma=true;

				// Add the bind field name based on the row and column index
				$sSql .= ':'.$row_index.'_'.$value_index;
			}

			$sSql .= ')';
		}

		// Attempt bind query parameters and execute query using a transaction
		try {
			$this->beginTransaction();

			$oPdo = $this->getConnection();

			$oPdoStmt = $oPdo->prepare($sSql);

			// Bind values to parameters from array $psData
			foreach ($psData as $row_index=>$row_values) {
				foreach ($row_values as $value_index=>$value) {
					$oPdoStmt->bindValue(':'.$row_index.'_'.$value_index, $value);
				}
			}

			// Execute statement and commit transaction
			$oPdoStmt->execute();
			$this->endTransaction();

			// Returns number of rows affected
			return $oPdoStmt->rowCount();
		} catch (PDOException $e) {
			$this->writeError($e);
			$this->cancelTransaction();
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			$this->cancelTransaction();
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

 	/**
	 * Update existing record in specified table.
	 *
	 * @param string $psTable - table name
	 * @param array $psData - associative array representing the columns and their respective values to update
	 * @param array $paWhere (optional) - associative array representing the WHERE clause filters
	 * @return integer|boolean - number of rows updated, false on failure
	 */
	public function update($psTable, $psData, $paWhere=null) {
		// Build UPDATE statement
		$sSql = "UPDATE $psTable SET ";

		// Obtains fields to be updated
		$aFields = $this->filter($psTable, $psData);

		// Append set part into the query string
		foreach ($aFields as $key=>$val) {
			if ($key > 0)
				$sSql .= ', ';

			$sSql .= "`$val` = :param_$val";
		}

		if (count($paWhere) > 0) {
			// Append WHERE clause
			$sSql .= ' WHERE ';

			// Obtains fields to WHERE clause
			$aFields = $this->filter($psTable, $paWhere);

			// Add each condition
			foreach ($aFields as $key=>$val) {
				if ($key > 0)
					$sSql .= ' AND ';

				$sSql .= "$val = :where_$val";
			}
		}

		// Attempt bind query parameters and execute query
		try {
			$oPdo = $this->getConnection();

			$oPdoStmt = $oPdo->prepare($sSql);

			// Bind values to parameters from array $psData
			foreach ($psData as $key=>$val) {
				$oPdoStmt->bindValue(':param_'.$key, $val);
			}

			if (count($paWhere) > 0) {
				// Bind values to parameters from array $paWhere
				foreach ($paWhere as $key=>$val) {
					$oPdoStmt->bindValue(':where_'.$key, $val);
				}
			}

			$oPdoStmt->execute();

			// Returns number of rows updated
			return $oPdoStmt->rowCount();
		} catch (PDOException $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

	/**
	 * Update multiple records to a specified table.
	 *
	 * @param string $psTable - table name
	 * @param array $psData - associative array representing the columns and their respective values to update
	 * @param array $paWhere - associative array representing the WHERE clause filters
	 * @return integer|boolean - number of rows updated, false on failure
	 */
	public function updateMultiple($psTable, $psData, $paWhere) {
		try {
			$iNumRows = 0;

			$this->beginTransaction();

			// Execute each UPDATE statement
			foreach ($psData as $key=>$aData)
				$iNumRows += $this->update($psTable, $aData, $paWhere[$key]);

			$this->endTransaction();

			// Returns number of rows updated
			return $iNumRows;
		} catch (PDOException $e) {
			$this->writeError($e);
			$this->cancelTransaction();
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			$this->cancelTransaction();
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

	/**
	 * Removes rows from specified table.
	 *
	 * @param string $psTable - table name
	 * @param array $paWhere (optional) - associative array representing the WHERE clause filters
	 * @return integer|boolean - number of deleted rows, false on failure
	 */
	public function delete($psTable, $paWhere=null) {
		// Build DELETE statement
		$sSql = "DELETE FROM $psTable";

		if (count($paWhere) > 0) {
			// Append WHERE clause
			$sSql .= ' WHERE ';

			// Obtains fields to WHERE clause
			$aFields = $this->filter($psTable, $paWhere);

			// Add each condition
			foreach ($aFields as $key=>$val) {
				if ($key > 0)
					$sSql .= ' AND ';

				$sSql .= "$val = :$val";
			}
		}

		// Attempt bind query parameters and execute query
		try {
			$oPdo = $this->getConnection();

			$oPdoStmt = $oPdo->prepare($sSql);

			if (count($paWhere) > 0) {
				// Bind values to parameters from array $paWhere
				foreach ($paWhere as $key=>$val) {
					$oPdoStmt->bindValue(':'.$key, $val);
				}
			}

			$oPdoStmt->execute();

			// Returns number of deleted rows
			return $oPdoStmt->rowCount();
		} catch (PDOException $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

	/**
	 * Returns data from a customized SELECT statement.
	 *
	 * @param string $psQuery - SELECT statement to be executed
	 * @param array $psParams (optional) - associative array with bind parameters (representing the WHERE clause filters)
	 * @param boolean $biFirst (optional) - indicates if returns only first row
	 * @return array|boolean - associate array representing the fetched table(s) row(s), false on failure
	 */
	public function query($psQuery, $psParams=null, $biFirst=false) {
		// Attempt bind query parameters and execute query
		try {
			$oPdo = $this->getConnection();

			$oPdoStmt = $oPdo->prepare($psQuery);

			if (count($psParams) > 0) {		
				// Bind values to parameters from array $paParams
				foreach ((array)$psParams as $key=>$val) {
					$oPdoStmt->bindValue(':'.$key, $val);
				}
			}

			$oPdoStmt->execute();

			// Returns the results (first row only or all rows)
			return ($biFirst) ? $oPdoStmt->fetch(PDO::FETCH_ASSOC) : $oPdoStmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		} catch (Exception $e) {
			$this->writeError($e);
			throw new PDO_Wrapper_Exception($this->pdo_exception, null, $e);
		}
	}

	/**
	 * Returns first record from a customized SELECT statement.
	 *
	 * @param string $psQuery - SELECT statement to be executed
	 * @param array $psParams (optional) - associative array with bind parameters (representing the WHERE clause filters)
	 * @return array|boolean - associate array representing the fetched table row, false on failure
	 */
	public function queryFirst($psQuery, $psParams=array()) {
		return $this->query($psQuery, $psParams, true);
	}

	/**
	 * Obtains fields from table or values from intersection of table fields and filter array.
	 *
	 * @param string $psTable - table name
	 * @param array $aParams (optional) - associative array representing the columns and their respective values (statements: select, insert or update)
	 * @return array fields|values
	 */
	protected function filter($psTable, $aParams=null) {
		$oPdo = $this->getConnection();
		$sDriver = $oPdo->getAttribute(PDO::ATTR_DRIVER_NAME);

		if ($sDriver == 'sqlite') {
			$sSql = "PRAGMA table_info('$psTable');";
			$sKey = "name";
		} else if ($sDriver == 'mysql') {
			$sSql = "DESCRIBE $psTable;";
			$sKey = "Field";
		} else {
			$sSql = "SELECT column_name FROM information_schema.columns WHERE table_name = '$psTable';";
			$sKey = "column_name";
		}

		$aValues = $aFields = array();

		if (false !== ($aFieldsTable=$this->query($sSql))) {
			foreach ($aFieldsTable as $record)
				$aFields[] = $record[$sKey];

			if (count($aParams) > 0)
				$aValues = array_values(array_intersect($aFields, array_keys($aParams)));
		}

		return (count($aParams) > 0) ? $aValues : $aFields;
	}

	/**
	 * Returns primary key from specified table.
	 *
	 * @param $psTable - table name
	 * @return string $sPK - primary key column name
	 */
	protected function getTablePK($psTable) {
		$oPdo = $this->getConnection();

		switch ($oPdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
			case 'sqlite':
				$sql = "PRAGMA table_info('$psTable');";
				$sKeyColumn = "name";
				$aKeySearch = array('pk'=>'1');
				break;
			case 'mysql':
				$sSql = "DESCRIBE $psTable;";
				$sKeyColumn = "Field";
				$aKeySearch = array('Key'=>'PRI');
				break;
			default:
				$sSql = "SELECT * FROM information_schema.columns WHERE table_name='$psTable'";
				$sKeyColumn = "column_name";
				$aKeySearch = array('COLUMN_KEY'=>'PRI');
				break;
		}

		$sPK = "";

		if (false !== ($aFieldsTable=$this->query($sSql))) {
			foreach ($aFieldsTable as $record)  {
				if ($record[key($aKeySearch)] == $aKeySearch[key($aKeySearch)])
					$sPK = $record[$sKeyColumn];
			}
		}

		return $sPK;
	}

	/**
	 * Write error log into file and set pdo_exception class property.
	 *
	 * @param Exception $poError
	 */
	private function writeError($poError) {
		if (self::$LOG_ERRORS) {
			$sFilePath = self::$LOG_ERRORS_DIRECTORY.str_replace('@@__DATE__@@', date('Y-m-d'), self::$LOG_ERRORS_FILE);
			$sMessage = str_replace(
					array('@@__DATETIME__@@', '@@__FILE__@@', '@@__LINE__@@', '@@__MESSAGE__@@'),
					array(date('Y-m-d H:i:s'), $poError->getFile(), $poError->getLine(), $poError->getMessage()),
					file_get_contents('log_tmp.htm', true)
				);
			error_log($sMessage, 3, $sFilePath);
			echo $sMessage;
		}

		$this->pdo_exception = $poError;
	}

	/**
	 * Returns last message from Exception or PDOException caught.
	 *
	 * @return string
	 */
	public function getErrorMessage() {
		return ($this->pdo_exception) ? $this->pdo_exception->getMessage() : 'Database temporarily unavailable';
	}

	/**
	 * Returns current error from Exception or PDOException.
	 *
	 * @return Exception|PDOException
	 */
	public function getPDOException() {
		return $this->pdo_exception;
	}

	/**
	 * Validates if the driver is supported.
	 *
	 * @param string $psDriver - driver name
	 * @return boolean - true (driver is supported), false (driver is not supported)
	 */
	private function validateDriver($psDriver) {
		return (!in_array($psDriver, PDO::getAvailableDrivers())) ? false : true;
	}

	/**
	 * Release PDO DB connections.
	 *
	 */
	function __destruct() {
		unset($this->pdo_object);
	}
}

class PDO_Wrapper_Exception extends Exception {}

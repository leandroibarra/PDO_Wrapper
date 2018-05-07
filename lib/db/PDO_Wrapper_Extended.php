<?php
/**
 * PDO_Wrapper_Extended class.
 *
 * Extention for PDO Wrapper to manage queries like jQuery Datatable plugin.
 *
 * @author     Leandro Ibarra
 * @copyright  2015
 * @license    MIT License
 */
class PDO_Wrapper_Extended extends PDO_Wrapper {
	/**
	 * Number of records, before filtering.
	 *
	 * @var integer
	 */
	private $iTotalRecords;

	/**
	 * Number of records, after filtering.
	 *
	 * @var integer
	 */
	private $iTotalFilteredRecords;

	/**
	 * Parameters to store pages information.
	 *
	 * @var array
	 */
	public $aPages = array(
		'iFirst' => 1,
		'iPrev' => null,
		'iSelf' => null,
		'iNext' => null,
		'iLast' => null
	);

	/**
	 * Parameters to store information of query results.
	 *
	 * @var array
	 */
	public $aRecords = array(
		'iFrom' => null,
		'iTo' => null,
		'iAmount' => null,
		'iPerPage' => null,
		'iTotal' => null
	);

	/**
	 * PDO_Wrapper_Extended single instance (singleton).
	 *
	 * @var PDO_Wrapper_Extended
	 */
	protected static $instance = null;

	/**
	 * Get an instance of the PDO_Wrapper_Extended.
	 *
	 * @return PDO_Wrapper_Extended
	 */
	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new PDO_Wrapper_Extended();
		}
		return self::$instance;
	}

	/**
	 * Retrieve private property iTotalRecords.
	 *
	 * @return integer
	 */
	public function getTotalRecords() {
		return $this->iTotalRecords;
	}

	/**
	 * Retrieve private property iTotalFilteredRecords.
	 *
	 * @return integer
	 */
	public function getTotalFilteredRecords() {
		return $this->iTotalFilteredRecords;
	}

	/**
	 * Constructor. Make protected so only subclasses and self can create this object (singleton).
	 *
	 */
	protected function __construct() {
		parent::__construct();
	}

	/**
	 * Sets and validates pages, builds LIMIT clause, and executes query.
	 *
	 * @param string $psTable
	 * @param string $psTableAlias
	 * @param string $psQuery
	 * @param array $paFilters
	 * @param integer $piPage
	 * @param integer $piLimit
	 * @return array $aResult
	 */
	public function executeQuery($psTable, $psTableAlias, $psQuery, $paFilters, $piPage, $piLimit) {
		$sFieldsQuery = str_ireplace('SELECT', '', substr($psQuery, 0, strpos($psQuery, 'FROM')));

		$sDistinctField = 'id';

		if ($psTable == 'actions')
			$sDistinctField = 'id_user';

		$sTotalFilteredQuery = preg_replace(
			'/(GROUP BY.*)$/',
			'',
			str_ireplace(
				trim($sFieldsQuery),
				" COUNT(DISTINCT({$psTableAlias}.{$sDistinctField})) AS total ",
				$psQuery
			)
		);

		$aTotalFiltered = $this->query($sTotalFilteredQuery, $paFilters);
		$this->aRecords['iTotal'] = $this->iTotalFilteredRecords = $aTotalFiltered[0]['total'];

		$this->aPages['iSelf'] = intval($piPage);

		$iOffset = 0;
		$iRowCount = $piLimit;

		$this->aPages['iLast'] = ceil($this->iTotalFilteredRecords / $piLimit);

		if ($this->iTotalFilteredRecords>0 && $piPage>$this->aPages['iLast'])
			throw new Model_Exception(null, -0003);

		if ($piPage>0 && $piPage<=$this->aPages['iLast']) {
			$iOffset = ($piPage - 1) * $piLimit;

			if ($piPage > 1)
				$this->aPages['iPrev'] = $piPage - 1;

			if ($piPage < $this->aPages['iLast'])
				$this->aPages['iNext'] = $piPage + 1;
		}

		$this->aRecords['iPerPage'] = $iRowCount;

		// For compatibility with PostgreSQL, MySQL also supports the LIMIT row_count OFFSET offset syntax
		$psQuery .= " LIMIT {$iRowCount} OFFSET {$iOffset}";

		$aResult = $this->query($psQuery, $paFilters);

		$this->aRecords['iAmount'] = count($aResult);

		$this->aRecords['iFrom'] = ($this->aRecords['iAmount']>0) ? (($piPage - 1) * $iRowCount) + 1 : $this->aRecords['iAmount'];

		$this->aRecords['iTo'] = (($piPage - 1) * $iRowCount) + $this->aRecords['iAmount'];

		return $aResult;
	}

	/**
	 * Executes a search through SELECT statement with specific conditions.
	 *
	 * @param string $psQuery - SELECT statement to be executed
	 * @param string $psTable - table name
	 * @param array $paGet (reference - optional) - $_GET parameters to filter query
	 * @param integer $piCount (optional)
	 * @param integer $piOffset (optional)
	 * @return array|boolean - associate array representing the fetched table(s) row(s), false on failure
	 */
	public function rawSearch($psQuery, $psTable, &$paGet=null, $piCount='', $piOffset='') {
		$paGet = (count($paGet) > 0) ? $paGet : array();

		$aFieldsTable = $this->filter($psTable);

		$sFieldsQuery = str_ireplace('SELECT', '', substr($psQuery, 0, stripos($psQuery, 'FROM')));

		$aWhereAnd = $this->processWhere($psTable, $aFieldsTable, $paGet);
		$aOrderby = $this->processOrder($psTable, $aFieldsTable, $paGet);
		$aWhereOr = $this->processQuery($psTable, $aFieldsTable, $paGet);

		if ($aOrderby)
			$sOrder = implode(', ', $aOrderby);

		if ($aWhereAnd)
			$aWhere[] = '('. implode(' AND ', $aWhereAnd) .')';

		if ($aWhereOr)
			$aWhere[] = '('. implode(' OR ', $aWhereOr). ')';

		if ($aWhere)
			$aWhere = '('. implode(' AND ', $aWhere) .')';

		$sSql = $psQuery;

		if ($aWhere) {
			if (stripos($sSql, 'WHERE') !== false)
				$sSql = str_replace('WHERE', "WHERE {$aWhere} AND ", $sSql);
			else
				$sSql .= " WHERE {$aWhere} ";
		}

		if (!empty($sOrder))
			$sSql .= " ORDER BY {$sOrder} ";

		if (!is_null($piCount) && is_numeric($piCount)) {
			$piOffset = (int)$piOffset;
			$piCount = (int)$piCount;

			$iPge = $paGet['page'];
			$iIpp = $paGet['ipp'];

			$paGet['page'] = $piOffset;
			$paGet['ipp'] = $piCount;

			$sSql .= $this->paginator($psQuery, $sSql, $psTable, $sFieldsQuery, $paGet);

			$paGet['page'] = $iPge;
			$paGet['ipp'] = $iIpp;
		} else {
			$sSql .= $this->paginator($psQuery, $sSql, $psTable, $sFieldsQuery, $paGet);
		}

		$sSql .= ";";

		$aResult = $this->query($sSql, $paGet);

		return $aResult;
	}

	/**
	 * Returns conditions to WHERE clause that will be joined by AND operator.
	 *
	 * @param string $psTable - table name
	 * @param array $paFields - fields belonging to table
	 * @param array $paGet - $_GET array parameters reference or empty array to filter query
	 * @return array $aWhere - WHERE conditions
	 */
	protected function processWhere($psTable, $paFields, $paGet) {
		$aWhere = array();

		// Default WHERE conditions
		for ($i=0; $i<count($paFields); $i++) {
			if ($paGet['bSearchable_'.$i]=="true" && !empty($paGet['sSearch_'.$i]))
				$aWhere[] = " {$psTable}.`$paFields[$i]` LIKE '%{$paGet['sSearch_'.$i]}%'";
		}

		// Custom WHERE conditions
		foreach ($paGet as $k=>$v) {
			if (stripos($k,'f_')!==false && $v) {
				if (stripos($k,'f_lte_') !== false) { // Less than or equal
					$f = substr($k,6);
					$aWhere[] = " {$psTable}.`{$f}` <= '{$v}' ";
				} else if (stripos($k,'f_gte_') !== false) { // Greater than or equal
					$f = substr($k,6);
					$aWhere[] = " {$psTable}.`{$f}` >= '{$v}' ";
				} else if (stripos($k,'f_lt_') !== false) { // Less than
					$f = substr($k,5);
					$aWhere[] = " {$psTable}.`{$f}` < '{$v}' ";
				} else if (stripos($k,'f_gt_') !== false) { // Greater than
					$f = substr($k,5);
					$aWhere[] = " {$psTable}.`{$f}` > '{$v}' ";
				} else if (stripos($k,'f_not_') !== false) { // Not equal
					$f = substr($k,6);
					$aWhere[] = " {$psTable}.`{$f}` <> '{$v}' ";
				} else { // Equal
					$f = substr($k,2);
					$aWhere[] = " {$psTable}.`{$f}` = '{$v}' ";
				}
			} else if (stripos($k,'fe_')!==false && $v) {
				$f = substr($k,3);
				$aWhere[] = " `{$f}` = '{$v}' ";
			}
		}

		return $aWhere;
	}

	/**
	 * Returns conditions for ORDER BY clause.
	 *
	 * @param string $psTable - table name
	 * @param array $paFields - fields belonging to table
	 * @param array $paGet - $_GET array parameters reference or empty array to filter query
	 * @return array $aOrderby - ORDER BY conditions
	 */
	protected function processOrder($psTable, $paFields, $paGet) {
		$aOrderby = array();

		/*
		// Default ORDER BY conditions
		if (isset($paGet['iSortCol_0'])) {
			for ($i=0; $i<intval($paGet['iSortingCols']); $i++) {
				if ($paGet['bSortable_'.intval($paGet['iSortCol_'.$i])] == "true")
					$aOrderby[] = " {$psTable}.`{$paFields[intval($paGet['iSortCol_'.$i])]}` ".strtoupper($paGet['sSortDir_'.$i]);
			}
		}
		*/

		// Custom ORDER BY conditions
		foreach ($paGet as $k=>$v) {
			if (stripos($k,'o_')!==false && $v) {
				$f = substr($k,2);

				if (strpos($f, '.') !== false) {
					$aElements = explode('.', $f);

					$aOrderby[] = " `{$aElements[0]}`.`{$aElements[1]}` ".strtoupper($v)." ";
				} else {
					$aOrderby[] = " `{$f}` ".strtoupper($v)." ";
				}
			}
		}

		return $aOrderby;
	}

	/**
	 * Returns conditions to WHERE clause that will be joined by OR operator.
	 *
	 * @param string $psTable - table name
	 * @param array $paFields - fields belonging to table
	 * @param array $paGet - $_GET array parameters reference or empty array to filter query
	 * @return array $aWhere - WHERE conditions
	 */
	protected function processQuery($psTable, $paFields, $paGet) {
		$aWhere = array();

		// Default WHERE conditions
		if (isset($paGet['sSearch']) && !empty($paGet['sSearch'])) {
			for ($i=0; $i<count($paFields); $i++)
				$aWhere[] = " {$psTable}.`$paFields[$i]` LIKE '%{$paGet['sSearch']}%'";
		}

		// Custom WHERE conditions
		if ($paGet['q']) {
			// Search in all fields
			foreach ($paFields as $f) {
				$aWhere[] = " {$psTable}.`{$f}` LIKE '{$paGet['q']}%' ";
			}
		}

		// Search in specified fields
		foreach ($paGet as $k=>$v) {
			// Contain string
			if (stripos($k,'q_')!==false && $v) {
				$f = substr($k,2);

				if (strpos($f, '.') !== false) {
					$aElements = explode('.', $f);

					$aWhere[] = " `{$aElements[0]}`.`{$aElements[1]}` LIKE '%{$v}%' ";
				} else {
					$aWhere[] = " `{$f}` LIKE '%{$v}%' ";
				}
			}

			// End string
			if (stripos($k,'qe_')!==false && $v) {
				$f = substr($k,3);
				$aWhere[] = " `{$f}` LIKE '%{$v}' ";
			}

			// Begin string
			if (stripos($k,'qb_')!==false && $v) {
				$f = substr($k,3);
				$aWhere[] = " `{$f}` LIKE '{$v}%' ";
			}
		}

		return $aWhere;
	}

	/**
	 * Returns totals records (before filtering and after filtering) and LIMIT clause.
	 *
	 * @param string $psQuery - original query string
	 * @param string $psSql - query string
	 * @param string $psTable - table name
	 * @param string $psFields - fields from query string
	 * @param array $paGet - $_GET array parameters reference or empty array to filter query
	 * @return string $sLimit - LIMIT clause
	 */
	private function paginator($psQuery, $psSql, $psTable, $psFields, $paGet) {
		$sLimit = "";

		$sCountField = '*';

		preg_match('/GROUP BY (.*)/', $psSql, $aMatches);
		if ((bool) $aMatches)
			$sCountField = "DISTINCT({$aMatches[1]})";

		$sTotalFiltered = preg_replace(
			'/(GROUP BY.*)$/m',
			'',
			str_ireplace(
				trim($psFields),
				" COUNT({$sCountField}) AS total ",
				$psSql
			)
		);

		$aResultCount = $this->query($sTotalFiltered, $paGet);
		$this->iTotalFilteredRecords = $aResultCount[0]['total'];

		$sPK = $this->getTablePK($psTable);

		$sTotalRecords = "SELECT COUNT({$sPK}) AS total FROM {$psTable};";

		if ((bool) $paGet['total_filtered'])
			$sTotalRecords = preg_replace(
				'/(GROUP BY.*)$/m',
				'',
				str_ireplace(
					trim($psFields),
					" COUNT({$sCountField}) AS total ",
					$psQuery
				)
			);

		$aTotalCount = $this->query($sTotalRecords, $paGet);
		$this->iTotalRecords = $aTotalCount[0]['total'];

		if (isset($paGet['iDisplayStart']) && $paGet['iDisplayLength']!='-1') {
			$sLimit = " LIMIT {$paGet['iDisplayStart']}, {$paGet['iDisplayLength']}";
		}

		return $sLimit;
	}
}

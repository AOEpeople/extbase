<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Jochen Rau <jochen.rau@typoplanet.de>
*  All rights reserved
*
*  This class is a backport of the corresponding class of FLOW3.
*  All credits go to the v5 team.
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * A Storage backend
 *
 * @package Extbase
 * @subpackage Persistence\Storage
 * @version $Id: $
 */
class Tx_Extbase_Persistence_Storage_Typo3DbBackend implements Tx_Extbase_Persistence_Storage_BackendInterface, t3lib_Singleton {

	const OPERATOR_EQUAL_TO_NULL = 'operatorEqualToNull';
	const OPERATOR_NOT_EQUAL_TO_NULL = 'operatorNotEqualToNull';

	/**
	 * The TYPO3 database object
	 *
	 * @var t3lib_db
	 */
	protected $databaseHandle;

	/**
	 * @var Tx_Extbase_Persistence_DataMapper
	 */
	protected $dataMapper;

	/**
	 * The TYPO3 page select object. Used for language and workspace overlay
	 *
	 * @var t3lib_pageSelect
	 */
	protected $pageSelectObject;

	/**
	 * Constructs this Storage Backend instance
	 *
	 * @param t3lib_db $databaseHandle The database handle
	 */
	public function __construct($databaseHandle) {
		$this->databaseHandle = $databaseHandle;
	}

	/**

	 * Injects the DataMapper to map nodes to objects
	 *
	 * @param Tx_Extbase_Persistence_Mapper_DataMapper $dataMapper
	 * @return void
	 */
	public function injectDataMapper(Tx_Extbase_Persistence_Mapper_DataMapper $dataMapper) {
		$this->dataMapper = $dataMapper;
	}

	/**
	 * Adds a row to the storage
	 *
	 * @param string $tableName The database table name
	 * @param array $row The row to be inserted
	 * @param boolean $isRelation TRUE if we are currently inserting into a relation table, FALSE by default
	 * @return int The uid of the inserted row
	 */
	public function addRow($tableName, array $row, $isRelation = FALSE) {
		$fields = array();
		$values = array();
		$parameters = array();
		unset($row['uid']); // TODO Check if the offset exists
		foreach ($row as $columnName => $value) {
			$fields[] = $columnName;
			$values[] = '?';
			$parameters[] = $value;
		}

		$sqlString = 'INSERT INTO ' . $tableName . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
		$this->replacePlaceholders($sqlString, $parameters);
		$this->databaseHandle->sql_query($sqlString);
		$this->checkSqlErrors();
		$uid = $this->databaseHandle->sql_insert_id();
		if (!$isRelation) {
			$this->clearPageCache($tableName, $uid);
		}
		return $uid;
	}

	/**
	 * Updates a row in the storage
	 *
	 * @param string $tableName The database table name
	 * @param array $row The row to be updated
	 * @param boolean $isRelation TRUE if we are currently inserting into a relation table, FALSE by default
	 * @return void
	 */
	public function updateRow($tableName, array $row, $isRelation = FALSE) {
		if (!isset($row['uid'])) throw new InvalidArgumentException('The given row must contain a value for "uid".');
		$uid = (int)$row['uid'];
		unset($row['uid']);
		$fields = array();
		$parameters = array();
		foreach ($row as $columnName => $value) {
			$fields[] = $columnName . '=?';
			$parameters[] = $value;
		}
		$parameters[] = $uid;

		$sqlString = 'UPDATE ' . $tableName . ' SET ' . implode(', ', $fields) . ' WHERE uid=?';
		$this->replacePlaceholders($sqlString, $parameters);

		$returnValue = $this->databaseHandle->sql_query($sqlString);
		$this->checkSqlErrors();
		if (!$isRelation) {
			$this->clearPageCache($tableName, $uid);
		}
		return $returnValue;
	}

	/**
	 * Deletes a row in the storage
	 *
	 * @param string $tableName The database table name
	 * @param array $uid The uid of the row to be deleted
	 * @param boolean $isRelation TRUE if we are currently inserting into a relation table, FALSE by default
	 * @return void
	 */
	public function removeRow($tableName, $uid, $isRelation = FALSE) {
		$sqlString = 'DELETE FROM ' . $tableName . ' WHERE uid=?';
		$this->replacePlaceholders($sqlString, array((int)$uid));
		if (!$isRelation) {
			$this->clearPageCache($tableName, $uid, $isRelation);
		}
		$returnValue = $this->databaseHandle->sql_query($sqlString);
		$this->checkSqlErrors();
		return $returnValue;
	}

	/**
	 * Returns an array with tuples matching the query.
	 *
	 * @param Tx_Extbase_Persistence_QOM_QueryObjectModelInterface $query
	 * @return array The matching tuples
	 */
	public function getRows(Tx_Extbase_Persistence_QOM_QueryObjectModelInterface $query) {
		$statement = $this->parseQuery($query);
		$result = $this->databaseHandle->sql_query($statement);
		$this->checkSqlErrors();
		if ($result) {
			$tuples = $this->getRowsFromResult($query->getSource(), $result);
		}

		return $tuples;
	}

	/**
	 * Returns an array with tuples matching the query.
	 *
	 * @param Tx_Extbase_Persistence_QOM_QueryObjectModelInterface $query
	 * @return array The matching tuples
	 */
	public function parseQuery(Tx_Extbase_Persistence_QOM_QueryObjectModelInterface $query) {
		$statement = '';
		$parameters = array();
		$constraint = $query->getConstraint();
		if($constraint instanceof Tx_Extbase_Persistence_QOM_StatementInterface) {
			if ($constraint->getLanguage() === Tx_Extbase_Persistence_QOM_QueryObjectModelInterface::TYPO3_SQL_MYSQL) {
				$statement = $constraint->getStatement();
				$parameters= $query->getBoundVariableValues();
			} else {
				throw new Tx_Extbase_Persistence_Exception('Unsupported query language.', 1248701951);
			}
		} else {
			$sql = array();
			$sql['tables'] = array();
			$sql['fields'] = array();
			$sql['where'] = array();
			$sql['additionalWhereClause'] = array();
			$sql['orderings'] = array();
			$sql['limit'] = array();
			$tuples = array();

			$source = $query->getSource();
			$this->parseSource($query, $source, $sql, $parameters);

			$statement = 'SELECT ' . implode(',', $sql['fields']) . ' FROM ' . implode(' ', $sql['tables']);

			$this->parseConstraint($query->getConstraint(), $source, $sql, $parameters, $query->getBoundVariableValues());

			if (!empty($sql['where'])) {
				$statement .= ' WHERE ' . implode('', $sql['where']);
				if (!empty($sql['additionalWhereClause'])) {
					$statement .= ' AND ' . implode(' AND ', $sql['additionalWhereClause']);
				}
			} elseif (!empty($sql['additionalWhereClause'])) {
				$statement .= ' WHERE ' . implode(' AND ', $sql['additionalWhereClause']);
			}

			$this->parseOrderings($query->getOrderings(), $source, $sql, $parameters, $query->getBoundVariableValues());
			if (!empty($sql['orderings'])) {
				$statement .= ' ORDER BY ' . implode(', ', $sql['orderings']);
			}

			$this->parseLimitAndOffset($query->getLimit(), $query->getOffset(), $sql);
			if (!empty($sql['limit'])) {
				$statement .= ' LIMIT ' . $sql['limit'];
			}
		}

		$this->replacePlaceholders($statement, $parameters);

		return $statement;
	}

	/**
	 * Checks if a Value Object equal to the given Object exists in the data base
	 *
	 * @param array $properties The properties of the Value Object
	 * @param Tx_Extbase_Persistence_Mapper_DataMap $dataMap The Data Map
	 * @return array The matching tuples
	 */
	public function hasValueObject(array $properties, Tx_Extbase_Persistence_Mapper_DataMap $dataMap) {
		$fields = array();
		$parameters = array();
		foreach ($properties as $propertyName => $propertyValue) {
			if ($dataMap->isPersistableProperty($propertyName) && ($propertyName !== 'uid')) {
				$fields[] = $dataMap->getColumnMap($propertyName)->getColumnName() . '=?';
				$parameters[] = $dataMap->convertPropertyValueToFieldValue($propertyValue);
			}
		}

		$sqlString = 'SELECT * FROM ' . $dataMap->getTableName() .  ' WHERE ' . implode(' AND ', $fields);
		$this->replacePlaceholders($sqlString, $parameters);
		$res = $this->databaseHandle->sql_query($sqlString);
		$this->checkSqlErrors();
		$row = $this->databaseHandle->sql_fetch_assoc($res);
		if ($row !== FALSE) {
			return $row['uid'];
		} else {
			return FALSE;
		}
	}

	/**
	 * Transforms a Query Source into SQL and parameter arrays
	 *
	 * @param Tx_Extbase_Persistence_QOM_QueryObjectModel $query
	 * @param Tx_Extbase_Persistence_QOM_SourceInterface $source The source
	 * @param array &$sql
	 * @param array &$parameters
	 * @return void
	 */
	protected function parseSource(Tx_Extbase_Persistence_QOM_QueryObjectModelInterface $query, Tx_Extbase_Persistence_QOM_SourceInterface $source, array &$sql, array &$parameters) {
		if ($source instanceof Tx_Extbase_Persistence_QOM_SelectorInterface) {
			$tableName = $source->getSelectorName();
			$sql['fields'][] = $tableName . '.*';
			$sql['tables'][] = $tableName;
			$querySettings = $query->getQuerySettings();
			if ($querySettings instanceof Tx_Extbase_Persistence_Typo3QuerySettingsInterface) {
				if ($querySettings->getRespectEnableFields()) {
					$this->addEnableFieldsStatement($tableName, $sql);
				}
				if ($querySettings->getRespectStoragePage()) {
					$this->addPageIdStatement($tableName, $sql);
				}
			}
		} elseif ($source instanceof Tx_Extbase_Persistence_QOM_JoinInterface) {
			$this->parseJoin($query, $source, $sql, $parameters);
		}
	}

	/**
	 * Transforms a Join into SQL and parameter arrays
	 *
	 * @param Tx_Extbase_Persistence_QOM_QueryObjectModel $query
	 * @param Tx_Extbase_Persistence_QOM_JoinInterface $join
	 * @param array &$sql
	 * @param array &$parameters
	 * @return void
	 */
	protected function parseJoin(Tx_Extbase_Persistence_QOM_QueryObjectModelInterface $query, Tx_Extbase_Persistence_QOM_JoinInterface $join, array &$sql, array &$parameters) {
		$leftSource = $join->getLeft();
		$leftTableName = $leftSource->getSelectorName();
		$rightSource = $join->getRight();
		$rightTableName = $rightSource->getSelectorName();

		$sql['fields'][] = $leftTableName . '.*';
		$sql['fields'][] = $rightTableName . '.*';

		// TODO Implement support for different join types and nested joins
		$sql['tables'][] = $leftTableName . ' LEFT JOIN ' . $rightTableName;

		$joinCondition = $join->getJoinCondition();
		// TODO Check the parsing of the join
		if ($joinCondition instanceof Tx_Extbase_Persistence_QOM_EquiJoinCondition) {
			$column1Name = $this->dataMapper->convertPropertyNameToColumnName($joinCondition->getProperty1Name(), $leftSource->getNodeTypeName());
			$column2Name = $this->dataMapper->convertPropertyNameToColumnName($joinCondition->getProperty2Name(), $rightSource->getNodeTypeName());
			$sql['tables'][] = 'ON ' . $joinCondition->getSelector1Name() . '.' . $column1Name . ' = ' . $joinCondition->getSelector2Name() . '.' . $column2Name;
		}
		// TODO Implement childtableWhere

		$querySettings = $query->getQuerySettings();
		if ($querySettings instanceof Tx_Extbase_Persistence_Typo3QuerySettingsInterface) {
			if ($querySettings->getRespectEnableFields()) {
				$this->addEnableFieldsStatement($leftTableName, $sql);
				$this->addEnableFieldsStatement($rightTableName, $sql);
			}
			if ($querySettings->getRespectStoragePage()) {
				$this->addPageIdStatement($leftTableName, $sql);
				$this->addPageIdStatement($rightTableName, $sql);
			}
		}
	}

	/**
	 * Transforms a constraint into SQL and parameter arrays
	 *
	 * @param Tx_Extbase_Persistence_QOM_ConstraintInterface $constraint
	 * @param Tx_Extbase_Persistence_QOM_SourceInterface $source The source
	 * @param array &$sql
	 * @param array &$parameters
	 * @param array $boundVariableValues
	 * @return void
	 */
	protected function parseConstraint(Tx_Extbase_Persistence_QOM_ConstraintInterface $constraint = NULL, Tx_Extbase_Persistence_QOM_SourceInterface $source, array &$sql, array &$parameters, array $boundVariableValues) {
		if ($constraint instanceof Tx_Extbase_Persistence_QOM_AndInterface) {
			$sql['where'][] = '(';
			$this->parseConstraint($constraint->getConstraint1(), $source, $sql, $parameters, $boundVariableValues);
			$sql['where'][] = ' AND ';
			$this->parseConstraint($constraint->getConstraint2(), $source, $sql, $parameters, $boundVariableValues);
			$sql['where'][] = ')';
		} elseif ($constraint instanceof Tx_Extbase_Persistence_QOM_OrInterface) {
			$sql['where'][] = '(';
			$this->parseConstraint($constraint->getConstraint1(), $source, $sql, $parameters, $boundVariableValues);
			$sql['where'][] = ' OR ';
			$this->parseConstraint($constraint->getConstraint2(), $source, $sql, $parameters, $boundVariableValues);
			$sql['where'][] = ')';
		} elseif ($constraint instanceof Tx_Extbase_Persistence_QOM_NotInterface) {
			$sql['where'][] = 'NOT (';
			$this->parseConstraint($constraint->getConstraint(), $source, $sql, $parameters, $boundVariableValues);
			$sql['where'][] = ')';
		} elseif ($constraint instanceof Tx_Extbase_Persistence_QOM_ComparisonInterface) {
			$this->parseComparison($constraint, $source, $sql, $parameters, $boundVariableValues);
		} elseif ($constraint instanceof Tx_Extbase_Persistence_QOM_RelatedInterface) {
			$this->parseRelated($constraint, $sql, $parameters, $boundVariableValues);
		}
	}

	/**
	 * Parse a Comparison into SQL and parameter arrays.
	 *
	 * @param Tx_Extbase_Persistence_QOM_ComparisonInterface $comparison The comparison to parse
	 * @param Tx_Extbase_Persistence_QOM_SourceInterface $source The source
	 * @param array &$sql SQL query parts to add to
	 * @param array &$parameters Parameters to bind to the SQL
	 * @param array $boundVariableValues The bound variables in the query and their values
	 * @return void
	 */
	protected function parseComparison(Tx_Extbase_Persistence_QOM_ComparisonInterface $comparison, Tx_Extbase_Persistence_QOM_SourceInterface $source, array &$sql, array &$parameters, array $boundVariableValues) {
		if (!($comparison->getOperand2() instanceof Tx_Extbase_Persistence_QOM_BindVariableValueInterface)) throw new Tx_Extbase_Persistence_Exception('Type of operand is not supported', 1247581135);

		$value = $boundVariableValues[$comparison->getOperand2()->getBindVariableName()];
		$operator = $comparison->getOperator();
		if ($value === NULL) {
			if ($operator === Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_EQUAL_TO) {
				$operator = self::OPERATOR_EQUAL_TO_NULL;
			} elseif ($operator === Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_NOT_EQUAL_TO) {
				$operator = self::OPERATOR_NOT_EQUAL_TO_NULL;
			} else {
				// TODO Throw exception
			}
		}
		$parameters[] = $value;

		$this->parseDynamicOperand($comparison->getOperand1(), $operator, $source, $sql, $parameters);
	}

	/**
	 * Parse a DynamicOperand into SQL and parameter arrays.
	 *
	 * @param Tx_Extbase_Persistence_QOM_DynamicOperandInterface $operand
	 * @param string $operator One of the JCR_OPERATOR_* constants
	 * @param Tx_Extbase_Persistence_QOM_SourceInterface $source The source
	 * @param array &$sql SQL query parts to add to
	 * @param array &$parameters
	 * @param string $valueFunction an aoptional SQL function to apply to the operand value
	 * @return void
	 */
	protected function parseDynamicOperand(Tx_Extbase_Persistence_QOM_DynamicOperandInterface $operand, $operator, Tx_Extbase_Persistence_QOM_SourceInterface $source, array &$sql, array &$parameters, $valueFunction = NULL) {
		if ($operand instanceof Tx_Extbase_Persistence_QOM_LowerCaseInterface) {
			$this->parseDynamicOperand($operand->getOperand(), $operator, $sql, $parameters, 'LOWER');
		} elseif ($operand instanceof Tx_Extbase_Persistence_QOM_UpperCaseInterface) {
			$this->parseDynamicOperand($operand->getOperand(), $operator, $sql, $parameters, 'UPPER');
		} elseif ($operand instanceof Tx_Extbase_Persistence_QOM_PropertyValueInterface) {
			$tableName = $operand->getSelectorName();
			// FIXME Discuss the translation from propertyName to columnName
			if ($source instanceof Tx_Extbase_Persistence_QOM_SelectorInterface) {
				 $className = $source->getNodeTypeName();
			} else {
				$className = '';
			}
			$columnName = $this->dataMapper->convertPropertyNameToColumnName($operand->getPropertyName(), $className);
			$operator = $this->resolveOperator($operator);

			if ($valueFunction === NULL) {
				$constraintSQL .= (!empty($tableName) ? $tableName . '.' : '') . $columnName .  ' ' . $operator . ' ?';
			} else {
				$constraintSQL .= $valueFunction . '(' . (!empty($tableName) ? $tableName . '.' : '') . $columnName .  ' ' . $operator . ' ?';
			}

			$sql['where'][] = $constraintSQL;
		}
	}

	/**
	 * Returns the SQL operator for the given JCR operator type.
	 *
	 * @param string $operator One of the JCR_OPERATOR_* constants
	 * @return string an SQL operator
	 */
	protected function resolveOperator($operator) {
		switch ($operator) {
			case self::OPERATOR_EQUAL_TO_NULL:
				$operator = 'IS';
				break;
			case self::OPERATOR_NOT_EQUAL_TO_NULL:
				$operator = 'IS NOT';
				break;
			case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_EQUAL_TO:
				$operator = '=';
				break;
			case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_NOT_EQUAL_TO:
				$operator = '!=';
				break;
			case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_LESS_THAN:
				$operator = '<';
				break;
			case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_LESS_THAN_OR_EQUAL_TO:
				$operator = '<=';
				break;
			case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_GREATER_THAN:
				$operator = '>';
				break;
			case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_GREATER_THAN_OR_EQUAL_TO:
				$operator = '>=';
				break;
			case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_OPERATOR_LIKE:
				$operator = 'LIKE';
				break;
			default:
				throw new Tx_Extbase_Persistence_Exception('Unsupported operator encountered.', 1242816073);
		}

		return $operator;
	}

	/**
	 * Replace query placeholders in a query part by the given
	 * parameters.
	 *
	 * @param string $queryPart The query part with placeholders
	 * @param array $parameters The parameters
	 * @return string The query part with replaced placeholders
	 */
	protected function replacePlaceholders(&$sqlString, array $parameters) {
		if (substr_count($sqlString, '?') !== count($parameters)) throw new Tx_Extbase_Persistence_Exception('The number of question marks to replace must be equal to the number of parameters.', 1242816074);
		$offset = 0;
		foreach ($parameters as $parameter) {
			$markPosition = strpos($sqlString, '?', $offset);
			if ($markPosition !== FALSE) {
				if ($parameter === NULL) {
					$parameter = 'NULL';
				} else {
					$parameter = "'" . $parameter . "'"; // TODO Discuss: Do we need quotation?
				}
				$sqlString = substr($sqlString, 0, $markPosition) . $parameter . substr($sqlString, $markPosition + 1);
			}
			$offset = $markPosition + strlen($parameter);
		}
	}

	/**
	 * Builds the enable fields statement
	 *
	 * @param string $tableName The database table name
	 * @param array &$sql The query parts
	 * @return void
	 */
	protected function addEnableFieldsStatement($tableName, array &$sql) {
		if (is_array($GLOBALS['TCA'][$tableName]['ctrl'])) {
			if (TYPO3_MODE === 'FE') {
				$statement = substr($GLOBALS['TSFE']->sys_page->enableFields($tableName), 5);
			} else { // TYPO3_MODE === 'BE'
				$statement = substr(t3lib_BEfunc::BEenableFields($tableName), 5);
			}
			if(!empty($statement)) {
				$sql['additionalWhereClause'][] = $statement;
			}
		}
	}

	/**
	 * Builds the page ID checking statement
	 *
	 * @param string $tableName The database table name
	 * @param array &$sql The query parts
	 * @return void
	 */
	protected function addPageIdStatement($tableName, array &$sql) {
		// TODO We have to call the appropriate API method if we are in TYPO3BE mode
		if (is_array($GLOBALS['TCA'][$tableName]['ctrl'])) {
			$extbaseFrameworkConfiguration = Tx_Extbase_Dispatcher::getExtbaseFrameworkConfiguration();
			$sql['additionalWhereClause'][] = $tableName . '.pid IN (' . implode(', ', t3lib_div::intExplode(',', $extbaseFrameworkConfiguration['persistence']['storagePid'])) . ')';
		}
	}

	/**
	 * Transforms orderings into SQL
	 *
	 * @param array $orderings
	 * @param array &$sql
	 * @param array &$parameters
	 * @param array $boundVariableValues
	 * @return void
	 */
	protected function parseOrderings(array $orderings, Tx_Extbase_Persistence_QOM_SourceInterface $source, array &$sql, array &$parameters, array $boundVariableValues) {
		foreach ($orderings as $ordering) {
			$operand = $ordering->getOperand();
			$order = $ordering->getOrder();
			if ($operand instanceof Tx_Extbase_Persistence_QOM_PropertyValue) {
				switch ($order) {
					case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_ORDER_ASCENDING:
						$order = 'ASC';
						break;
					case Tx_Extbase_Persistence_QOM_QueryObjectModelConstantsInterface::JCR_ORDER_DESCENDING:
						$order = 'DESC';
						break;
					default:
						throw new Tx_Extbase_Persistence_Exception('Unsupported order encountered.', 1242816074);
				}
				$columnName = $this->dataMapper->convertPropertyNameToColumnName($ordering->getOperand()->getPropertyName(), $source->getNodeTypeName());
				$sql['orderings'][] = $columnName . ' ' . $order;
			}
		}
	}

	/**
	 * Transforms limit and offset into SQL
	 *
	 * @param int $limit
	 * @param int $offset
	 * @param array &$sql
	 * @return void
	 */
	protected function parseLimitAndOffset($limit, $offset, array &$sql) {
		if ($limit !== NULL && $offset !== NULL) {
			$sql['limit'] = $offset . ', ' . $limit;
		} elseif ($limit !== NULL) {
			$sql['limit'] = $limit;
		}
	}

	/**
	 * Transforms a Resource from a database query to an array of rows. Performs the language and
	 * workspace overlay before.
	 *
	 * @param Tx_Extbase_Persistence_QOM_SourceInterface $source The source (selector od join)
	 *
	 * @return array The result as an array of rows (tuples)
	 */
	protected function getRowsFromResult(Tx_Extbase_Persistence_QOM_SourceInterface $source, $res) {
		$rows = array();
		while ($row = $this->databaseHandle->sql_fetch_assoc($res)) {
			if	($source instanceof Tx_Extbase_Persistence_QOM_SelectorInterface) {
			$row = $this->doLanguageAndWorkspaceOverlay($source->getSelectorName(), $row);
			}
			if (is_array($row)) {
				// TODO Check if this is necessary, maybe the last line is enough
				$arrayKeys = range(0,count($row));
				array_fill_keys($arrayKeys, $row);
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Performs workspace and language overlay on the given row array. The language and workspace id is automatically
	 * detected (depending on FE or BE context). You can also explicitly set the language/workspace id.
	 *
	 * @param Tx_Extbase_Persistence_Mapper_DataMap $dataMap
	 * @param array $row The row array (as reference)
	 * @param string $languageUid The language id
	 * @param string $workspaceUidUid The workspace id
	 * @return void
	 */
	protected function doLanguageAndWorkspaceOverlay($tableName, array $row, $languageUid = NULL, $workspaceUid = NULL) {
		if (!($this->pageSelectObject instanceof t3lib_pageSelect)) {
			if (TYPO3_MODE == 'FE') {
				if (is_object($GLOBALS['TSFE'])) {
					$this->pageSelectObject = $GLOBALS['TSFE']->sys_page;
					if ($languageUid === NULL) {
						$languageUid = $GLOBALS['TSFE']->sys_language_content;
					}
				} else {
					require_once(PATH_t3lib . 'class.t3lib_page.php');
					$this->pageSelectObject = t3lib_div::makeInstance('t3lib_pageSelect');
					if ($languageUid === NULL) {
						$languageUid = intval(t3lib_div::_GP('L'));
					}
				}
				if ($workspaceUid !== NULL) {
					$this->pageSelectObject->versioningWorkspaceId = $workspaceUid;
				}
			} else {
				require_once(PATH_t3lib . 'class.t3lib_page.php');
				$this->pageSelectObject = t3lib_div::makeInstance( 't3lib_pageSelect' );
				//$this->pageSelectObject->versioningPreview =  TRUE;
				if ($workspaceUid === NULL) {
					$workspaceUid = $GLOBALS['BE_USER']->workspace;
				}
				$this->pageSelectObject->versioningWorkspaceId = $workspaceUid;
			}
		}

		$this->pageSelectObject->versionOL($tableName, $row, TRUE);
		$row = $this->pageSelectObject->getRecordOverlay($tableName, $row, $languageUid, ''); //'hideNonTranslated'
		// TODO Skip if empty languageoverlay (languagevisibility)
		return $row;
	}

	/**
	 * Checks if there are SQL errors in the last query, and if yes, throw an exception.
	 *
	 * @return void
	 * @throws Tx_Extbase_Persistence_Storage_Exception_SqlError
	 */
	protected function checkSqlErrors() {
		$error = $this->databaseHandle->sql_error();
		if ($error !== '') {
			throw new Tx_Extbase_Persistence_Storage_Exception_SqlError($error, 1247602160);
		}
	}

	/**
	 * Clear the TYPO3 page cache for the given record.
	 * Much of this functionality is taken from t3lib_tcemain::clear_cache() which unfortunately only works with logged-in BE user.
	 *
	 * @param $tableName Table name of the record
	 * @param $uid UID of the record
	 * @return void
	 */
	protected function clearPageCache($tableName, $uid) {
		$extbaseSettings = Tx_Extbase_Dispatcher::getExtbaseFrameworkConfiguration();
		if (isset($extbaseSettings['persistence']['enableAutomaticCacheClearing']) && $extbaseSettings['persistence']['enableAutomaticCacheClearing'] === '1') {
		} else {
			// if disabled, return
			return;
		}

		$pageCache = $GLOBALS['typo3CacheManager']->getCache('cache_pages');
		$pageSectionCache = $GLOBALS['typo3CacheManager']->getCache('cache_pagesection');

		$result = $this->databaseHandle->exec_SELECTquery('pid', $tableName, 'uid='.intval($uid));

		$pageIdsToClear = array();
		if ($row = $this->databaseHandle->sql_fetch_assoc($result))	{
			$storagePage = $row['pid'];
			$pageIdsToClear[] = $storagePage;
		}
		if (!$storagePage) {
			return;
		}

		$pageTSConfig = t3lib_BEfunc::getPagesTSconfig($storagePage);
		if (isset($pageTSConfig['TCEMAIN.']['clearCacheCmd']))	{
			$clearCacheCommands = t3lib_div::trimExplode(',',strtolower($pageTSConfig['TCEMAIN.']['clearCacheCmd']),1);
			$clearCacheCommands = array_unique($clearCacheCommands);
			foreach ($clearCacheCommands as $clearCacheCommand)	{
				if (t3lib_div::testInt($clearCacheCommand))	{
					$pageIdsToClear[] = $clearCacheCommand;
				}
			}
		}

		foreach ($pageIdsToClear as $pageIdToClear) {
			$pageCache->flushByTag('pageId_' . $pageIdToClear);
			$pageSectionCache->flushByTag('pageId_' . $pageIdToClear);
		}
	}
}

?>
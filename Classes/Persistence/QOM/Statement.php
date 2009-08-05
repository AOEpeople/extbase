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
 * A statement acting as a constraint.
 *
 * @package Extbase
 * @subpackage Persistence\QOM
 * @version $Id: Selector.php 2011 2009-03-18 14:22:24Z k-fish $
 * @scope prototype
 */
class Tx_Extbase_Persistence_QOM_Statement implements Tx_Extbase_Persistence_QOM_StatementInterface {

	/**
	 * @var string
	 */
	protected $statement;

	/**
	 * @var string
	 */
	protected $language;

	/**
	 * Constructs the Statement instance
	 *
	 * @param string $statement
	 * @param string $selectorName
	 */
	public function __construct($statement, $language) {
		$this->statement = $statement;
		$this->language = $language;
	}

	/**
	 * Gets the statement.
	 *
	 * @return the statemenbt; non-null
	 */
	public function getStatement() {
		return $this->statement;
	}

	/**
	 * Gets the language.
	 *
	 * @return string The language; one of Tx_Extbase_Persistence_QOM_QueryObjectModelInterface::JCR_* or Tx_Extbase_Persistence_QOM_QueryObjectModelInterface::TYPO3_*
	 */
	public function getLanguage() {
		return $this->language;
	}
	
	/**
	 * Does nothing
	 *
	 * @param array &$boundVariables
	 * @return void
	 */
	public function collectBoundVariableNames(&$boundVariables) {
		// does nothing
	}

}

?>
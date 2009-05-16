<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Jochen Rau <jochen.rau@typoplanet.de>
*  All rights reserved
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
 * Testcase for the Generic Object Validator
 *
 * @package TYPO3
 * @subpackage extbase
 * @version $Id$
 */
class Tx_Extbase_Validation_Validator_GenericObjectValidator_testcase extends Tx_Extbase_Base_testcase {

	/**
	 * @test
	 */
	public function isValidReturnsFalseIfTheValueIsNoObject() {
		$validator = $this->getMock('Tx_Extbase_Validation_Validator_GenericObjectValidator', array('addError'), array(), '', FALSE);
		$this->assertFalse($validator->isValid('foo'));
	}

	/**
	 * @test
	 */
	public function isValidChecksAllPropertiesForWhichAPropertyValidatorExists() {
		$mockPropertyValidators = array('foo' => 'validator', 'bar' => 'validator');
		$mockObject = new stdClass;

		$validator = $this->getMock($this->buildAccessibleProxy('Tx_Extbase_Validation_Validator_GenericObjectValidator'), array('addError', 'isPropertyValid'), array(), '', FALSE);
		$validator->_set('propertyValidators', $mockPropertyValidators);

		$validator->expects($this->at(0))->method('isPropertyValid')->with($mockObject, 'foo')->will($this->returnValue(TRUE));
		$validator->expects($this->at(1))->method('isPropertyValid')->with($mockObject, 'bar')->will($this->returnValue(TRUE));

		$validator->isValid($mockObject);
	}
}

?>
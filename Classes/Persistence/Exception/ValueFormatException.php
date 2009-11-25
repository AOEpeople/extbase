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
 * Exception thrown when an attempt is made to assign a value to a property
 * that has an invalid format, given the type of the property. Also thrown
 * if an attempt is made to read the value of a property using a type-specific
 * read method of a type into which it is not convertible.
 *
 * @package Extbase
 * @subpackage Persistence\Exception
 * @version $Id$
 */
class Tx_Extbase_Persistence_Exception_ValueFormatException extends Tx_Extbase_Persistence_Exception {
}

?>
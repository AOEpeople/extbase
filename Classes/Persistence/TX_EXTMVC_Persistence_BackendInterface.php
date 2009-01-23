<?php
declare(ENCODING = 'utf-8');

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * A persistence backend interface
 *
 * @package FLOW3
 * @subpackage Persistence
 * @version $Id:$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
interface BackendInterface {

	/**
	 * Initializes the backend
	 *
	 * @param array $classSchemata the class schemata the backend will be handling
	 * @return void
	 */
	public function initialize(array $classSchemata);

	/**
	 * Sets the aggregate root objects
	 *
	 * @param SplObjectStorage $objects
	 * @return void
	 */
	public function setAggregateRootObjects(_SplObjectStorage $objects);

	/**
	 * Sets the deleted objects
	 *
	 * @param SplObjectStorage $objects
	 * @return void
	 */
	public function setDeletedObjects(_SplObjectStorage $objects);

	/**
	 * Commits the current persistence session
	 *
	 * @return void
	 */
	public function commit();

}
?>
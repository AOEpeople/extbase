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

require_once(t3lib_extMgm::extPath('extmvc') . 'Classes/TX_EXTMVC_ViewInterface.php');

/**
 * An abstract View
 *
 * @version $Id:$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
abstract class TX_EXTMVC_View_AbstractView implements TX_EXTMVC_ViewInterface {

	/**
	 * @var TX_EXTMVC_Request
	 */
	protected $request;

	/**
	 * @var array of TX_EXTMVC_View_Helper_HelperInterface
	 */
	protected $viewHelpers;

	/**
	 * @var array
	 */
	protected $model;

	/**
	 * Constructs the view.
	 */
	public function __construct() {
	}

	/**
	 * Initializes the view after all dependencies have been injected
	 *
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function initializeObject() {
		$this->initializeView();
	}

	/**
	 * Sets the current request
	 *
	 * @param TX_EXTMVC_Request $request
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function setRequest(TX_EXTMVC_Request $request) {
		$this->request = $request;
	}

	/**
	 * Returns an View Helper instance.
	 * View Helpers must implement the interface TX_EXTMVC_View_Helper_HelperInterface
	 *
	 * @param string $viewHelperClassName the full name of the View Helper Class including 
	 * @return TX_EXTMVC_View_Helper_HelperInterface The View Helper instance
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function getViewHelper($viewHelperClassName) {
		if (!isset($this->viewHelpers[$viewHelperClassName])) {
			$viewHelper = $this->objectManager->getObject($viewHelperClassName);
			if (!$viewHelper instanceof TX_EXTMVC_View_Helper_HelperInterface) {
				throw new TX_EXTMVC_Exception_InvalidViewHelper('View Helpers must implement interface "TX_EXTMVC_View_Helper_HelperInterface"', 1222895456);
			}
			$viewHelper->setRequest($this->request);
			$this->viewHelpers[$viewHelperClassName] = $viewHelper;
		}
		return $this->viewHelpers[$viewHelperClassName];
	}

	/**
	 * Initializes this view.
	 *
	 * Override this method for initializing your concrete view implementation.
	 *
	 * @return void
	 */
	protected function initializeView() {
	}

	/**
	 * Assigns domain models (single objects or aggregates) or values to the view
	 *
	 * @param string $valueName The name of the value
	 * @param mixed $value the value to assign
	 * @return void
	 */
	public function assign($name, $value) {
		$this->model[$name] = $value;
	}

}

?>

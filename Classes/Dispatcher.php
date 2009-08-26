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
 * Creates a request an dispatches it to the controller which was specified
 * by TS Setup, Flexform and returns the content to the v4 framework.
 *
 * This class is the main entry point for extbase extensions in the frontend.
 *
 * @package Extbase
 * @version $ID:$
 */
class Tx_Extbase_Dispatcher {

	/**
	 * @var Tx_Extbase_Utility_ClassLoader
	 */
	protected $classLoader;
	
	/**
	 * @var Tx_Extbase_Configuration_Manager
	 */
	protected $configurationManager;

	/**
	 * @var Tx_Extbase_Reflection_Service
	 */
	protected static $reflectionService;

	/**
	 * @var Tx_Extbase_Persistence_Manager
	 */
	private static $persistenceManager;

	/**
	 * The configuration for the Extbase framework
	 * @var array
	 */
	private static $extbaseFrameworkConfiguration;


	/**
	 * Constructs this Dispatcher and registers the autoloader
	 */
	public function __construct() {
		$this->initializeClassLoader();
	}

	/**
	 * Creates a request an dispatches it to a controller.
	 *
	 * @param string $content The content
	 * @param array|NULL $configuration The TS configuration array
	 * @return string $content The processed content
	 */
	public function dispatch($content, $configuration) {
		// FIXME Remove the next lines. These are only there to generate the ext_autoload.php file
		//$extutil = new Tx_Extbase_Utility_Extension;
		//$extutil->createAutoloadRegistryForExtension('extbase', t3lib_extMgm::extPath('extbase'));
		//$extutil->createAutoloadRegistryForExtension('fluid', t3lib_extMgm::extPath('fluid'));

		if (!is_array($configuration)) {
			t3lib_div::sysLog('Extbase was not able to dispatch the request. No configuration.', 'extbase', t3lib_div::SYSLOG_SEVERITY_ERROR);
			return $content;
		}
		$this->initializeConfigurationManagerAndFrameworkConfiguration($configuration);

		$requestBuilder = t3lib_div::makeInstance('Tx_Extbase_MVC_Web_RequestBuilder');
		$request = $requestBuilder->initialize($configuration);
		$request = $requestBuilder->build();
		$response = t3lib_div::makeInstance('Tx_Extbase_MVC_Web_Response');

		$persistenceManager = self::getPersistenceManager();

		$dispatchLoopCount = 0;
		while (!$request->isDispatched()) {
			if ($dispatchLoopCount++ > 99) throw new Tx_Extbase_MVC_Exception_InfiniteLoop('Could not ultimately dispatch the request after '  . $dispatchLoopCount . ' iterations.', 1217839467);
			$controller = $this->getPreparedController($request);
			try {
				$controller->processRequest($request, $response);
			} catch (Tx_Extbase_MVC_Exception_StopAction $ignoredException) {
			} catch (Tx_Extbase_MVC_Exception_InvalidArgumentValue $exception) {
				return '';
			}
		}

		$persistenceManager->persistAll();
		self::$reflectionService->shutdown();
		if (count($response->getAdditionalHeaderData()) > 0) {
			$GLOBALS['TSFE']->additionalHeaderData[$request->getControllerExtensionName()] = implode("\n", $response->getAdditionalHeaderData());
		}
		$response->sendHeaders();
		return $response->getContent();
	}
	
	/**
	 * Initializes the autoload mechanism of Extbase. This is supplement to the core autoloader.
	 *
	 * @return void
	 */
	
	protected function initializeClassLoader() {
		if (!class_exists('Tx_Extbase_Utility_ClassLoader')) {
			require(t3lib_extmgm::extPath('extbase') . 'Classes/Utility/ClassLoader.php');
		}

		$classLoader = new Tx_Extbase_Utility_ClassLoader();
		spl_autoload_register(array($classLoader, 'loadClass'));
	}

	/**
	 * Initializes the configuration manager and the Extbase settings
	 *
	 * @param $configuration The current incoming configuration
	 * @return void
	 */
	protected function initializeConfigurationManagerAndFrameworkConfiguration($configuration) {
		$configurationSources = array();
		$configurationSources[] = t3lib_div::makeInstance('Tx_Extbase_Configuration_Source_TypoScriptSource');
		if (!empty($this->cObj->data['pi_flexform'])) {
			$configurationSource = t3lib_div::makeInstance('Tx_Extbase_Configuration_Source_FlexFormSource');
			$configurationSource->setFlexFormContent($this->cObj->data['pi_flexform']);
			$configurationSources[] = $configurationSource;
		}
		$this->configurationManager = t3lib_div::makeInstance('Tx_Extbase_Configuration_Manager', $configurationSources);
		self::$extbaseFrameworkConfiguration = $this->configurationManager->getFrameworkConfiguration($configuration, $this->cObj);
	}

	/**
	 * Builds and returns a controller
	 *
	 * @param Tx_Extbase_MVC_Web_Request $request
	 * @return Tx_Extbase_MVC_Controller_ControllerInterface The prepared controller
	 */
	protected function getPreparedController(Tx_Extbase_MVC_Web_Request $request) {
		$controllerObjectName = $request->getControllerObjectName();
		$controller = t3lib_div::makeInstance($controllerObjectName);
		if (!$controller instanceof Tx_Extbase_MVC_Controller_ControllerInterface) {
			throw new Tx_Extbase_MVC_Exception_InvalidController('Invalid controller "' . $request->getControllerObjectName() . '". The controller must implement the Tx_Extbase_MVC_Controller_ControllerInterface.', 1202921619);
		}
		self::$reflectionService = t3lib_div::makeInstance('Tx_Extbase_Reflection_Service');
		$propertyMapper = t3lib_div::makeInstance('Tx_Extbase_Property_Mapper');
		$propertyMapper->injectReflectionService(self::$reflectionService);
		$controller->injectPropertyMapper($propertyMapper);
		$controller->injectSettings($this->configurationManager->getSettings($request->getControllerExtensionName()));
		$cacheManager = t3lib_div::makeInstance('t3lib_cache_Manager');
		try {
			self::$reflectionService->setCache($cacheManager->getCache('cache_extbase_reflection'));
		} catch (t3lib_cache_exception_NoSuchCache $exception) {
			$GLOBALS['typo3CacheFactory']->create(
				'cache_extbase_reflection',
				't3lib_cache_frontend_VariableFrontend',
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_extbase_reflection']['backend'],
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_extbase_reflection']['options']
			);
			self::$reflectionService->setCache($cacheManager->getCache('cache_extbase_reflection'));
		}
		if (!self::$reflectionService->isInitialized()) {
			self::$reflectionService->initialize();
		}
		$objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_Manager');
		$validatorResolver = t3lib_div::makeInstance('Tx_Extbase_Validation_ValidatorResolver');
		$validatorResolver->injectObjectManager($objectManager);
		$validatorResolver->injectReflectionService(self::$reflectionService);
		$controller->injectValidatorResolver($validatorResolver);
		$controller->injectReflectionService(self::$reflectionService);
		$controller->injectObjectManager($objectManager);
		return $controller;
	}

	/**
	 * This function prepares and returns the Persistance Manager
	 *
	 * @return Tx_Extbase_Persistence_Manager A (singleton) instance of the Persistence Manager
	 */
	public static function getPersistenceManager() {
		if (self::$persistenceManager === NULL) {
			$dataMapper = t3lib_div::makeInstance('Tx_Extbase_Persistence_Mapper_DataMapper'); // singleton

			$storageBackend = t3lib_div::makeInstance('Tx_Extbase_Persistence_Storage_Typo3DbBackend', $GLOBALS['TYPO3_DB']); // singleton
			$storageBackend->injectDataMapper($dataMapper);

			$qomFactory = t3lib_div::makeInstance('Tx_Extbase_Persistence_QOM_QueryObjectModelFactory', $storageBackend, $dataMapper);
			
			$persistenceSession = t3lib_div::makeInstance('Tx_Extbase_Persistence_Session'); // singleton

			$persistenceBackend = t3lib_div::makeInstance('Tx_Extbase_Persistence_Backend', $persistenceSession, $storageBackend); // singleton
			$persistenceBackend->injectDataMapper($dataMapper);
			$persistenceBackend->injectIdentityMap(t3lib_div::makeInstance('Tx_Extbase_Persistence_IdentityMap'));
			$persistenceBackend->injectQueryFactory(t3lib_div::makeInstance('Tx_Extbase_Persistence_QueryFactory'));
			$persistenceBackend->injectQOMFactory($qomFactory);
			$persistenceBackend->injectValueFactory(t3lib_div::makeInstance('Tx_Extbase_Persistence_ValueFactory'));

			$objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_Manager'); // singleton
			
			$persistenceManager = t3lib_div::makeInstance('Tx_Extbase_Persistence_Manager'); // singleton
			$persistenceManager->injectBackend($persistenceBackend);
			$persistenceManager->injectSession($persistenceSession);
			$persistenceManager->injectObjectManager($objectManager);
			
			self::$persistenceManager = $persistenceManager;
		}

		return self::$persistenceManager;
	}

	/**
	 * This function returns the settings of Extbase
	 *
	 * @return array The settings
	 */
	public static function getExtbaseFrameworkConfiguration() {
		return self::$extbaseFrameworkConfiguration;
	}

		/**
	 * Calls an Extbase Backend module.
	 *
	 * @param string $module The name of the module
	 * @return void
	 */
	public function callModule($module) {
		if (isset($GLOBALS['TBE_MODULES'][$module])) {
			$config = $GLOBALS['TBE_MODULES'][$module];

			// Check permissions and exit if the user has no permission for entry
			$GLOBALS['BE_USER']->modAccess($config, TRUE);
			if (t3lib_div::_GP('id')) {
				// Check page access
				$id = t3lib_div::_GP('id');
				$permClause = $GLOBALS['BE_USER']->getPagePermsClause(TRUE);
				$access = is_array(t3lib_BEfunc::readPageAccess($id, $permClause));
				if (!$access) {
					t3lib_BEfunc::typo3PrintError('No Access', 'You don\'t have access to this page', 0);
				}
			}

			// Resolve the controller/action to use
			$controllerAction = $this->resolveControllerAction($module);

			// As for SCbase modules, output of the controller/action pair should be echoed
			echo $this->transfer($module, $controllerAction['controllerName'], $controllerAction['actionName']);
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Resolves the controller and action to use for current call.
	 * This takes into account any function menu that has being called.
	 *
	 * @param string $module The name of the module
	 * @return array The controller/action pair to use for current call
	 */
	protected function resolveControllerAction($module) {
		$configuration = $GLOBALS['TBE_MODULES'][$module];
		$fallbackControllerAction = $this->getFallbackControllerAction($configuration);

			// Extract dispatcher settings from request
		$argumentPrefix = strtolower('tx_' . $configuration['extensionName'] . '_' . $configuration['name']);
		$dispatcherParameters = t3lib_div::_GPmerged($argumentPrefix);
		$dispatcherControllerAction = $this->getDispatcherControllerAction($configuration, $dispatcherParameters);

			// Extract module function settings from request
		$moduleFunctionControllerAction = $this->getModuleFunctionControllerAction($module, $fallbackControllerAction['controllerName']);	

			// Dispatcher controller/action has precedence over default controller/action
		$controllerAction = t3lib_div::array_merge_recursive_overrule($fallbackControllerAction, $dispatcherControllerAction, FALSE, FALSE);
			// Module function controller/action has precedence
		$controllerAction = t3lib_div::array_merge_recursive_overrule($controllerAction, $moduleFunctionControllerAction, FALSE, FALSE);

		return $controllerAction;
	}

	/**
	 * Returns the fallback controller/action pair to be used when request does not contain
	 * any controller/action to be used or the provided parameters are not valid. 
	 *
	 * @param array $configuration The module configuration
	 * @return array The controller/action pair
	 */
	protected function getFallbackControllerAction($configuration) {
			// Extract module settings from its registration in ext_tables.php
		$controllers = array_keys($configuration['controllerActions']);
		$defaultController = array_shift($controllers);
		$actions = t3lib_div::trimExplode(',', $configuration['controllerActions'][$defaultController], TRUE);
		$defaultAction = $actions[0];

		return array(
			'controllerName' => $defaultController,
			'actionName' => $defaultAction,
		);
	}

	/**
	 * Returns the controller/action pair that was specified by the request if it is valid,
	 * otherwise, will just return a blank controller/action pair meaning the default
	 * controller/action should be used instead.
	 *
	 * @param array $configuration The module configuration
	 * @param array $dispatcherParameters The dispatcher parameters
	 * @return array The controller/action pair
	 */
	protected function getDispatcherControllerAction($configuration, $dispatcherParameters) {
		$controllerAction = array(
			'controllerName' => '',
			'actionName' => '',
		);

		if (!isset($dispatcherParameters['controllerName'])) {
				// Early return: should use fallback controller/action
			return $controllerAction;
		}

			// Extract configured controllers from module's registration in ext_tables.php
		$controllers = array_keys($configuration['controllerActions']);

		$controller = $dispatcherParameters['controllerName'];
		if (in_array($controller, $controllers)) {
				// Update return value as selected controller is valid
			$controllerAction['controllerName'] = $controller;
			$actions = t3lib_div::trimExplode(',', $configuration['controllerActions'][$controller], TRUE);
			if (isset($dispatcherParameters['actionName'])) {
					// Extract configured actions for selected controllers
				$action = $dispatcherParameters['actionName'];
				if (in_array($action, $actions)) {
						// Requested action is valid for selected controller
					$controllerAction['actionName'] = $action;
				} else {
						// Use first action of selected controller as fallback action
					$controllerAction['actionName'] = $actions[0];
				}
			} else {
					// Use first action of selected controller as fallback action
				$controllerAction['actionName'] = $actions[0];
			}
		}

		return $controllerAction;
	}

	/**
	 * Returns the controller/action pair to use if a module function parameter is found
	 * in the request, otherwise, will just return a blank controller/action pair.
	 *
	 * @param string $module The name of the module
	 * @param string $defaultController The module's default controller
	 * @return array The controller/action pair
	 */
	protected function getModuleFunctionControllerAction($module, $defaultController) {
		$controllerAction = array(
			'controller' => '',
			'action' => '',
		);

		$set = t3lib_div::_GP('SET');
		if (!$set) {
				// Early return
			return $controllerAction;
		}

		$moduleFunction = $set['function'];
		$matches = array();
		if (preg_match('/^(.*)->(.*)$/', $moduleFunction, $matches)) {
			$controllerAction['controller'] = $matches[1];
			$controllerAction['action'] = $matches[2];
		} else {
				// Support for external SCbase module function rendering
			$functions = $GLOBALS['TBE_MODULES_EXT'][$module]['MOD_MENU']['function'];
			if (isset($functions[$moduleFunction])) {
				$controllerAction['controller'] = $defaultController;
				$controllerAction['action'] = 'extObj';
			}
		}

		return $controllerAction;
	}

	/**
	 * Transfers the request to an Extbase backend module, calling
	 * a given controller/action.
	 *
	 * @param string $module The name of the module
	 * @param string $controller The controller to use
	 * @param string $action The controller's action to execute
	 * @return string The module rendered view
	 */
	protected function transfer($module, $controller, $action) {
		 $config = $GLOBALS['TBE_MODULES'][$module];
		 
		 $extbaseConfiguration = array(
			'userFunc' => 'tx_extbase_dispatcher->dispatch',
			'pluginName' => $module,
			'extensionName' => $config['extensionName'],
			'controller' => $controller,
			'action' => $action,
			'switchableControllerActions.' => array(),
			'persistence' => '< plugin.tx_' . strtolower($config['extensionName']) . '.persistence',
		);
		
		$i = 1;
		foreach ($config['controllerActions'] as $controller => $actions) {
				// Add an "extObj" action for the default controller to handle external
				// SCbase modules which add function menu entries
			if ($i == 1) {
				$actions .= ',extObj'; 
			}
			$extbaseConfiguration['switchableControllerActions.'][$i++ . '.'] = array(
				'controller' => $controller,
				'actions' => $actions,
			);
		}
				
			// BACK_PATH is the path from the typo3/ directory from within the
			// directory containing the controller file. We are using mod.php dispatcher
			// and thus we are already within typo3/ because we call typo3/mod.php
		$GLOBALS['BACK_PATH'] = '';
		return $this->dispatch('Here comes Extbase BE Module', $extbaseConfiguration);
	}
	
}
?>
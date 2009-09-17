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
 * Validator resolver to automatically find a appropriate validator for a given subject
 *
 * @package Extbase
 * @subpackage Validation
 * @version $Id$
 */
class Tx_Extbase_Validation_ValidatorResolver {

	/**
	 * Match validator names and options
	 * @var string
	 */
	const PATTERN_MATCH_VALIDATORS = '/(?:^|,\s*)(?P<validatorName>[a-z0-9_]+)\s*(?:\((?P<validatorOptions>.+)\))?/i';

	/**
	 * @var Tx_Extbase_Object_ManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var Tx_Extbase_Reflection_Service
	 */
	protected $reflectionService;

	/**
	 * @var array
	 */
	protected $baseValidatorConjunctions = array();

	/**
	 * Injects the object manager
	 *
	 * @param Tx_Extbase_Object_ManagerInterface $objectManager A reference to the object manager
	 * @return void
	 */
	public function injectObjectManager(Tx_Extbase_Object_ManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * Injects the reflection service
	 *
	 * @param Tx_Extbase_Reflection_Service $reflectionService
	 * @return void
	 */
	public function injectReflectionService(Tx_Extbase_Reflection_Service $reflectionService) {
		$this->reflectionService = $reflectionService;
	}

	/**
	 * Get a validator for a given data type. Returns a validator implementing
	 * the Tx_Extbase_Validation_Validator_ValidatorInterface or NULL if no validator
	 * could be resolved.
	 *
	 * @param string $validatorName Either one of the built-in data types or fully qualified validator class name
	 * @param array $validatorOptions Options to be passed to the validator
	 * @return Tx_Extbase_Validation_Validator_ValidatorInterface Validator or NULL if none found.
	 */
	public function createValidator($validatorName, array $validatorOptions = array()) {
		$validatorClassName = $this->resolveValidatorObjectName($validatorName);
		if ($validatorClassName === FALSE) return NULL;
		$validator = $this->objectManager->getObject($validatorClassName);
		if (!($validator instanceof Tx_Extbase_Validation_Validator_ValidatorInterface)) {
			return NULL;
		}

		$validator->setOptions($validatorOptions);
		return $validator;
	}

	/**
	 * Resolves and returns the base validator conjunction for the given data type.
	 *
	 * If no validator could be resolved (which usually means that no validation is necessary),
	 * NULL is returned.
	 *
	 * @param string $dataType The data type to search a validator for. Usually the fully qualified object name
	 * @return Tx_Extbase_Validation_Validator_ConjunctionValidator The validator conjunction or NULL
	 */
	public function getBaseValidatorConjunction($dataType) {
		if (!isset($this->baseValidatorConjunctions[$dataType])) {
			$this->baseValidatorConjunctions[$dataType] = $this->buildBaseValidatorConjunction($dataType);
		}
		return $this->baseValidatorConjunctions[$dataType];
	}

	/**
	 * Detects and registers any validators for arguments:
	 * - by the data type specified in the @param annotations
	 * - additional validators specified in the @validate annotations of a method
	 *
	 * @return array An Array of ValidatorConjunctions for each method parameters.
	 */
	public function buildMethodArgumentsValidatorConjunctions($className, $methodName) {
		$validatorConjunctions = array();

		$methodParameters = $this->reflectionService->getMethodParameters($className, $methodName);
		$methodTagsValues = $this->reflectionService->getMethodTagsValues($className, $methodName);
		if (!count($methodParameters)) {
			// early return in case no parameters were found.
			return $validatorConjunctions;
		}
		foreach ($methodParameters as $parameterName => $methodParameter) {
			$validatorConjunction = $this->createValidator('Conjunction');
			$typeValidator = $this->createValidator($methodParameter['type']);
			if ($typeValidator !== NULL) $validatorConjunction->addValidator($typeValidator);
			$validatorConjunctions[$parameterName] = $validatorConjunction;
		}

		if (isset($methodTagsValues['validate'])) {
			foreach ($methodTagsValues['validate'] as $validateValue) {
				$parsedAnnotation = $this->parseValidatorAnnotation($validateValue);
				foreach ($parsedAnnotation['validators'] as $validatorConfiguration) {
					$newValidator = $this->createValidator($validatorConfiguration['validatorName'], $validatorConfiguration['validatorOptions']);
					if ($newValidator === NULL) throw new Tx_Extbase_Validation_Exception_NoSuchValidator('Invalid validate annotation in ' . $className . '->' . $methodName . '(): Could not resolve class name for  validator "' . $validatorConfiguration['validatorName'] . '".', 1239853109);

					if  (isset($validatorConjunctions[$parsedAnnotation['argumentName']])) {
						$validatorConjunctions[$parsedAnnotation['argumentName']]->addValidator($newValidator);
					} else {
						throw new Tx_Extbase_Validation_Exception_InvalidValidationConfiguration('Invalid validate annotation in ' . $className . '->' . $methodName . '(): Validator specified for argument name "' . $parsedAnnotation['argumentName'] . '", but this argument does not exist.', 1253172726);
					}
				}
			}
		}
		return $validatorConjunctions;
	}

	/**
	 * Builds a base validator conjunction for the given data type.
	 *
	 * The base validation rules are those which were declared directly in a class (typically
	 * a model) through some @validate annotations on properties.
	 *
	 * Additionally, if a custom validator was defined for the class in question, it will be added
	 * to the end of the conjunction. A custom validator is found if it follows the naming convention
	 * "Replace '\Model\' by '\Validator\' and append "Validator".
	 *
	 * Example: $dataType is F3\Foo\Domain\Model\Quux, then the Validator will be found if it has the
	 * name F3\Foo\Domain\Validator\QuuxValidator
	 *
	 * @param string $dataType The data type to build the validation conjunction for. Needs to be the fully qualified object name.
	 * @return Tx_Extbase_Validation_Validator_ConjunctionValidator The validator conjunction or NULL
	 */
	protected function buildBaseValidatorConjunction($dataType) {
		$validatorConjunction = $this->objectManager->getObject('Tx_Extbase_Validation_Validator_ConjunctionValidator');

		// Model based validator
		if (class_exists($dataType)) {
			$validatorCount = 0;
			$objectValidator = $this->createValidator('GenericObject');

			foreach ($this->reflectionService->getClassPropertyNames($dataType) as $classPropertyName) {
				$classPropertyTagsValues = $this->reflectionService->getPropertyTagsValues($dataType, $classPropertyName);
				if (!isset($classPropertyTagsValues['validate'])) continue;

				foreach ($classPropertyTagsValues['validate'] as $validateValue) {
					$parsedAnnotation = $this->parseValidatorAnnotation($validateValue);
					foreach ($parsedAnnotation['validators'] as $validatorConfiguration) {
						$newValidator = $this->createValidator($validatorConfiguration['validatorName'], $validatorConfiguration['validatorOptions']);
						if ($newValidator === NULL) {
							throw new Tx_Extbase_Validation_Exception_NoSuchValidator('Invalid validate annotation in ' . $dataType . '::' . $classPropertyName . ': Could not resolve class name for  validator "' . $validatorConfiguration['validatorName'] . '".', 1241098027);
						}
						$objectValidator->addPropertyValidator($classPropertyName, $newValidator);
						$validatorCount ++;
					}
				}
			}
			if ($validatorCount > 0) $validatorConjunction->addValidator($objectValidator);
		}

		// Custom validator for the class
		$possibleValidatorClassName = str_replace('_Model_', '_Validator_', $dataType) . 'Validator';
		$customValidator = $this->createValidator($possibleValidatorClassName);
		if ($customValidator !== NULL) {
			$validatorConjunction->addValidator($customValidator);
		}

		return $validatorConjunction;
	}

	/**
	 * Parses the validator options given in @validate annotations.
	 *
	 * @return array
	 */
	protected function parseValidatorAnnotation($validateValue) {
		$matches = array();
		if ($validateValue[0] === '$') {
			$parts = explode(' ', $validateValue, 2);
			$validatorConfiguration = array('argumentName' => ltrim($parts[0], '$'), 'validators' => array());
			preg_match_all(self::PATTERN_MATCH_VALIDATORS, $parts[1], $matches, PREG_SET_ORDER);
		} else {
			preg_match_all(self::PATTERN_MATCH_VALIDATORS, $validateValue, $matches, PREG_SET_ORDER);
		}

		foreach ($matches as $match) {
			$validatorName = $match['validatorName'];
			$validatorOptions = array();
			if (isset($match['validatorOptions'])) {
				if (strpos($match['validatorOptions'], '\'') === FALSE && strpos($match['validatorOptions'], '"') === FALSE) {
					$validatorOptions = $this->parseSimpleValidatorOptions($match['validatorOptions']);
				} else {
					$validatorOptions = $this->parseComplexValidatorOptions($match['validatorOptions']);
				}
			}
			$validatorConfiguration['validators'][] = array('validatorName' => $validatorName, 'validatorOptions' => $validatorOptions);
		}

		return $validatorConfiguration;
	}

	/**
	 * Parses $rawValidatorOptions not containing quoted option values.
	 * $rawValidatorOptions will be an empty string afterwards (pass by ref!).
	 *
	 * @param string &$rawValidatorOptions
	 * @return array An array of optionName/optionValue pairs
	 */
	protected function parseSimpleValidatorOptions(&$rawValidatorOptions) {
		$validatorOptions = array();

		$rawValidatorOptions = explode(',', $rawValidatorOptions);
		foreach ($rawValidatorOptions as $rawValidatorOption) {
			if (strpos($rawValidatorOption, '=') !== FALSE) {
				list($optionName, $optionValue) = explode('=', $rawValidatorOption, 2);
				$validatorOptions[trim($optionName)] = trim($optionValue);
			}
		}

		$rawValidatorOptions = '';
		return $validatorOptions;
	}

	/**
	 * Parses $rawValidatorOptions containing quoted option values.
	 *
	 * @param string $rawValidatorOptions
	 * @return array An array of optionName/optionValue pairs
	 */
	protected function parseComplexValidatorOptions($rawValidatorOptions) {
		$validatorOptions = array();

		while (strlen($rawValidatorOptions) > 0) {
			$parts = explode('=', $rawValidatorOptions, 2);
			$optionName = trim($parts[0]);
			$rawValidatorOptions = trim($parts[1]);

			$matches = array();
			preg_match('/(?:\'(.+)\'|"(.+)")(?:,|$)/', $rawValidatorOptions, $matches);
			$validatorOptions[$optionName] = str_replace(array('\\\'', '\\"'), array('\'', '"'), (isset($matches[2]) ? $matches[2] : $matches[1]));

			$rawValidatorOptions = ltrim(substr($rawValidatorOptions, strlen($matches[0])),', ');
			if (strpos($rawValidatorOptions, '\'') === FALSE && strpos($rawValidatorOptions, '"') === FALSE) {
				$validatorOptions = array_merge($validatorOptions, $this->parseSimpleValidatorOptions($rawValidatorOptions));
			}
		}

		return $validatorOptions;
	}

	/**
	 *
	 *
	 * Returns an object of an appropriate validator for the given class. If no validator is available
	 * NULL is returned
	 *
	 * @param string $validatorName Either the fully qualified class name of the validator or the short name of a built-in validator
	 * @return string Name of the validator object or FALSE
	 */
	protected function resolveValidatorObjectName($validatorName) {
		if (class_exists($validatorName)) return $validatorName;

		$possibleClassName = 'Tx_Extbase_Validation_Validator_' . $this->unifyDataType($validatorName) . 'Validator';
		if (class_exists($possibleClassName)) return $possibleClassName;

		return FALSE;
	}

	/**
	 * Preprocess data types. Used to map primitive PHP types to DataTypes used in Extbase.
	 *
	 * @param string $type Data type to unify
	 * @return string unified data type
	 */
	protected function unifyDataType($type) {
		switch ($type) {
			case 'int' :
				$type = 'Integer';
				break;
			case 'bool' :
				$type = 'Boolean';
				break;
			case 'double' :
				$type = 'Float';
				break;
			case 'numeric' :
				$type = 'Number';
				break;
			case 'mixed' :
				$type = 'Raw';
				break;
		}
		return ucfirst($type);
	}

}

?>
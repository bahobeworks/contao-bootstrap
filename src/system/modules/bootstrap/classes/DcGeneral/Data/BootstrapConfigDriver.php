<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 18.09.13
 * Time: 09:46
 * To change this template use File | Settings | File Templates.
 */

namespace Netzmacht\Bootstrap\DcGeneral\Data;

use DcGeneral\Data\CollectionInterface;
use DcGeneral\Data\ConfigInterface;
use DcGeneral\Data\DefaultCollection;
use DcGeneral\Data\DefaultConfig;
use DcGeneral\Data\DefaultModel;
use DcGeneral\Data\DriverInterface;
use DcGeneral\Data\ModelInterface;

class BootstrapConfigDriver implements DriverInterface
{
	protected $strSource = null;

	protected $strRoot = null;

	protected $arrIds = null;

	protected $objFile;


	/**
	 * Set base config with source and other necessary parameter.
	 *
	 * @param array $arrConfig The configuration to use.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException when no source has been defined.
	 */
	public function setBaseConfig(array $arrConfig)
	{
		// check root
		if(!isset($arrConfig['root']))
		{
			throw new \RuntimeException('No root given');
		}

		$this->strRoot = $arrConfig['root'];


		// set source
		if(!isset($arrConfig['path']))
		{
			throw new \RuntimeException('Missing filename name');
		}
		elseif(!is_file(TL_ROOT . '/' . $arrConfig['path']) && !$arrConfig['autoCreate'])
		{
			throw new \RuntimeException('File does not exists');
		}

		$this->objFile = new \File($arrConfig['path']);

		if(isset($arrConfig['ids']))
		{
			$this->arrIds = $arrConfig['ids'];
		}

		$this->strSource = $arrConfig['source'];
	}


	/**
	 * Return an empty configuration object.
	 *
	 * @return ConfigInterface
	 */
	public function getEmptyConfig()
	{
		return DefaultConfig::init();
	}


	/**
	 * Fetch an empty single record (new model).
	 *
	 * @return ModelInterface
	 */
	public function getEmptyModel()
	{
		$objModel = new DefaultModel();
		$objModel->setProviderName($this->strSource);

		return $objModel;
	}


	/**
	 * Fetch an empty single collection (new model list).
	 *
	 * @return CollectionInterface
	 */
	public function getEmptyCollection()
	{
		return new DefaultCollection();
	}


	/**
	 * get name of root
	 *
	 * @return string
	 */
	public function getRoot()
	{
		return $this->strRoot;
	}


	/**
	 * Fetch a single or first record by id or filter.
	 *
	 * If the model shall be retrieved by id, use $objConfig->setId() to populate the config with an Id.
	 *
	 * If the model shall be retrieved by filter, use $objConfig->setFilter() to populate the config with a filter.
	 *
	 * @param ConfigInterface $objConfig
	 *
	 * @return ModelInterface
	 */
	public function fetch(ConfigInterface $objConfig)
	{
		if($this->arrIds !== null && !in_array($objConfig->getId(), $this->arrIds))
		{
			throw new \RuntimeException('Invalid ID given');
		}

		if(!isset($GLOBALS[$this->strRoot][$objConfig->getId()]))
		{
			return null;
		}

		$objModel = $this->getEmptyModel();
		$objModel->setID($objConfig->getId());

		foreach($GLOBALS['TL_DCA'][$this->strSource]['fields'] as $key => $definition)
		{
			$objModel->setProperty($key, $this->getPathValue($key));
		}

		return $objModel;
	}


	/**
	 * Fetch all records (optional filtered, sorted and limited).
	 *
	 * This returns a collection of all models matching the config object. If idOnly is true, an array containing all
	 * matching ids is returned.
	 *
	 * @param ConfigInterface $objConfig
	 *
	 * @return CollectionInterface|array
	 */
	public function fetchAll(ConfigInterface $objConfig)
	{
		if($objConfig->getIdOnly())
		{
			return $this->arrIds !== null ? $this->arrIds : array_keys($GLOBALS[$this->strRoot]);
		}

		$objCollection = $this->getEmptyCollection();
		$arrIds = $this->arrIds !== null ? $this->arrIds : array_keys($GLOBALS[$this->strRoot]);

		foreach($arrIds as $id)
		{
			$objModel = $this->getEmptyModel();
			$objModel->setID($id);

			if(!isset($GLOBALS[$this->strRoot][$id]))
			{
				continue;
			}

			foreach($GLOBALS[$this->strRoot][$id] as $key => $value)
			{
				$objModel->setProperty($key, $this->getPathValue($key));
			}

			$objCollection->add($objModel);
		}

		return $objCollection;
	}


	/**
	 * Retrieve all unique values for the given property.
	 *
	 * The result set will be an array containing all unique values contained in the data provider.
	 * Note: this only re-ensembles really used values for at least one data set.
	 *
	 * The only information being interpreted from the passed config object is the first property to fetch and the
	 * filter definition.
	 *
	 * @param ConfigInterface $objConfig   The filter config options.
	 *
	 * @return CollectionInterface
	 */
	public function getFilterOptions(ConfigInterface $objConfig)
	{
		$arrProperties = $objConfig->getFields();
		$strProperty = $arrProperties[0];

		if (count($arrProperties) <> 1)
		{
			throw new \RuntimeException('objConfig must contain exactly one property to be retrieved.');
		}
		elseif($strProperty != 'id')
		{
			throw new \RuntimeException('Only id supported as filter option');
		}

		// @todo be aware what will be effected when i change config here?
		$objConfig->setIdOnly(true);
		return $this->fetchAll($objConfig);
	}


	/**
	 * Return the amount of total items (filtering may be used in the config).
	 *
	 * @param ConfigInterface $objConfig
	 *
	 * @return int
	 */
	public function getCount(ConfigInterface $objConfig)
	{
		// @todo be aware what will be effected when i change config here?
		$objConfig->setIdOnly(true);
		$arrIds = $this->fetchAll($objConfig);

		return count($arrIds);
	}


	/**
	 * Save an item to the data provider.
	 *
	 * If the item does not have an Id yet, the save operation will add it as a new row to the database and
	 * populate the Id of the model accordingly.
	 *
	 * @param ModelInterface $objItem   The model to save back.
	 *
	 * @return ModelInterface The passed model.
	 */
	public function save(ModelInterface $objItem)
	{
		$arrSet = array();

		if($objItem->getId() == '' || $objItem->getId() == null)
		{
			throw new \RuntimeException('No id given but required');
		}

		$data = '<?php' . "\n\n";
		$data .= $this->prepareForSave($objItem);

		$this->objFile->write($data);
	}


	/**
	 * @param $item
	 * @param array $arrTree
	 * @return string
	 */
	protected function prepareForSave($item, $arrTree = null, $blnForce=false)
	{
		if($arrTree === null)
		{
			$arrTree = array();
		}

		$buffer = '';

		$buildTree = function($arrTree)
		{
			$part = '$GLOBALS';

			foreach($arrTree as $tree)
			{
				if(is_int($tree))
				{
					$part .= '[' . $tree . ']';
				}
				else
				{
					$part .= '[\'' . $tree . '\']';
				}
			}

			return $part;
		};

		foreach($item as $key => $value)
		{
			// @todo make it configurable that null values will be passed
			if($value === null)
			{
				continue;
			}

			$info = $this->getFieldInfo($key);

			$arrNewTree   = array_unique(array_merge($arrTree, $info['path']));
			$arrNewTree[] = $info['field'];

			// do not save id on root level
			if(($key == 'tstamp' || $key == 'id') && empty($arrTree))
			{
				continue;
			}

			$hasChanged = !($value == $this->getTreeValue($arrNewTree));

			//nothing changed
			if(!$hasChanged && !$blnForce && ((count($arrNewTree) - $blnForce) > 2))
			{
				continue;
			}

			$blnForce = ($hasChanged || $blnForce) ? count($arrNewTree) : false;

			if(is_array($value))
			{
				//
				$children = $this->prepareForSave($value, $arrNewTree, $blnForce);

				if($children != '')
				{
					$buffer .= $buildTree($arrNewTree) . ' = array(); ' . "\n";
					$buffer .= $children;
				}
			}
			else
			{
				$part = $buildTree($arrNewTree);
				$part .= ' = ' . $this->prepareValueForSave($value) . ";\n";

				$buffer .= $part;
			}
		}

		return $buffer;
	}


	/**
	 * @param $value
	 * @return int|string
	 * @throws \RuntimeException
	 */
	protected function prepareValueForSave($value)
	{
		if(is_bool($value))
		{
			return $value ? 'true' : 'false';
		}
		elseif(is_numeric($value))
		{
			return $value;
		}
		elseif(is_string($value))
		{
			return '\'' . html_entity_decode($value, ENT_QUOTES, $GLOBALS['TL_CONFIG']['characterSet']) . '\'';
		}
		elseif(is_null($value))
		{
			return 'null';
		}
		else
		{
			throw new \RuntimeException('Invalid value given');
		}
	}


	/**
	 * Save a collection of items to the data provider.
	 *
	 * @param CollectionInterface $objItems The collection containing all items to be saved.
	 *
	 * @return void
	 */
	public function saveEach(CollectionInterface $objItems)
	{
		// @todo Will we support multi entries?
		throw new \RuntimeException('Data provider does not support multiple savings');
	}


	/**
	 * Delete an item.
	 *
	 * The given value may be either integer, string or an instance of Model
	 *
	 * @param mixed $item Id or the model itself, to delete.
	 *
	 * @throws \RuntimeException when an unusable object has been passed.
	 */
	public function delete($item)
	{
		if($item instanceof ModelInterface)
		{
			$item = $item->getId();
		}

		unset($GLOBALS[$this->strRoot][$item]);

		if(empty($GLOBALS[$this->strRoot]))
		{
			$this->objFile->delete();
		}
	}


	/**
	 * Save a new version of a model.
	 *
	 * @param ModelInterface $objModel    The model for which a new version shall be created.
	 *
	 * @param string $strUsername The username to attach to the version as creator.
	 *
	 * @return void
	 */
	public function saveVersion(ModelInterface $objModel, $strUsername)
	{
		throw new \RuntimeException('Versioning is not supported');
	}


	/**
	 * Return a model based of the version information.
	 *
	 * @param mixed $mixID      The ID of the record.
	 *
	 * @param mixed $mixVersion The ID of the version.
	 *
	 * @return ModelInterface
	 */
	public function getVersion($mixID, $mixVersion)
	{
		throw new \RuntimeException('Versioning is not supported');
	}


	/**
	 * Return a list with all versions for the model with the given Id.
	 *
	 * @param mixed $mixID         The ID of the row.
	 *
	 * @param boolean $blnOnlyActive If true, only active versions will get returned, if false all version will get
	 *                               returned.
	 *
	 * @return CollectionInterface
	 */
	public function getVersions($mixID, $blnOnlyActive = false)
	{
		return null;
	}


	/**
	 * Set a version as active.
	 *
	 * @param mixed $mixID      The ID of the model.
	 *
	 * @param mixed $mixVersion The version number to set active.
	 */
	public function setVersionActive($mixID, $mixVersion)
	{
		throw new \RuntimeException('Versioning is not supported');
	}


	/**
	 * Retrieve the current active version for a model.
	 *
	 * @param mixed $mixID The ID of the model.
	 *
	 * @return mixed The current version number of the requested row.
	 */
	public function getActiveVersion($mixID)
	{
		throw new \RuntimeException('Versioning is not supported');
	}


	/**
	 * Reset the fallback field.
	 *
	 * This clears the given property in all items in the data provider to an empty value.
	 *
	 * Documentation:
	 *      Evaluation - fallback => If true the field can only be assigned once per table.
	 *
	 * @param string $strField The field to reset.
	 *
	 * @return void
	 */
	public function resetFallback($strField)
	{
		throw new \RuntimeException('resetFallback is not supported');
	}


	/**
	 * Check if the value is unique in the data provider.
	 *
	 * @param string $strField the field in which to test.
	 *
	 * @param mixed $varNew   the value about to be saved.
	 *
	 * @param int $intId    the (optional) id of the item currently in scope - pass null for new items.
	 *
	 * Documentation:
	 *      Evaluation - unique => If true the field value cannot be saved if it exists already.
	 *
	 * @return boolean
	 */
	public function isUniqueValue($strField, $varNew, $intId = null)
	{
		throw new \RuntimeException('Unique fields are not supported');
	}


	/**
	 * Check if a property with the given name exists in the data provider.
	 *
	 * @param string $strField The name of the property to search.
	 *
	 * @return boolean
	 */
	public function fieldExists($strField)
	{
		// @todo We acutally need an ID here to check if field exists.
		return true;
	}


	/**
	 * Check if two models have the same values in all properties.
	 *
	 * @param ModelInterface $objModel1 The first model to compare.
	 *
	 * @param ModelInterface $objModel2 The second model to compare.
	 *
	 * @return boolean True - If both models are same, false if not.
	 */
	public function sameModels($objModel1, $objModel2)
	{
		foreach ($objModel1 as $key => $value)
		{
			if ($key == "id")
			{
				continue;
			}

			if (is_array($value))
			{
				if (!is_array($objModel2->getProperty($key)))
				{
					return false;
				}

				if (serialize($value) != serialize($objModel2->getProperty($key)))
				{
					return false;
				}
			}
			else if ($value != $objModel2->getProperty($key))
			{
				return false;
			}
		}

		return true;
	}


	/**
	 * create field name
	 * @return string
	 */
	public static function pathToField()
	{
		return '__' . implode('__', func_get_args());
	}


	/**
	 * @param $fieldName
	 * @return array
	 */
	protected function getFieldInfo($fieldName)
	{
		if(strpos($fieldName, '__') === 0)
		{
			$path = explode('__', substr($fieldName, 2));

			return array
			(
				'field'  => array_pop($path),
				'path'   => array_merge(array($this->strRoot), $path),
			);
		}
		else
		{
			return array
			(
				'field' => $fieldName,
				'path'  => array($this->strRoot),
			);
		}
	}


	/**
	 * @param $path
	 * @return mixed
	 */
	protected function getPathValue($path)
	{
		$info = $this->getFieldInfo($path);
		$value = $GLOBALS;

		foreach($info['path'] as $node)
		{
			$value = $value[$node];
		}

		return $value[$info['field']];
	}


	protected function getTreeValue($arrTree)
	{
		$value = $GLOBALS;

		foreach($arrTree as $node)
		{
			$value = $value[$node];
		}

		return $value;
	}

}
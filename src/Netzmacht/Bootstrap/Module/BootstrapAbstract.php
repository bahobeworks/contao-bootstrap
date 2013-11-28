<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * @package   netzmacht-bootstrap
 * @author    netzmacht creative David Molineus
 * @license   MPL/2.0
 * @copyright 2013 netzmacht creative David Molineus
 */

namespace Netzmacht\Bootstrap\Module;

use Netzmacht\Bootstrap\Attributes;

/**
 * Class BootstrapModule provides easy access for bootstrap namespaces attributes
 *
 * @package Netzmacht\Bootstrap
 */
abstract class BootstrapAbstract extends \Module
{

	/**
	 * @var array
	 */
	protected $arrBootstrapAttributes = array();


	/**
	 * @param        $objElement
	 * @param string $strColumn
	 */
	public function __construct($objElement, $strColumn='main')
	{
		parent::__construct($objElement, $strColumn);

		$this->arrData = new Attributes($this->arrData);
		$this->arrData->registerNamespaceAttributes($this->arrBootstrapAttributes);
		$this->cssDefinitioin = $this->cssID;
	}


	/**
	 * @return string|void
	 *
	 * Need to copy original method because of #6149
	 */
	public function generate()
	{
		if ($this->arrData['space'][0] != '')
		{
			$this->arrStyle[] = 'margin-top:'.$this->arrData['space'][0].'px;';
		}

		if ($this->arrData['space'][1] != '')
		{
			$this->arrStyle[] = 'margin-bottom:'.$this->arrData['space'][1].'px;';
		}

		$this->Template = new \FrontendTemplate($this->strTemplate);
		$this->Template->setData(clone $this->arrData);

		$this->compile();

		$this->Template->inColumn = $this->strColumn;
		$this->Template->style = !empty($this->arrStyle) ? implode(' ', $this->arrStyle) : '';
		$this->Template->class = trim('mod_' . $this->type . ' ' . $this->cssID[1]);
		$this->Template->cssID = ($this->cssID[0] != '') ? ' id="' . $this->cssID[0] . '"' : '';

		if ($this->Template->headline == '')
		{
			$this->Template->headline = $this->headline;
		}

		if ($this->Template->hl == '')
		{
			$this->Template->hl = $this->hl;
		}

		return $this->Template->parse();
	}

}
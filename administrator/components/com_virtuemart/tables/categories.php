<?php
/**
*
* Product table
*
* @package	VirtueMart
* @subpackage Category
* @author jseros
* @link http://www.virtuemart.net
* @copyright Copyright (c) 2004 - 2010 VirtueMart Team. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* @version $Id$
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmTable'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmtable.php');
/**
 * Category table class
 * The class is is used to table-level abstraction for Categories.
 *
 * @package	VirtueMart
 * @subpackage Category
 * @author jseros
 */
class TableCategories extends VmTable {

	/** @var int Primary key */
	var $virtuemart_category_id	= null;
	/** @var integer Product id */
	var $virtuemart_vendor_id		= 0;
	/** @var string Category name */
	var $category_name		=  '';
	/** @var string Category description */
	var $category_description		= '';

	/** @var string Category browse page layout */
	var $category_template = null;
	/** @var string Category browse page layout */
	var $category_layout = null;
	/** @var int Category flypage */
	var $category_product_layout		= null;

	/** @var integer Products to show per row  */
	var $products_per_row		= null;
	/** @var int Category order */
	var $ordering		= 0;
	/** @var int category limit start*/
	var $limit_list_start 	 = 0;
	/** @var int category limit step*/
	var $limit_list_step 	 = 10;
	/** @var int category limit max */
	var $limit_list_max	= 0;
	/** @var int category limit initial */
	var $limit_list_initial	= 10;
	/** @var string Meta description */
	var $metadesc	= '';
	/** @var string Meta keys */
	var $metakey	= '';
	/** @var string Meta robot */
	var $metarobot	= '';
	/** @var string Meta author */
	var $metaauthor	= '';
        /** @var integer Category publish or not */
	var $published			= 1;
        /** @var date Category creation date */
        var $created_on = null;
        var $created_by = 0;
        /** @var date Category last modification date */
        var $modified_on = null;
        var $modified_by = 0;

   	/** @var boolean */
	var $locked_on	= null;
	/** @var time */
	var $locked_by	= 0;
	/**
	 * Class contructor
	 *
	 * @author Max Milbers
	 * @param $db A database connector object
	 */
	public function __construct($db) {
		parent::__construct('#__virtuemart_categories', 'virtuemart_category_id', $db);

		$this->setUniqueName('category_name','COM_VIRTUEMART_CATEGORY_NAME_ALREADY_EXISTS');
		$this->setLoggable();
	}

//	/**
//	 *
//	 * @author Max Milbers
//	 */
//	public function check(){
//
//		$date = JFactory::getDate();
//		$today = $date->toMySQL();
//		if(empty($this->created_on)){
//			$this->created_on = $today;
//		}
//     	$this->modified_on = $today;
//
//     	if(empty($this->category_name)){
//            $this->setError('Cant save category without name');
//            return false;
//     	}
//
//		if (!empty($this->category_name)) {
//		    $db = JFactory::getDBO();
//
//			$q = 'SELECT `virtuemart_category_id` FROM `#__virtuemart_categories` ';
//			$q .= 'WHERE `category_name`="' . $this->category_name . '"';
//            $db->setQuery($q);
//		    $virtuemart_category_id = $db->loadResult();
//		    if (!empty($virtuemart_category_id) && $virtuemart_category_id!=$this->virtuemart_category_id) {
//				$this->setError(JText::_('COM_VIRTUEMART_CATEGORY_NAME_ALREADY_EXISTS'));
//				return false;
//			}
//		}
//
//     	return true;
//	}

	/**
	 * Overwrite method
	 *
	 * @author jseros
	 * @param $dirn movement number
	 * @param $parent_id category parent id
	 * @param $where sql WHERE clausule
	 */
	public function move( $dirn, $parent_id = 0, $where='' )
	{
		if (!in_array( 'ordering',  array_keys($this->getProperties())))
		{
			$this->setError( get_class( $this ).' does not support ordering' );
			return false;
		}

		$k = $this->_tbl_key;

		$sql = "SELECT ".$this->_tbl_key.", ordering FROM ".$this->_tbl." c
				LEFT JOIN #__virtuemart_category_categories cx
				ON c.virtuemart_category_id = cx.category_child_id";

		$condition = 'cx.category_parent_id = '. $this->_db->Quote($parent_id);
		$where = ($where ? ' AND '.$condition : $condition);

		if ($dirn < 0)
		{
			$sql .= ' WHERE c.ordering < '.(int) $this->ordering;
			$sql .= ($where ? ' AND '.$where : '');
			$sql .= ' ORDER BY c.ordering DESC';
		}
		else if ($dirn > 0)
		{
			$sql .= ' WHERE c.ordering > '.(int) $this->ordering;
			$sql .= ($where ? ' AND '. $where : '');
			$sql .= ' ORDER BY c.ordering';
		}
		else
		{
			$sql .= ' WHERE c.ordering = '.(int) $this->ordering;
			$sql .= ($where ? ' AND '.$where : '');
			$sql .= ' ORDER BY c.ordering';
		}

		$this->_db->setQuery( $sql, 0, 1 );


		$row = null;
		$row = $this->_db->loadObject();
		if (isset($row))
		{
			$query = 'UPDATE '. $this->_tbl
			. ' SET ordering = '. (int) $row->ordering
			. ' WHERE '. $this->_tbl_key .' = '. $this->_db->Quote($this->$k)
			;
			$this->_db->setQuery( $query );

			if (!$this->_db->query())
			{
				$err = $this->_db->getErrorMsg();
				JError::raiseError( 500, $err );
			}

			$query = 'UPDATE '.$this->_tbl
			. ' SET ordering = '.(int) $this->ordering
			. ' WHERE '.$this->_tbl_key.' = '.$this->_db->Quote($row->$k)
			;
			$this->_db->setQuery( $query );

			if (!$this->_db->query())
			{
				$err = $this->_db->getErrorMsg();
				JError::raiseError( 500, $err );
			}

			$this->ordering = $row->ordering;
		}
		else
		{
			$query = 'UPDATE '. $this->_tbl
			. ' SET ordering = '.(int) $this->ordering
			. ' WHERE '. $this->_tbl_key .' = '. $this->_db->Quote($this->$k)
			;
			$this->_db->setQuery( $query );

			if (!$this->_db->query())
			{
				$err = $this->_db->getErrorMsg();
				JError::raiseError( 500, $err );
			}
		}
		return true;
	}

	/**
	 * Overwrite method
	 * Compacts the ordering sequence of the selected records
	 * @author jseros
	 *
	 * @param $parent_id category parent id
	 * @param string Additional where query to limit ordering to a particular subset of records
	 */
	function reorder( $parent_id = 0, $where='' )
	{
		$k = $this->_tbl_key;

		if (!in_array( 'ordering', array_keys($this->getProperties() ) ))
		{
			$this->setError( get_class( $this ).' does not support ordering');
			return false;
		}

		$query = 'SELECT c.'.$this->_tbl_key.', c.ordering'
		. ' FROM '. $this->_tbl . ' c'
		. ' LEFT JOIN #__virtuemart_category_categories cx'
		. ' ON c.virtuemart_category_id = cx.category_child_id'
		. ' WHERE c.ordering >= 0' . ( $where ? ' AND '. $where : '' )
		. ' AND cx.category_parent_id = '. $parent_id
		. ' ORDER BY c.ordering'.$order2;

		$this->_db->setQuery( $query );
		if (!($orders = $this->_db->loadObjectList()))
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		// compact the ordering numbers
		for ($i=0, $n=count( $orders ); $i < $n; $i++)
		{
			if ($orders[$i]->ordering >= 0)
			{
				if ($orders[$i]->ordering != $i+1)
				{
					$orders[$i]->ordering = $i+1;
					$query = 'UPDATE '.$this->_tbl
					. ' SET ordering = '. (int) $orders[$i]->ordering
					. ' WHERE '. $k .' = '. $this->_db->Quote($orders[$i]->$k)
					;
					$this->_db->setQuery( $query);
					$this->_db->query();
				}
			}
		}

	return true;
	}
}

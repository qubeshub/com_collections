<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Collections\Models\Item;

use Components\Collections\Models\Item as GenericItem;
use Components\Wiki\Models\Book;
use Components\Wiki\Models\Page;
use Request;
use Route;
use Lang;

require_once(dirname(__DIR__) . DS . 'item.php');

/**
 * Collections model for an item
 */
class Wiki extends GenericItem
{
	/**
	 * Item type
	 *
	 * @var  string
	 */
	protected $_type = 'wiki';

	/**
	 * Get the item type
	 *
	 * @param   string  $as  Return type as?
	 * @return  string
	 */
	public function type($as=null)
	{
		if ($as == 'title')
		{
			return Lang::txt('Wiki page');
		}
		return parent::type($as);
	}

	/**
	 * Chck if we're on a URL where an item can be collected
	 *
	 * @return  boolean
	 */
	public function canCollect()
	{
		if (Request::getCmd('option') != 'com_wiki')
		{
			return false;
		}

		if (!Request::getVar('pagename', ''))
		{
			return false;
		}

		return true;
	}

	/**
	 * Create an item entry for a wiki page
	 *
	 * @param   integer  $id  Optional ID to use
	 * @return  boolean
	 */
	public function make($id=null)
	{
		if ($this->exists())
		{
			return true;
		}

		include_once(PATH_CORE . DS . 'components' . DS . 'com_wiki' . DS . 'models' . DS . 'book.php');
		$page = null;

		if (!$id)
		{
			$group = Request::getVar('cn', '');

			$book = new Book(($group ? $group : '__site__'));
			$page = $book->page();

			$id = $page->get('id');
		}

		$this->_tbl->loadType($id, $this->_type);

		if ($this->exists())
		{
			return true;
		}

		if (!$page)
		{
			$page = new Page($id);
		}

		if (!$page->exists())
		{
			$this->setError(Lang::txt('Wiki page not found.'));
			return false;
		}

		$this->set('type', $this->_type)
		     ->set('object_id', $page->get('id'))
		     ->set('created', $page->get('created'))
		     ->set('created_by', $page->get('created_by'))
		     ->set('title', $page->get('title'))
		     ->set('description', $page->revision()->content('clean', 300))
		     ->set('url', Route::url($page->link()));

		if (!$this->store())
		{
			return false;
		}

		return true;
	}
}

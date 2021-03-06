<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Collections\Admin\Controllers;

use Components\Collections\Models\Orm\Item;
use Components\Collections\Models\Orm\Asset;
use Hubzero\Component\AdminController;
use Request;
use Notify;
use Route;
use Lang;
use User;
use Date;
use App;

/**
 * Controller class for collection items
 */
class Items extends AdminController
{
	/**
	 * Execute a task
	 *
	 * @return  void
	 */
	public function execute()
	{
		$this->registerTask('add', 'edit');
		$this->registerTask('apply', 'save');

		parent::execute();
	}

	/**
	 * Display a list of all entries
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// Get filters
		$filters = array(
			'sort' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sort',
				'filter_order',
				'created'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sortdir',
				'filter_order_Dir',
				'DESC'
			),
			'search' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.search',
				'search',
				''
			)),
			'type' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.type',
				'type',
				''
			)),
			'state' => Request::getState(
				$this->_option . '.' . $this->_controller . '.state',
				'state',
				'-1'
			),
			'access' => Request::getState(
				$this->_option . '.' . $this->_controller . '.access',
				'access',
				'-1'
			)
		);

		$model = Item::all()
			->including(['creator', function ($creator){
				$creator->select('*');
			}]);

		if ($filters['search'])
		{
			$model->whereLike('title', strtolower((string)$filters['search']));
		}

		if ($filters['state'] >= 0)
		{
			$model->whereEquals('state', $filters['state']);
		}

		if ($filters['access'] >= 0)
		{
			$model->whereEquals('access', (int)$filters['access']);
		}

		if ($filters['type'])
		{
			$model->whereEquals('type', $filters['type']);
		}
		else
		{
			$model->where('type', '!=', 'collection');
		}

		// Get records
		$rows = $model
			->ordered('filter_order', 'filter_order_Dir')
			->paginated('limitstart', 'limit')
			->rows();

		$types = Item::all()
			->select('DISTINCT(type)')
			->whereRaw("type != ''")
			->whereRaw("type != 'collection'")
			->rows();

		// Output the HTML
		$this->view
			->set('filters', $filters)
			->set('rows', $rows)
			->set('types', $types)
			->display();
	}

	/**
	 * Edit a collection
	 *
	 * @param   object  $row
	 * @return  void
	 */
	public function editTask($row=null)
	{
		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.create', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		Request::setVar('hidemainmenu', 1);

		if (!is_object($row))
		{
			// Incoming
			$id = Request::getArray('id', array(0));

			if (is_array($id))
			{
				$id = (!empty($id) ? $id[0] : 0);
			}

			$row = Item::oneOrNew($id);
		}

		if ($row->isNew())
		{
			$row->set('created_by', User::get('id'));
			$row->set('created', Date::toSql());
		}

		// Output the HTML
		$this->view
			->set('row', $row)
			->setLayout('edit')
			->display();
	}

	/**
	 * Save an entry
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.create', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$fields = Request::getArray('fields', array(), 'post');

		// Initiate extended database class
		$row = Item::oneOrNew($fields['id'])->set($fields);

		// Store new content
		if (!$row->save())
		{
			Notify::error($row->getError());
			return $this->editTask($row);
		}

		// Save assets
		$assets = Request::getArray('assets', array(), 'post');

		$k = 1;

		foreach ($assets as $i => $asset)
		{
			$a = Asset::oneOrNew($asset['id']);
			$a->set('type', $asset['type']);
			$a->set('item_id', $row->get('id'));
			$a->set('ordering', $k);
			$a->set('filename', $asset['filename']);
			$a->set('state', $a::STATE_PUBLISHED);

			if (strtolower($a->get('filename')) == 'http://')
			{
				if (!$a->get('id'))
				{
					continue;
				}

				if (!$a->destroy())
				{
					Notify::error($a->getError());
					continue;
				}
			}

			if (!$a->save())
			{
				Notify::error($a->getError());
				continue;
			}

			$k++;
		}

		// Process tags
		$row->tag(trim(Request::getString('tags', '')));

		Notify::success(Lang::txt('COM_COLLECTIONS_POST_SAVED'));

		if ($this->getTask() == 'apply')
		{
			return $this->editTask($row);
		}

		// Set the redirect
		$this->cancelTask();
	}

	/**
	 * Delete one or more entries
	 *
	 * @return  void
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.delete', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);
		$i = 0;

		if (count($ids) > 0)
		{
			// Loop through all the IDs
			foreach ($ids as $id)
			{
				$entry = Item::oneOrFail(intval($id));

				// Delete the entry
				if (!$entry->destroy())
				{
					Notify::error($entry->getError());
					continue;
				}

				$i++;
			}
		}

		if ($i)
		{
			Notify::success(Lang::txt('COM_COLLECTIONS_ITEMS_DELETED'));
		}

		// Set the redirect
		$this->cancelTask();
	}
}

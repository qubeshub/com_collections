<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2011 Purdue University. All rights reserved.
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
 * @copyright Copyright 2005-2011 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(PATH_CORE . DS . 'components' . DS . 'com_collections' . DS . 'models' . DS . 'archive.php');

/**
 * API controller class for support tickets
 */
class CollectionsControllerApi extends \Hubzero\Component\ApiController
{
	/**
	 * Execute a request
	 *
	 * @return    void
	 */
	public function execute()
	{
		//JLoader::import('joomla.environment.request');
		//JLoader::import('joomla.application.component.helper');

		$this->config   = Component::params('com_blog');
		$this->database = JFactory::getDBO();

		switch ($this->segments[0])
		{
			case 'collections': $this->collectionsTask(); break;
			case 'collection':  $this->collectionTask();  break;

			case 'posts': $this->postsTask(); break;
			case 'post':  $this->postTask();  break;

			default:
				$this->serviceTask();
			break;
		}
	}

	/**
	 * Method to report errors. creates error node for response body as well
	 *
	 * @param	$code		Error Code
	 * @param	$message	Error Message
	 * @param	$format		Error Response Format
	 *
	 * @return     void
	 */
	private function errorMessage($code, $message, $format = 'json')
	{
		//build error code and message
		$object = new stdClass();
		$object->error->code    = $code;
		$object->error->message = $message;

		//set http status code and reason
		$this->getResponse()
		     ->setErrorMessage($object->error->code, $object->error->message);

		//add error to message body
		$this->setMessageType(Request::getWord('format', $format));
		$this->setMessage($object);
	}

	/**
	 * Displays a available options and parameters the API
	 * for this comonent offers.
	 *
	 * @return  void
	 */
	private function serviceTask()
	{
		$response = new stdClass();
		$response->component = 'collections';
		$response->tasks = array(
			'collections' => array(
				'description' => Lang::txt('Get a list of collections.'),
				'parameters'  => array(
					'sort_Dir' => array(
						'description' => Lang::txt('Direction to sort results by.'),
						'type'        => 'string',
						'default'     => 'desc',
						'accepts'     => array('asc', 'desc')
					),
					'search' => array(
						'description' => Lang::txt('A word or phrase to search for.'),
						'type'        => 'string',
						'default'     => 'null'
					),
					'limit' => array(
						'description' => Lang::txt('Number of result to return.'),
						'type'        => 'integer',
						'default'     => '25'
					),
					'limitstart' => array(
						'description' => Lang::txt('Number of where to start returning results.'),
						'type'        => 'integer',
						'default'     => '0'
					),
				),
			),
			'posts' => array(
				'description' => Lang::txt('Get a list of posts.'),
				'parameters'  => array(
					'collection' => array(
						'description' => Lang::txt('ID of the collection to retrieve posts for.'),
						'type'        => 'integer',
						'default'     => '0'
					),
					'sort_Dir' => array(
						'description' => Lang::txt('Direction to sort results by.'),
						'type'        => 'string',
						'default'     => 'desc',
						'accepts'     => array('asc', 'desc')
					),
					'search' => array(
						'description' => Lang::txt('A word or phrase to search for.'),
						'type'        => 'string',
						'default'     => 'null'
					),
					'limit' => array(
						'description' => Lang::txt('Number of result to return.'),
						'type'        => 'integer',
						'default'     => '25'
					),
					'limitstart' => array(
						'description' => Lang::txt('Number of where to start returning results.'),
						'type'        => 'integer',
						'default'     => '0'
					),
				),
			),
		);

		$this->setMessageType(Request::getWord('format', 'json'));
		$this->setMessage($response);
	}

	/**
	 * Displays a list of tags
	 *
	 * @return    void
	 */
	private function postsTask()
	{
		$this->setMessageType(Request::getWord('format', 'json'));

		$model = new \Component\Collections\Models\Collection();

		$filters = array(
			'limit'      => Request::getInt('limit', 25),
			'start'      => Request::getInt('limitstart', 0),
			'search'     => Request::getVar('search', ''),
			'sort'       => 'p.created',
			'state'      => 1,
			'sort_Dir'   => strtoupper(Request::getWord('sortDir', 'DESC')),
			'is_default' => 0
		);

		if ($collection = Request::getInt('collection', 0))
		{
			$filters['collection_id'] = $collection;
		}

		$response = new stdClass;
		$response->posts = array();

		$filters['count'] = true;

		$response->total = $model->posts($filters);

		if ($response->total)
		{
			$href = 'index.php?option=com_collections&controller=media&post=';
			$base = str_replace('/api', '', rtrim(Request::base(), '/'));

			$filters['count'] = false;

			foreach ($model->posts($filters) as $i => $entry)
			{
				$item = $entry->item();

				$obj = new stdClass;
				$obj->id        = $entry->get('id');
				$obj->title     = $entry->get('title', $item->get('title'));
				$obj->type      = $item->get('type');;
				$obj->posted    = $entry->get('created');
				$obj->author    = $entry->creator()->get('name');
				$obj->url       = $base . '/' . ltrim(Route::url($entry->link()), '/');

				$obj->tags      = $item->tags('string');
				$obj->comments  = $item->get('comments', 0);
				$obj->likes     = $item->get('positive', 0);
				$obj->reposts   = $item->get('reposts', 0);
				$obj->assets    = array();

				$assets = $item->assets();
				if ($assets->total() > 0)
				{
					foreach ($assets as $asset)
					{
						$a = new stdClass;
						$a->title       = ltrim($asset->get('filename'), '/');
						$a->description = $asset->get('description');
						$a->url         = ($asset->get('type') == 'link' ? $asset->get('filename') : $base . '/' . ltrim(Route::url($href . $entry->get('id') . '&task=download&file=' . $a->title), '/'));

						$obj->assets[] = $a;
					}
				}

				$response->posts[] = $obj;
			}
		}

		$response->success = true;

		$this->setMessage($response);
	}

	/**
	 * Displays a list of tags
	 *
	 * @return    void
	 */
	private function collectionsTask()
	{
		$this->setMessageType(Request::getWord('format', 'json'));

		$model = new \Components\Collections\Models\Archive();

		$filters = array(
			'limit'      => Request::getInt('limit', 25),
			'start'      => Request::getInt('limitstart', 0),
			'search'     => Request::getVar('search', ''),
			'state'      => 1,
			'sort_Dir'   => strtoupper(Request::getWord('sortDir', 'DESC')),
			'is_default' => 0,
			'access'     => 0
		);

		$response = new stdClass;
		$response->collections = array();

		$filters['count'] = true;

		$response->total = $model->collections($filters);

		if ($response->total)
		{
			$base = str_replace('/api', '', rtrim(Request::base(), '/'));

			$filters['count'] = false;

			foreach ($model->collections($filters) as $i => $entry)
			{
				$collection = \Components\Collections\Models\Collection::getInstance($entry->item()->get('object_id'));

				$obj = new stdClass;
				$obj->id          = $entry->get('id');
				$obj->title       = $entry->get('title', $collection->get('title'));
				$obj->description = $entry->description('clean'); //get('description', $collection->get('description'));
				$obj->type        = 'collection';
				$obj->posted      = $entry->created();
				$obj->author      = $entry->creator('name');
				$obj->url         = $base . '/' . ltrim(Route::url($collection->link()), '/');

				$obj->files       = $collection->count('file');
				$obj->links       = $collection->count('link');
				$obj->collections = $collection->count('collection');

				$response->collections[] = $obj;
			}
		}

		$response->success = true;

		$this->setMessage($response);
	}

	/**
	 * Displays a list of tags
	 *
	 * @return    void
	 */
	private function collectionTask()
	{
		$this->postsTask();
	}

	/**
	 * Displays a list of tags
	 *
	 * @return    void
	 */
	private function postTask()
	{
		$this->setMessageType(Request::getWord('format', 'json'));

		$response = new stdClass;
		$response->success = true;

		$this->setMessage($response);
	}
}

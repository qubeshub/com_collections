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

namespace Components\Collections\Site\Controllers;

use Components\Collections\Models\Collection;
use Components\Collections\Models\Archive;
use Components\Collections\Models\Post;
use Components\Collections\Models\Item;
use Components\Collections\Tables;
use Hubzero\Component\SiteController;
use Hubzero\Item\Comment;
use Pathway;
use Request;
use Route;
use Lang;
use User;

/**
 * Controller class for collection posts
 */
class Posts extends SiteController
{
	/**
	 * Determines task being called and attempts to execute it
	 *
	 * @return	void
	 */
	public function execute()
	{
		$this->model = Archive::getInstance();

		$this->registerTask('comment', 'post');

		parent::execute();
	}

	/**
	 * Display a post
	 *
	 * @return  string
	 */
	public function displayTask()
	{
		$this->view->config  = $this->config;
		$this->view->model   = $this->model;
		$this->view->no_html = Request::getInt('no_html', 0);

		$post_id = Request::getInt('post', 0);

		$this->view->post = Post::getInstance($post_id);

		if (!$this->view->post->exists())
		{
			App::redirect(
				Route::url('index.php?option=' . $this->option . '&controller=collections&task=posts')
			);
			return;
		}

		$this->view->collection = $this->model->collection($this->view->post->get('collection_id'));

		// Push error messages ot the view
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		$this->view
			->setLayout('display')
			->display();
	}

	/**
	 * Display a form for editing an entry
	 *
	 * @return  string
	 */
	public function editTask()
	{
		// Login is required
		if (User::isGuest())
		{
			return $this->loginTask();
		}

		$this->view->config = $this->config;

		// Incoming
		$this->view->no_html = Request::getInt('no_html', 0);

		$id = Request::getInt('post', 0);

		$this->view->collection = $this->model->collection(Request::getVar('board', 0));

		// Get all collections for a user
		$this->view->collections = $this->model->collections();
		if (!$this->view->collections->total())
		{
			$this->view->collection->setup(User::get('id'), 'member');
			$this->view->collections = $this->model->collections();
			$this->view->collection  = $this->model->collection(Request::getVar('board', 0));
		}

		// Load the post
		$this->view->entry = $this->view->collection->post($id);
		if (!$this->view->collection->exists() && $this->view->entry->exists())
		{
			$this->view->collection = $this->model->collection($this->view->entry->get('collection_id'));
		}

		// Are we removing an asset?
		if ($remove = Request::getInt('remove', 0))
		{
			if (!$this->view->entry->item()->removeAsset($remove))
			{
				$this->view->setError($this->view->entry->item()->getError());
			}
		}

		// If not being called through AJAX
		// push scripts and styles to document
		if (!$this->view->no_html)
		{
			$filters = array(
				'count'   => true,
				'access'  => 0,
				'state'   => 1,
				'user_id' => User::get('id')
			);
			$this->view->counts['collections'] = $this->model->collections($filters);
			$this->view->counts['posts'] = $this->model->posts($filters);
		}

		// Push error messages ot the view
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		// Display
		$this->view
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
		Request::checkToken() or exit('Invalid Token');

		// Login is required
		if (User::isGuest())
		{
			return $this->loginTask();
		}

		// Incoming
		$fields = Request::getVar('fields', array(), 'post', 'none', 2);

		// Get model
		$row = new Item();

		// Bind content
		if (!$row->bind($fields))
		{
			$this->setError($row->getError());
			return $this->editTask($row);
		}

		// Add some data
		//$row->set('_files', $files);
		$row->set('_assets', Request::getVar('assets', array(), 'post'));
		$row->set('_tags', trim(Request::getVar('tags', '')));
		$row->set('state', 1);

		// Store new content
		if (!$row->store())
		{
			$this->setError($row->getError());
			return $this->editTask($row);
		}

		// Create a post entry linking the item to the board
		$p = Request::getVar('post', array(), 'post');

		// Load a post entry
		$post = new Post($p['id']);
		if (!$post->exists())
		{
			// No post existed so set some values
			$post->set('item_id', $row->get('id'));
			$post->set('original', 1);
		}

		// Are we creating a new collection for it?
		$coltitle = Request::getVar('collection_title', '', 'post');
		if (!$p['collection_id'] && $coltitle)
		{
			$collection = new Collection();
			$collection->set('title', $coltitle);
			$collection->set('object_id', User::get('id'));
			$collection->set('object_type', 'member');
			$collection->store();

			$p['collection_id'] = $collection->get('id');
		}
		$post->set('collection_id', $p['collection_id']);

		// Set the description
		if (isset($p['description']))
		{
			$post->set('description', $p['description']);
		}

		// Store record
		if (!$post->store())
		{
			$this->setError($post->getError());
		}

		// Check for any errors
		if ($this->getError())
		{
			return $this->editTask($row);
		}

		// Redirect to main listing
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=collections')
		);
	}

	/**
	 * Save a comment
	 *
	 * @return  string
	 */
	public function savecommentTask()
	{
		// Check for request forgeries
		Request::checkToken() or exit('Invalid Token');

		// Ensure the user is logged in
		if (User::isGuest())
		{
			return $this->loginTask();
		}

		// Incoming
		$comment = Request::getVar('comment', array(), 'post', 'none', 2);

		// Instantiate a new comment object and pass it the data
		$row = new Comment($this->database);
		if (!$row->bind($comment))
		{
			$this->setError($row->getError());
			return $this->displayTask();
		}

		// Check content
		if (!$row->check())
		{
			$this->setError($row->getError());
			return $this->displayTask();
		}

		// Store new content
		if (!$row->store())
		{
			$this->setError($row->getError());
			return $this->displayTask();
		}

		$this->displayTask();
	}

	/**
	 * Delete a comment
	 *
	 * @return  string
	 */
	public function deletecommentTask()
	{
		// Ensure the user is logged in
		if (User::isGuest())
		{
			return $this->loginTask();
		}

		// Incoming
		$id = Request::getInt('comment', 0);
		if (!$id)
		{
			return $this->displayTask();
		}

		// Initiate a whiteboard comment object
		$comment = new Comment($this->database);
		$comment->load($id);
		$comment->state = 2;

		// Delete the entry itself
		if (!$comment->store())
		{
			$this->setError($comment->getError());
		}

		// Return the topics list
		return $this->displayTask();
	}

	/**
	 * Vote for an item
	 *
	 * @return  void
	 */
	public function voteTask()
	{
		if (User::isGuest())
		{
			return $this->loginTask();
		}

		// Incoming
		$id = Request::getInt('post', 0);

		// Get the post model
		$post = Post::getInstance($id);

		// Record the vote
		if (!$post->item()->vote())
		{
			$this->setError($post->item()->getError());
		}

		// Display updated item stats if called via AJAX
		$no_html = Request::getInt('no_html', 0);
		if ($no_html)
		{
			echo Lang::txt('COM_COLLECTIONS_NUM_LIKES', $post->item()->get('positive'));
			exit;
		}

		// Display the main listing
		App::redirect(
			Route::url('index.php?option=' . $this->option . '&controller=collections&task=posts')
		);
	}

	/**
	 * Repost an entry
	 *
	 * @return     string
	 */
	public function collectTask()
	{
		if (User::isGuest())
		{
			return $this->loginTask();
		}

		$model = new Archive('member', User::get('id'));

		$no_html = Request::getInt('no_html', 0);

		// No collection ID selected so present repost form
		$repost = Request::getInt('repost', 0);
		if (!$repost)
		{
			// Incoming
			$post_id       = Request::getInt('post', 0);
			$collection_id = Request::getVar('board', 0);

			if (!$post_id && $collection_id)
			{
				$collection = $model->collection($collection_id);

				$item_id       = $collection->item()->get('id');
				$collection_id = $collection->item()->get('object_id');
			}
			else
			{
				$post = Post::getInstance($post_id);

				$item_id = $post->get('item_id');
			}

			$this->view->myboards      = $model->mine();
			$this->view->groupboards   = $model->mine('groups');

			//$this->view->name          = $this->_name;
			$this->view->option        = $this->_option;
			$this->view->no_html       = $no_html;
			$this->view->post_id       = $post_id;
			$this->view->collection_id = $collection_id;
			$this->view->item_id       = $item_id;

			$this->view->display();
			return;
		}

		$collection_title = Request::getVar('collection_title', '');
		$collection_id = Request::getInt('collection_id', 0);
		$item_id       = Request::getInt('item_id', 0);

		if ($collection_title)
		{
			$collection = new Collection();
			$collection->set('title', $collection_title);
			$collection->set('object_id', User::get('id'));
			$collection->set('object_type', 'member');
			if (!$collection->store())
			{
				$this->setError($collection->getError());
			}
			$collection_id = $collection->get('id');
		}

		// Try loading the current collection/post to see
		// if this has already been posted to the collection (i.e., no duplicates)
		$post = new Tables\Post($this->database);
		$post->loadByBoard($collection_id, $item_id);
		if (!$post->get('id'))
		{
			// No record found -- we're OK to add one
			$post->item_id       = $item_id;
			$post->collection_id = $collection_id;
			$post->description   = Request::getVar('description', '');
			if ($post->check())
			{
				$this->setError($post->getError());
			}
			// Store new content
			if (!$post->store())
			{
				$this->setError($post->getError());
			}
		}
		if ($this->getError())
		{
			return $this->getError();
		}

		// Display updated item stats if called via AJAX
		if ($no_html)
		{
			echo Lang::txt('COM_COLLECTIONS_NUM_REPOSTS', $post->getCount(array('item_id' => $post->get('item_id'), 'original' => 0)));
			exit;
		}

		// Display the main listing
		App::redirect(
			Route::url('index.php?option=' . $this->option . '&controller=collections&task=posts')
		);
	}

	/**
	 * Save post reordering
	 *
	 * @return   void
	 */
	public function reorderTask()
	{
		// Check for request forgeries
		Request::checkToken('get') or Request::checkToken() or exit('Invalid Token');

		// Incoming
		$posts = Request::getVar('post', array());

		if (is_array($posts))
		{
			$folder = null;
			$i = 0;

			foreach ($posts as $post)
			{
				$post = intval($post);
				if (!$post)
				{
					continue;
				}

				$row = new Post($post);
				if (!$row->exists())
				{
					continue;
				}
				$row->set('ordering', $i + 1);
				$row->store(false);

				$i++;
			}
		}

		if (!$no_html)
		{
			// Output messsage and redirect
			App::redirect(
				Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller),
				Lang::txt('COM_COLLECTIONS_POSTS_REORDERED')
			);
			return;
		}

		$response = new \stdClass;
		$response->success = 1;
		$response->message = Lang::txt('COM_COLLECTIONS_POSTS_REORDERED');

		echo json_encode($response);
	}

	/**
	 * Get basic metadata for a post
	 *
	 * @return  void
	 */
	public function metadataTask()
	{
		$id = Request::getInt('post', 0);

		$post = new Post($id);

		if (!Request::getInt('no_html', 0))
		{
			// Output messsage and redirect
			App::redirect(
				Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller)
			);
			return;
		}

		$response = new \stdClass;
		$response->id       = $id;
		$response->reposts  = Lang::txt('COM_COLLECTIONS_NUM_REPOSTS', $post->item()->get('reposts', 0));
		$response->comments = Lang::txt('COM_COLLECTIONS_NUM_COMMENTS', $post->item()->get('comments', 0));
		$response->likes    = Lang::txt('COM_COLLECTIONS_NUM_LIKES', $post->item()->get('positive', 0));

		echo json_encode($response);
	}
}

<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

namespace Components\Collections\Site;

use Hubzero\Component\Router\Base;

/**
 * Routing class for the component
 */
class Router extends Base
{
	/**
	 * Build the route for the component.
	 *
	 * @param   array  &$query  An array of URL arguments
	 * @return  array  The URL arguments to use to assemble the subsequent URL.
	 */
	public function build(&$query)
	{
		$segments = array();

		if (!empty($query['controller']))
		{
			//$segments[] = $query['controller'];
			unset($query['controller']);
		}
		if (!empty($query['post']))
		{
			$segments[] = 'post';
			$segments[] = $query['post'];
			unset($query['post']);
		}
		if (!empty($query['board']))
		{
			//$segments[] = 'collection';
			$segments[] = $query['board'];
			unset($query['board']);
		}
		if (!empty($query['task']))
		{
			$segments[] = $query['task'];
			unset($query['task']);
		}
		if (!empty($query['asset']))
		{
			$segments[] = 'asset';
			$segments[] = $query['asset'];
			unset($query['asset']);
		}
		if (!empty($query['file']))
		{
			$segments[] = $query['file'];
			unset($query['file']);
		}

		return $segments;
	}

	/**
	 * Parse the segments of a URL.
	 *
	 * @param   array  &$segments  The segments of the URL to parse.
	 * @return  array  The URL attributes to be used by the application.
	 */
	public function parse(&$segments)
	{
		$vars = array();

		if (empty($segments))
		{
			return $vars;
		}

		if (isset($segments[0]))
		{
			if (is_numeric($segments[0]))
			{
				$vars['board'] = $segments[0];
				$vars['controller'] = 'posts';
				if (isset($segments[1]))
				{
					$vars['task'] = $segments[1];
				}
			}
			else
			{
				$vars['task'] = $segments[0];
				if ($vars['task'] == 'reorder')
				{
					$vars['controller'] = 'posts';
				}
				if (isset($segments[1]))
				{
					if (is_numeric($segments[1]))
					{
						$vars['post'] = $segments[1];
						$vars['controller'] = 'posts';
						if (isset($segments[2]))
						{
							$vars['task'] = $segments[2];
						}
					}
					else if ($segments[1] == 'asset')
					{
						if (isset($segments[2]))
						{
							$vars['asset'] = $segments[2];
						}
						$vars['controller'] = 'media';
					}
				}
			}
		}
		if (isset($segments[3]))
		{
			$vars['file'] = $segments[3];
			$vars['controller'] = 'media';
			$vars['task'] = 'download';
		}

		return $vars;
	}
}

<?php
/**
 * Ridiculously Responsive Social Sharing Buttons for joomla.org
 *
 * @copyright  Copyright (C) 2015 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

// We require com_content's route helper
JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');

/**
 * Ridiculously Responsive Social Sharing Buttons for joomla.org Content Plugin
 *
 * @since  1.0
 */
class PlgContentJoomlarrssb extends JPlugin
{
	/**
	 * Application object
	 *
	 * @var    JApplicationCms
	 * @since  1.0
	 */
	protected $app;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Database object
	 *
	 * @var    JDatabaseDriver
	 * @since  1.0
	 */
	protected $db;

	/**
	 * Flag if the category has been processed
	 *
	 * Since Joomla lacks a plugin event specifically for category related data, we must process this ourselves using the
	 * available data from the request.
	 *
	 * @var    boolean
	 * @since  1.1
	 */
	private static $hasProcessedCategory = false;

	/**
	 * Listener for the `onContentAfterTitle` event
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object.  Note $article->text is also available
	 * @param   object   &$params   The article params
	 * @param   integer  $page      The 'page' number
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function onContentAfterTitle($context, &$article, &$params, $page)
	{
		/*
		 * Validate the plugin should run in the current context
		 */
		$document = JFactory::getDocument();

		// Context check - This only works for com_content
		if (strpos($context, 'com_content') === false)
		{
			return;
		}

		// Check if the plugin is enabled
		if (JPluginHelper::isEnabled('content', 'joomlarrssb') == false)
		{
			return;
		}

		// Make sure the document is an HTML document
		if ($document->getType() != 'html')
		{
			return;
		}

		/*
		 * Start processing the plugin event
		 */

		// Set the parameters
		$displayEmail       = $this->params->get('displayEmail', '1');
		$displayFacebook    = $this->params->get('displayFacebook', '1');
		$displayGoogle      = $this->params->get('displayGoogle', '1');
		$displayLinkedin    = $this->params->get('displayLinkedin', '1');
		$displayPinterest   = $this->params->get('displayPinterest', '1');
		$displayTwitter     = $this->params->get('displayTwitter', '1');
		$selectedCategories = $this->params->def('displayCategories', '');
		$position           = $this->params->def('displayPosition', 'top');
		$view               = $this->app->input->getCmd('view', '');
		$shorten            = $this->params->def('useYOURLS', true);

		// Check whether we're displaying the plugin in the current view
		if ($this->params->get('view' . ucfirst($view), '1') == '0')
		{
			return;
		}

		// Check that we're actually displaying a button
		if ($displayEmail == '0' && $displayFacebook == '0' && $displayGoogle == '0' && $displayLinkedin == '0' && $displayPinterest == '0' && $displayTwitter == '0')
		{
			return;
		}

		// If we're not in the article view, we have to get the full $article object ourselves
		if ($view == 'featured' || $view == 'category')
		{
			/*
			 * We only want to handle com_content items; if this function returns null, there's no DB item
			 * Also, make sure the object isn't already loaded and undo previous plugin processing
			 */
			$data = $this->loadArticle($article);

			if ((!is_null($data)) && (!isset($article->catid)))
			{
				$article = $data;
			}
		}

		// Make sure we have a category ID, otherwise, end processing
		$properties = get_object_vars($article);

		if (!array_key_exists('catid', $properties))
		{
			return;
		}

		// Get the current category
		if (is_null($article->catid))
		{
			$currentCategory = 0;
		}
		else
		{
			$currentCategory = $article->catid;
		}

		// Define category restrictions
		if (is_array($selectedCategories))
		{
			$categories = $selectedCategories;
		}
		elseif ($selectedCategories == '')
		{
			$categories = [$currentCategory];
		}
		else
		{
			$categories = [$selectedCategories];
		}

		// If we aren't in a defined category, exit
		if (!in_array($currentCategory, $categories))
		{
			// If we made it this far, we probably deleted the text object; reset it
			if (!isset($article->text))
			{
				$article->text = $article->introtext;
			}

			return;
		}

		// Create the article slug
		$article->slug = $article->alias ? ($article->id . ':' . $article->alias) : $article->id;

		// Build the URL for the plugins to use
		$siteURL = substr(JUri::root(), 0, -1);
		$itemURL = $siteURL . JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));

		// Get the content and merge in the template; first see if $article->text is defined
		if (!isset($article->text))
		{
			$article->text = $article->introtext;
		}

		// Always run this preg_match as the results are also used in the layout
		$pattern = "/<img[^>]*src\=['\"]?(([^>]*)(jpg|gif|JPG|png|jpeg))['\"]?/";
		preg_match($pattern, $article->text, $matches);

		/*
		 * Add template metadata per the context
		 */

		// The metadata in this check should only be applied on a single article view
		if ($context === 'com_content.article')
		{
			/*
			 * The Joomla API doesn't support rendering meta tags as <meta property="" />, only <meta name="" />
			 */
			if (!empty($matches))
			{
				$document->addCustomTag('<meta property="og:image" content="' . $siteURL . '/' . $matches[1] . '"/>');
				$document->setMetaData('twitter:image', $siteURL . '/' . $matches[1]);
			}

			$description = !empty($article->metadesc) ? $article->metadesc : $article->introtext;
			$description = JHtml::_('string.truncate', $description, 200, true, false);

			// OpenGraph metadata
			$document->addCustomTag('<meta property="og:description" content="' . $description . '"/>');
			$document->addCustomTag('<meta property="og:title" content="' . $article->title . '"/>');
			$document->addCustomTag('<meta property="og:type" content="article"/>');
			$document->addCustomTag('<meta property="og:url" content="' . $itemURL . '"/>');

			// Twitter Card metadata
			$document->setMetaData('twitter:description', $description);
			$document->setMetaData('twitter:title', JHtml::_('string.truncate', $article->title, 70, true, false));
		}

		// Apply our shortened URL if configured
		if ($shorten)
		{
			$data     = [
				'signature' => $this->params->def('YOURLSAPIKey', '2909bc72e7'),
				'action'    => 'shorturl',
				'url'       => $itemURL,
				'format'    => 'simple'
			];

			try
			{
				$response = JHttpFactory::getHttp()->post($this->params->def('YOURLSUrl', 'http://joom.la') . '/yourls-api.php', $data);

				if ($response->code == 200)
				{
					$itemURL = $response->body;
				}
			}
			catch (Exception $e)
			{
				// In case of an error connecting out here, we can still use the 'real' URL.  Carry on.
			}
		}

		// Load the layout
		ob_start();
		$template = JPluginHelper::getLayoutPath('content', 'joomlarrssb');
		include $template;
		$output = ob_get_clean();

		// Add the output
		if ($position == 'top')
		{
			$article->introtext = $output . $article->introtext;
			$article->text      = $output . $article->text;
		}
		else
		{
			$article->introtext = $output . $article->introtext;
			$article->text .= $output;
		}

		return;
	}

	/**
	 * Listener for the `onContentPrepare` event
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object.  Note $article->text is also available
	 * @param   object   &$params   The article params
	 * @param   integer  $page      The 'page' number
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function onContentPrepare($context, &$article, &$params, $page)
	{
		/*
		 * Validate the plugin should run in the current context
		 */
		$document = JFactory::getDocument();

		// Has the plugin already triggered?
		if (self::$hasProcessedCategory)
		{
			return;
		}

		// Context check - This only works for com_content
		if (strpos($context, 'com_content') === false)
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// Check if the plugin is enabled
		if (JPluginHelper::isEnabled('content', 'joomlarrssb') == false)
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// Make sure the document is an HTML document
		if ($document->getType() != 'html')
		{
			self::$hasProcessedCategory = true;

			return;
		}

		/*
		 * Start processing the plugin event
		 */

		// Set the parameters
		$view = $this->app->input->getCmd('view', '');

		// Check whether we're displaying the plugin in the current view
		if ($this->params->get('view' . ucfirst($view), '1') == '0')
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// The featured view is not yet supported and the article view never will be
		if (in_array($view, ['article', 'featured']))
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// Get the requested category
		/** @var JTableCategory $category */
		$category = JTable::getInstance('Category');
		$category->load($this->app->input->getUint('id'));

		// Build the URL for the plugins to use
		$siteURL = substr(JUri::root(), 0, -1);
		$itemURL = $siteURL . JRoute::_(ContentHelperRoute::getCategoryRoute($category->id));

		// Check if there is a category image to use for the metadata
		$categoryParams = json_decode($category->params, true);

		/*
		 * The Joomla API doesn't support rendering meta tags as <meta property="" />, only <meta name="" />
		 */
		if (isset($categoryParams['image']) && !empty($categoryParams['image']))
		{
			$imageURL = $categoryParams['image'];

			// If the image isn't prefixed with http then assume it's relative and put the site URL in front
			if (strpos($imageURL, 'http') !== 0)
			{
				$imageURL = $siteURL . '/' . $imageURL;
			}

			$document->addCustomTag('<meta property="og:image" content="' . $imageURL . '"/>');
			$document->setMetaData('twitter:image', $imageURL);
		}

		$description = !empty($category->metadesc) ? $category->metadesc : strip_tags($category->description);

		// OpenGraph metadata
		$document->addCustomTag('<meta property="og:title" content="' . $category->title . '"/>');
		$document->addCustomTag('<meta property="og:type" content="article"/>');
		$document->addCustomTag('<meta property="og:url" content="' . $itemURL . '"/>');

		// Twitter Card metadata
		$document->setMetaData('twitter:title', JHtml::_('string.truncate', $category->title, 70, true, false));

		// Add the description too if it isn't empty
		if (!empty($category->description))
		{
			$document->addCustomTag('<meta property="og:description" content="' . $description . '"/>');
			$document->setMetaData('twitter:description', $description);
		}

		// We're done here
		self::$hasProcessedCategory = true;
	}

	/**
	 * Function to retrieve the full article object
	 *
	 * @param   object  $article  The content object
	 *
	 * @return  object  The full content object
	 *
	 * @since   1.0
	 */
	private function loadArticle($article)
	{
		// Query the database for the article text
		$query = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName('#__content'))
			->where($this->db->quoteName('introtext') . ' = ' . $this->db->quote($article->text));
		$this->db->setQuery($query);

		return $this->db->loadObject();
	}
}

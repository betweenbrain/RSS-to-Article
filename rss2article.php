<?php defined('_JEXEC') or die;

/**
 * File       rss2article.php
 * Created    4/17/13 4:48 PM
 * Author     Matt Thomas
 * Website    http://betweenbrain.com
 * Email      matt@betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2013 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v3 or later
 */

jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');

class plgSystemRss2article extends JPlugin {

	function plgSystemRss2article(&$subject, $params) {
		parent::__construct($subject, $params);

		$this->plugin   =& JPluginHelper::getPlugin('system', 'rss2article');
		$this->params   = new JParameter($this->plugin->params);
		$this->interval = $this->params->get('interval');
	}

	function onAfterRoute() {
		$app = JFactory::getApplication();

		if ($app->isSite()) {
			$now  = JFactory::getDate();
			$now  = $now->toUnix();
			$last = $this->params->get('last_run');
			$diff = $now - $last;

			if ($diff > $this->interval) {

				$version = new JVersion();
				define('J_VERSION', $version->getShortVersion());
				jimport('joomla.registry.format');
				$db = JFactory::getDbo();
				$this->params->set('last_run', $now);

				if (J_VERSION >= 1.6) {
					$handler = JRegistryFormat::getInstance('json');
					$params  = new JObject();
					$params->set('interval', $this->params->get('interval', 5));
					$params->set('last_run', $now);
					$params = $handler->objectToString($params, array());
					// Update plugin parameters in database
					$query = 'UPDATE #__extensions' .
						' SET params=' . $db->Quote($params) .
						' WHERE element = ' . $db->Quote('rss2article') .
						' AND folder = ' . $db->Quote('system') .
						' AND enabled >= 1' .
						' AND type =' . $db->Quote('plugin') .
						' AND state >= 0';
					$db->setQuery($query);
					$db->query();
				} else {
					// Retrieve saved parameters from database
					$query = ' SELECT params' .
						' FROM #__plugins' .
						' WHERE element = ' . $db->Quote('rss2article') . '';
					$db->setQuery($query);
					$params = $db->loadResult();
					// Check if last_run parameter has been previously saved.
					if (preg_match('/last_run=/', $params)) {
						// If it has been, update it.
						$params = preg_replace('/last_run=([0-9]*)/', 'last_run=' . $now, $params);
					} else {
						// Add last_run parameter to databse if it has not been recored before.
						// TODO: Currently adding last_run to beginning of param string due to extra "\n" when using $params .=
						$params = 'last_run=' . $now . "\n" . $params;
					}
					// Update plugin parameters in database
					$query = 'UPDATE #__plugins' .
						' SET params=' . $db->Quote($params) .
						' WHERE element = ' . $db->Quote('rss2article') .
						' AND folder = ' . $db->Quote('system') .
						' AND published >= 1';
					$db->setQuery($query);
					$db->query();
				}

				$curl = curl_init();

				curl_setopt_array($curl, Array(
					CURLOPT_URL            => 'http://blogs.guggenheim.org/map/feed/',
					CURLOPT_USERAGENT      => 'spider',
					CURLOPT_TIMEOUT        => 120,
					CURLOPT_CONNECTTIMEOUT => 30,
					CURLOPT_RETURNTRANSFER => TRUE,
					CURLOPT_ENCODING       => 'UTF-8'
				));

				$data = curl_exec($curl);

				curl_close($curl);

				$xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);

				foreach ($xml->channel->item as $item) {
					$creator = $item->children('dc', TRUE);

					$db   = JFactory::getDBO();
					$date = JFactory::getDate($item->pubDate);

					$data                   = new stdClass();
					$data->id               = NULL;
					$data->title            = $db->getEscaped($item->title);
					$data->introtext        = $item->description . ' <p><a href="' . $item->link . '">Permalink</a></p>';
					$data->state            = '1';
					$data->sectionid        = '1';
					$data->catid            = '1';
					$data->created          = $date->toMySQL();
					$data->created_by_alias = $db->getEscaped($creator);
					$data->state            = '1';

					$db->insertObject('#__content', $data, 'id');
				}
			}
		}

		return FALSE;
	}
}
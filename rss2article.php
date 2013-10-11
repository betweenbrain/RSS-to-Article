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

		$this->app      = JFactory::getApplication();
		$this->db       = JFactory::getDbo();
		$this->plugin   = JPluginHelper::getPlugin('system', 'rss2article');
		$this->interval = (int) ($this->params->get('interval', 5) * 60);
	}

	function onAfterRoute() {

		if ($this->pseudoCron()) {
			$feeds = $this->parseParams();

			foreach ($feeds as $feed) {
				$xml = $this->getFeed($feed->url);
				$this->saveItems($xml, $feed->catId, $feed->secId);
			}

			$this->logEvent();
		}
	}

	function pseudoCron() {

		if ($this->app->isSite()) {
			$now  = JFactory::getDate();
			$now  = $now->toUnix();
			$last = $this->params->get('last_run');
			$diff = $now - $last;

			if ($diff > $this->interval) {

				jimport('joomla.registry.format');
				$this->params->set('last_run', $now);

				$handler = JRegistryFormat::getInstance('json');
				$params  = new JObject();
				$params->set('interval', $this->params->get('interval', 5));
				$params->set('last_run', $now);
				$params = $handler->objectToString($params, array());
				// Update plugin parameters in database
				$query = 'UPDATE #__extensions' .
					' SET params=' . $this->db->Quote($params) .
					' WHERE element = ' . $this->db->Quote('rss2article') .
					' AND folder = ' . $this->db->Quote('system') .
					' AND enabled >= 1' .
					' AND type =' . $this->db->Quote('plugin') .
					' AND state >= 0';
				$this->db->setQuery($query);
				$this->db->query();

				return TRUE;
			}
		}

		return FALSE;
	}

	function getFeed($url) {
		$curl = curl_init();

		curl_setopt_array($curl, Array(
			CURLOPT_URL            => $url,
			CURLOPT_USERAGENT      => 'spider',
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_ENCODING       => 'UTF-8'
		));

		$data = curl_exec($curl);

		curl_close($curl);

		$xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);

		return $xml;
	}

	function parseParams() {
		$configuration = $this->params->get('configuration');

		// Procdeed only if data has been entered and stored
		if ($configuration) {

			// Normalize input by removing spaces and new lines.
			$configuration = preg_replace('/\n*\s*/', '', $configuration);

			// Convert string into an array.
			$configurations = explode(';', rtrim($configuration, ';'));

			// Initialize emptpy object.
			$feed = new stdClass();

			foreach ($configurations as $key => $value) {
				// Explode string into parts.
				$parts = explode(',', $value);

				// First part is assumed to be a URL.
				// TODO: Do we need to test this?
				$feed->$key->url = $parts[0];

				// Check for third argument, is none, it's a category.
				if (!$parts[2]) {
					$feed->$key->catId = $parts[1];
				} else {
					// If there is a third argument, we have a section and category.
					$feed->$key->secId = $parts[1];
					$feed->$key->catId = $parts[2];
				}
			}

			if ($feed) {
				return $feed;
			}
		}

		return FALSE;
	}

	function saveItems($xml, $catId, $secId = NULL) {

		$query = "SELECT title
				  FROM #__content
				  WHERE catid = $catId
				  AND state = 1";
		$this->db->setQuery($query);
		$articles = $this->db->loadObjectList();

		foreach ($xml->channel->item as $item) {

			foreach ($articles as $article) {
				if ($article->title == $item->title) {
					$duplicate = TRUE;
				}
			}

			$creator                = $item->children('dc', TRUE);
			$date                   = JFactory::getDate($item->pubDate);
			$data                   = new stdClass();
			$data->id               = NULL;
			$data->title            = $this->db->getEscaped($item->title);
			$data->alias            = JFilterOutput::stringURLSafe($item->title);
			$data->introtext        = $item->description . ' <p><a href="' . $item->link . '">Permalink</a></p>';
			$data->catid            = $catId;
			$data->created          = $date->toMySQL();
			$data->created_by_alias = $this->db->getEscaped($creator);
			$data->state            = '1';
			if ($secId) {
				$data->sectionid = $secId;
			}

			if ($duplicate != TRUE) {
				$this->db->insertObject('#__content', $data, 'id');
			}
		}
	}

	function logEvent() {

		$query = "CREATE TABLE IF NOT EXISTS " . $this->db->nameQuote('#__rss2article') . "
			(" . $this->db->nameQuote('last_run') . "
			datetime NOT NULL DEFAULT '0000-00-00 00:00:00')
			ENGINE=MyISAM DEFAULT CHARSET=utf8;";

		$this->db->setQuery($query);
		$this->db->query();

		$data           = new stdClass();
		$now            = JFactory::getDate()->toMySQL();
		$data->last_run = $now;
		$this->db->insertObject('#__rss2article', $data);
	}
}

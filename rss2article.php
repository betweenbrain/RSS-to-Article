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
				$this->saveItems($xml, $feed->catId);
			}

			$this->logEvent();
		}
	}

	function pseudoCron() {

		if ($this->app->isSite()) {
			$now = JFactory::getDate()->toUnix();

			$query = $this->db->getQuery(TRUE);

			$query
				->select($this->db->quoteName('last_run'))
				->from($this->db->quoteName('#__rss2article'))
				->setLimit('1')
				->order($this->db->quoteName('id') . ' DESC');

			$this->db->setQuery($query);

			$last_run = $this->db->loadResult();

			$last = JFactory::getDate($last_run)->toUnix();

			$diff = $now - $last;

			if ($diff > $this->interval) {

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

		if ($configuration) {

			$configuration = preg_replace('/\n*\s*/', '', $configuration);

			$configurations = explode(';', rtrim($configuration, ';'));

			$feed = new stdClass();

			foreach ($configurations as $key => $value) {

				$feed->$key = new stdClass();

				$parts = explode(',', $value);

				$feed->$key->url   = $parts[0];
				$feed->$key->catId = $parts[1];
			}

			if ($feed) {
				return $feed;
			}
		}

		return FALSE;
	}

	function saveItems($xml, $catId) {

		/*
		$query = "SELECT title
				  FROM #__content
				  WHERE catid = $catId
				  AND state = 1";
		*/

		$query = $this->db->getQuery(TRUE);

		$query
			->select($this->db->quoteName('title'))
			->from($this->db->quoteName('#__content'))
			->where(
				$this->db->quoteName('catid') . ' = ' . $catId . ' AND ' .
				$this->db->quoteName('state') . ' = 1');

		$this->db->setQuery($query);

		$articles = $this->db->loadObjectList();

		foreach ($xml->channel->item as $item) {

			$duplicate = FALSE;

			foreach ($articles as $article) {
				if ($article->title == $item->title) {
					$duplicate = TRUE;
				}
			}

			$creator = $item->children('dc', TRUE);
			$date    = JFactory::getDate($item->pubDate);

			$data                   = new stdClass();
			$data->title            = (string) $item->title[0];
			$data->alias            = JFilterOutput::stringURLSafe($item->title);
			$data->introtext        = $item->description . ' <p><a href="' . $item->link . '">Permalink</a></p>';
			$data->catid            = $catId;
			$data->created          = $date->toSQL();
			$data->created_by_alias = (string) $creator;
			$data->state            = '1';

			if (!$duplicate) {
				$this->db->insertObject('#__content', $data);
			}
		}
	}

	function logEvent() {

		$data           = new stdClass();
		$now            = JFactory::getDate()->toSQL();
		$data->last_run = $now;
		$this->db->insertObject('#__rss2article', $data);
	}
}

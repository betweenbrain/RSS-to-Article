<?php

/**
 * File            script.php
 * Created        10/15/13 10:14 PM
 * Author        Matt Thomas matt@betweenbrain.com
 * Copyright    Copyright (C) 2013 betweenbrain llc.
 */

class plgsystemrss2articleInstallerScript {
	/**
	 * Constructor
	 *
	 * @param   JAdapterInstance $adapter  The object responsible for running this script
	 */
	public function __construct(JAdapterInstance $adapter) {
		$this->db = JFactory::getDbo();
	}

	/**
	 * Called before any type of action
	 *
	 * @param   string $route              Which action is happening (install|uninstall|discover_install|update)
	 * @param   JAdapterInstance $adapter  The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function preflight($route, JAdapterInstance $adapter) {
	}

	/**
	 * Called after any type of action
	 *
	 * @param   string $route              Which action is happening (install|uninstall|discover_install|update)
	 * @param   JAdapterInstance $adapter  The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function postflight($route, JAdapterInstance $adapter) {

		/**
		 * Called on installation
		 *
		 * @param   JAdapterInstance $adapter  The object responsible for running this script
		 *
		 * @return  boolean  True on success
		 */

		$query = 'CREATE TABLE IF NOT EXISTS' . $this->db->quoteName('#__rss2article') . ' (
			' . $this->db->quoteName('id') . ' INT NOT NULL AUTO_INCREMENT,
			' . $this->db->quoteName('last_run') . ' datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
		PRIMARY KEY (ID)
		) COMMENT=""';

		$this->db->setQuery($query);
		$this->db->query();
	}

	public function install(JAdapterInstance $adapter) {
	}

	/**
	 * Called on update
	 *
	 * @param   JAdapterInstance $adapter  The object responsible for running this script
	 *
	 * @return  boolean  True on success
	 */
	public function update(JAdapterInstance $adapter) {
	}

	/**
	 * Called on uninstallation
	 *
	 * @param   JAdapterInstance $adapter  The object responsible for running this script
	 */
	public function uninstall(JAdapterInstance $adapter) {
	}
}
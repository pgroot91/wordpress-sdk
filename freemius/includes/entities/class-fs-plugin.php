<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Plugin extends FS_Scope_Entity {
		public $slug;
		public $file;
		public $version;
		public $auto_update;

		static function get_type()
		{
			return 'plugin';
		}
	}
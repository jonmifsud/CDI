<?php

	define('BLUEPRINTS_INDEX',200,false);
	define('CDIROOT',MANIFEST . '/cdi',false);
	define('CDI_FILENAME','cdi.sql');
	define('CDI_FILE', CDIROOT . '/' . CDI_FILENAME);
	define('CDI_DB_SYNC_FILENAME', 'db_sync.sql');
	define('CDI_DB_SYNC_FILE', CDIROOT . '/' . CDI_DB_SYNC_FILENAME);
	define('CDI_BACKUP_ROOT', CDIROOT . '/export');
	define('CDI_BACKUP_FILE', CDI_BACKUP_ROOT . '/%scdi-db-backup.sql');

	class CdiUtil {
		
		public static function isEnabled() {
			return (Symphony::Configuration()->get('enabled', 'cdi') == 'yes');
		}
		
		public static function isCdi() {
			return (Symphony::Configuration()->get('cdi-mode', 'cdi') == 'cdi');
		}
		
		public static function isCdiSlave() {
			return (Symphony::Configuration()->get('mode', 'cdi') == 'CdiSlave');
		}

		public static function isCdiMaster() {
			return (Symphony::Configuration()->get('mode', 'cdi') == 'CdiMaster');
		}
		
		public static function isCdiDBSync() {
			return (Symphony::Configuration()->get('cdi-mode', 'cdi') == 'db_sync');
		}
		
		public static function isCdiDBSyncMaster() {
			return (Symphony::Configuration()->get('mode', 'cdi') == 'CdiDBSyncMaster');
		}

		public static function isCdiDBSyncSlave() {
			return (Symphony::Configuration()->get('mode', 'cdi') == 'CdiDBSyncSlave');
		}
		
		public static function canBeMasterInstance() {
			if(!file_exists(CDIROOT) && is_writable(MANIFEST)) { return true; }
			if(is_writable(CDIROOT)) { return true; }
			else { return false; }
		}
		
		public static function hasDumpDBInstalled() {
			$status = Symphony::ExtensionManager()->fetchStatus(array('handle'=>'dump_db'));
			return ($status[0] == EXTENSION_ENABLED);
		}
		
		public static function hasDisabledBlueprints() {
			return (Symphony::Configuration()->get('disable_blueprints', 'cdi') == 'yes');
		}

		/**
		 * Return the current author.
		 * Only available if the author is logged into Symphony backend.
		 */
		public static function getAuthor() {
			$author = Administration::instance()->Author;
			if (isset($author)) { 
				return $author->getFullName(); 
			} else { 
				return ""; 
			}
		}
		
		/**
		 * Return the current URL from which the query is executed.
		 * Only available if the query is executed from the Symphony backend.
		 */
		public static function getURL() {
			$url = Administration::instance()->getCurrentPageURL();
			if (is_null($url)) { $url = ""; }
			return $url;
		}
		
		/**
		 * Return a line of Meta information to append to the query
		 */
		public static function getMetaData() {
			$meta = '-- ' . date('Y-m-d H:i:s', time());
			$meta .= ', ' . self::getAuthor();
			$meta .= ', ' . self::getURL();
			$meta .= ";\n";
			return $meta;
		}
		
		/**
		 * Generate API key for authenticating CDI Slave update request
		 */
		public static function generateKey(){
			return substr(sha1(uniqid()), 0, 10);
		}
		
	}
	
?>
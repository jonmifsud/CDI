<?php 

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdidbsync.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdidumpdb.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdimaster.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdislave.php');

	class CdiPreferences {
		
		/*-------------------------------------------------------------------------
			Public static functions
		-------------------------------------------------------------------------*/	
		
		public static function save() {
			try {
				// cdi-mode
				if(isset($_POST['settings']['cdi']['cdi-mode'])) {
					Symphony::Configuration()->set('cdi-mode', $_POST['settings']['cdi']['cdi-mode'], 'cdi');
				} else {
					return false;
				}
				
				// mode (based on is-slave)
				if(isset($_POST['settings']['cdi']['is-slave'])) {
					if(CdiUtil::isCdi()) {
						Symphony::Configuration()->set('mode', 'CdiSlave', 'cdi');
					} else {
						Symphony::Configuration()->set('mode', 'CdiDBSyncSlave', 'cdi');
					}
				} else {
					if(CdiUtil::isCdi()) {
						Symphony::Configuration()->set('mode', 'CdiMaster', 'cdi');
					} else {
						Symphony::Configuration()->set('mode', 'CdiDBSyncMaster', 'cdi');
					}
				}		
				
				// disable_blueprints
				if(isset($_POST['settings']['cdi']['disable_blueprints'])) {
					Symphony::Configuration()->set('disable_blueprints', 'yes', 'cdi');
				} else {
					Symphony::Configuration()->set('disable_blueprints', 'no', 'cdi');
				}
	
				// backup-enabled
				if(isset($_POST['settings']['cdi']['backup-enabled'])) {
					Symphony::Configuration()->set('backup-enabled', 'yes', 'cdi');
				} else {
					Symphony::Configuration()->set('backup-enabled', 'no', 'cdi');
				}
	
				// backup-overwrite
				if(isset($_POST['settings']['cdi']['backup-overwrite'])) {
					Symphony::Configuration()->set('backup-overwrite', 'yes', 'cdi');
				} else {
					Symphony::Configuration()->set('backup-overwrite', 'no', 'cdi');
				}
	
				// manual-backup-overwrite
				if(isset($_POST['settings']['cdi']['manual-backup-overwrite'])) {
					Symphony::Configuration()->set('manual-backup-overwrite', 'yes', 'cdi');
				} else {
					Symphony::Configuration()->set('manual-backup-overwrite', 'no', 'cdi');
				}
				
				// restore-enabled
				if(isset($_POST['settings']['cdi']['restore-enabled'])) {
					Symphony::Configuration()->set('restore-enabled', 'yes', 'cdi');
				} else {
					Symphony::Configuration()->set('restore-enabled', 'no', 'cdi');
				}
	
				// maintenance-enabled
				if(isset($_POST['settings']['cdi']['maintenance-enabled'])) {
					Symphony::Configuration()->set('maintenance-enabled', 'yes', 'cdi');
				} else {
					Symphony::Configuration()->set('maintenance-enabled', 'no', 'cdi');
				}
				
				// save configuration
				return Symphony::Configuration()->write();
			} catch(Exception $e) {
				Administration::instance()->Page->pageAlert(_('An error occurred while saving preferences for CDI: ') . $e->getMessage());
				Symphony::Log()->pushToLog('[CDI] ' . $e->getMessage(), E_ERROR, true);
				return false;
			}
		}
		
		public static function appendCdiMode() {
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Continuous Integration Mode',array('style' => 'margin-bottom: 5px;')));
			$options = array();
			$options[] = array('cdi', (CdiUtil::isCdiMaster() || CdiUtil::isCdiSlave()), 'Continuous Database Integration');
			$options[] = array('db_sync', (CdiUtil::isCdiDBSync()), 'Database Synchroniser');
			$div->appendChild(Widget::Select('settings[cdi][cdi-mode]', $options, array('class' => 'cdi-mode', 'style' => 'width: 250px;margin-bottom: 12px;')));
			if(CdiUtil::isCdiMaster() || CdiUtil::isCdiSlave()) {
				$div->appendChild(new XMLElement('p', 'Each individual query is stored to disk in order of execution and can be automatically executed on a slave instance. The CDI extension will register which queries have been executed to prevent duplicate execution.', array('class' => 'help', 'style' => 'margin-bottom: 10px;')));
			} else if(CdiUtil::isCdiDBSync()) {
				$div->appendChild(new XMLElement('p', 'All queries are stored to disk in a single file. The generated SQL file needs to be manually executed on each slave instance and flushed after upgrading to prevent duplicate execution.', array('class' => 'help', 'style' => 'margin-bottom: 10px;')));
			}
			$div->appendChild(new XMLElement('p', 'You need to save your changes before you can configure this mode, or reload the page to cancel. <br />Be advised: changing CDI mode will reset any mode specific configuration settings.', array('class' => 'cdiModeRestart', 'style' => 'display:none;')));
			return $div;
		}
		
		public static function appendCdiPreferences() {
			$header = new XMLElement('div',null, array('class' => 'cdiHeader'));
			$main = new XMLElement('div',null,array('class' => 'group'));
			$leftColumn = new XMLElement('div',null);
			$rightColumn = new XMLElement('div',null);
			$footer = new XMLElement('div',null, array('class' => 'cdiFooter'));
			
			if(CdiUtil::isCdiMaster()) {
				$header->appendChild(self::appendInstanceMode());
				$header->appendChild(self::appendCdiMasterQueries());
				
				if(file_exists(CDI_FILE) && CdiUtil::hasDumpDBInstalled()) {
					$leftColumn->appendChild(self::appendDownloadLog());
					$leftColumn->appendChild(self::appendClearLog());
					$rightColumn->appendChild(self::appendDBExport());
					$rightColumn->appendChild(self::appendRestore());
				} else if(!file_exists(CDI_FILE) && CdiUtil::hasDumpDBInstalled()) {
					$leftColumn->appendChild(self::appendDBExport());
					$rightColumn->appendChild(self::appendRestore());
				} else if(file_exists(CDI_FILE) && !CdiUtil::hasDumpDBInstalled()) {
					$leftColumn->appendChild(self::appendDownloadLog());
					$leftColumn->appendChild(self::appendClearLog());
					$rightColumn->appendChild(self::appendDBExport());
					$rightColumn->appendChild(self::appendRestore());
				} else if(!file_exists(CDI_FILE) && !CdiUtil::hasDumpDBInstalled()) {
					$header->appendChild(self::appendDBExport());
				}
			} else if(CdiUtil::isCdiSlave()) {
				$header->appendChild(self::appendApiKey());
				$leftColumn->appendChild(self::appendInstanceMode());
				$rightColumn->appendChild(self::appendDumpDB());

				if(file_exists(CDI_FILE) && CdiUtil::hasDumpDBInstalled()) {
					$leftColumn->appendChild(self::appendCdiSlaveQueries());
					$leftColumn->appendChild(self::appendClearLog());
					$rightColumn->appendChild(self::appendDBExport());
					$rightColumn->appendChild(self::appendRestore());
				} else if(file_exists(CDI_FILE) && !CdiUtil::hasDumpDBInstalled()) {
					$footer->appendChild(self::appendCdiSlaveQueries());
					$footer->appendChild(self::appendClearLog());
				} else if(!file_exists(CDI_FILE) && CdiUtil::hasDumpDBInstalled()) {
					$leftColumn->appendChild(self::appendCdiSlaveQueries());
					$rightColumn->appendChild(self::appendDBExport());
					$footer->appendChild(self::appendRestore());
				} else if(!file_exists(CDI_FILE) && !CdiUtil::hasDumpDBInstalled()) {
					$footer->appendChild(self::appendCdiSlaveQueries());
				}
			}
				
			// Add sections to preference group
			$cdiMode =  (CdiUtil::isCdiMaster() ? "CdiMaster" : 
						(CdiUtil::isCdiSlave() ? "CdiSlave" :
						(CdiUtil::isCdiDBSyncMaster() ? "DBSyncMaster" :
						(CdiUtil::isCdiDBSyncSlave() ? "DbSyncSlave" : "unknown"))));
			$section = new XMLElement('div',null,array('class' => 'cdi ' . $cdiMode));
				
			$section->appendChild($header);
			$main->appendChild($leftColumn);
			$main->appendChild($rightColumn);
			$section->appendChild($main);			
			$section->appendChild($footer);
			return $section;
		}

		
		public static function appendDBSyncPreferences() {
			$header = new XMLElement('div',null, array('class' => 'cdiHeader'));
			$main = new XMLElement('div',null,array('class' => 'group'));
			$leftColumn = new XMLElement('div',null);
			$rightColumn = new XMLElement('div',null);
			$footer = new XMLElement('div',null, array('class' => 'cdiFooter'));
						
				if(CdiUtil::isCdiDBSyncMaster()) {
					if(file_exists(CDI_DB_SYNC_FILE) && CdiUtil::hasDumpDBInstalled()) {
						$header->appendChild(self::appendInstanceMode());
						$leftColumn->appendChild(self::appendDownloadLog());
						$leftColumn->appendChild(self::appendClearLog());
						$rightColumn->appendChild(self::appendDBExport());
						$rightColumn->appendChild(self::appendRestore());
					} else if(file_exists(CDI_DB_SYNC_FILE) && !CdiUtil::hasDumpDBInstalled()) {
						$header->appendChild(self::appendInstanceMode());
						$leftColumn->appendChild(self::appendClearLog());
						$rightColumn->appendChild(self::appendDumpDB());
					} else if(!file_exists(CDI_DB_SYNC_FILE) && CdiUtil::hasDumpDBInstalled()) {
						$leftColumn->appendChild(self::appendInstanceMode());
						$rightColumn->appendChild(self::appendDBExport());
						$footer->appendChild(self::appendRestore());
					} else if(!file_exists(CDI_DB_SYNC_FILE) && !CdiUtil::hasDumpDBInstalled()) {
						$leftColumn->appendChild(self::appendInstanceMode());
						$rightColumn->appendChild(self::appendDumpDB());
					}
				} else if(CdiUtil::isCdiDBSyncSlave()) {
					if(file_exists(CDI_DB_SYNC_FILE) && CdiUtil::hasDumpDBInstalled()) {
						$leftColumn->appendChild(self::appendInstanceMode());
						$leftColumn->appendChild(self::appendDBSyncImport());
						$leftColumn->appendChild(self::appendDBSyncImportFile());
						$leftColumn->appendChild(self::appendClearLog());
						$rightColumn->appendChild(self::appendDumpDB());
						$rightColumn->appendChild(self::appendDBExport());
						$rightColumn->appendChild(self::appendRestore());
					} else if(file_exists(CDI_DB_SYNC_FILE) && !CdiUtil::hasDumpDBInstalled()) {
						$header->appendChild(self::appendInstanceMode());
						$leftColumn->appendChild(self::appendDBSyncImport());
						$leftColumn->appendChild(self::appendDBSyncImportFile());
						$leftColumn->appendChild(self::appendClearLog());
						$rightColumn->appendChild(self::appendDumpDB());
					} else if(!file_exists(CDI_DB_SYNC_FILE) && CdiUtil::hasDumpDBInstalled()) {
						$leftColumn->appendChild(self::appendInstanceMode());
						$leftColumn->appendChild(self::appendDBSyncImport());
						$leftColumn->appendChild(self::appendDBSyncImportFile());
						$rightColumn->appendChild(self::appendDumpDB());
						$rightColumn->appendChild(self::appendDBExport());
						$footer->appendChild(self::appendRestore());
					} else if(!file_exists(CDI_DB_SYNC_FILE) && !CdiUtil::hasDumpDBInstalled()) {
						$header->appendChild(self::appendInstanceMode());
						$leftColumn->appendChild(self::appendDBSyncImport());
						$leftColumn->appendChild(self::appendDBSyncImportFile());
						$rightColumn->appendChild(self::appendDumpDB());
					}
				}

			// Add sections to preference group
			$cdiMode =  (CdiUtil::isCdiMaster() ? "CdiMaster" : 
						(CdiUtil::isCdiSlave() ? "CdiSlave" :
						(CdiUtil::isCdiDBSyncMaster() ? "DBSyncMaster" :
						(CdiUtil::isCdiDBSyncSlave() ? "DBSyncSlave" : "unknown"))));
			$section = new XMLElement('div',null,array('class' => 'db_sync ' . $cdiMode));
				
			$section->appendChild($header);
			$main->appendChild($leftColumn);
			$main->appendChild($rightColumn);
			$section->appendChild($main);			
			$section->appendChild($footer);
			return $section;
		}
		
		public static function appendInstanceMode() {
			$div = new XMLElement('div', NULL, array('class' => 'instanceMode'));
			$div->appendChild(new XMLElement('h3','Instance Mode',array('style' => 'margin: 5px 0;')));
			$label = Widget::Label();
			if(!CdiUtil::isCdiSlave() && !CdiUtil::isCdiDBSyncSlave()) {
				$label->setAttribute('style','position:relative;padding-left:18px;');
			} else {
				$label->setAttribute('style','margin-bottom: 2px;position:relative;padding-left:18px;');
			}
			$input = Widget::Input('settings[cdi][is-slave]', 'yes', 'checkbox');
			$input->setAttribute('style','position:absolute;left:0px;');
			$input->setAttribute('class','instance-mode');
			if(CdiUtil::canBeMasterInstance()) {
				if(CdiUtil::isCdiSlave() || CdiUtil::isCdiDBSyncSlave()) { $input->setAttribute('checked', 'checked'); }
				$label->setValue($input->generate() . ' This is a "Slave" instance (no structural changes will be registered)');
			} else {
				$input->setAttribute('checked', 'checked');
				$input->setAttribute('disabled', 'disabled');
				$label->setValue($input->generate() . ' This can only be a "Slave" instance due to insufficient write permissions.');
			}
			$div->appendChild($label);
			if(CdiUtil::isCdiSlave() || CdiUtil::isCdiDBSyncSlave())
			{
				$label = Widget::Label();
				$label->setAttribute('style','position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][disable_blueprints]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				if(CdiUtil::hasDisabledBlueprints()) {
					$input->setAttribute('checked', 'checked');
				}
				$label->setValue($input->generate() . ' Disable structural changes on this instance.');
				$div->appendChild($label);
			}
			if(CdiUtil::isCdiMaster() || CdiUtil::isCdiSlave()) {
				$div->appendChild(new XMLElement('p', 'The extension is designed to allow automatic propagation of structural changes between environments in a DTAP setup.
													   It is imperitive that you have a single "Master" instance (usually your development environment). This is important because the auto-increment values need to be exactly the same on each database table in every environment. 
													   Switching between modes is therefore not recommended. If needed, make sure you only switch instance mode after you have ensured that you have restored all databases from the same source and cleared the CDI logs on all instances.', array('class' => 'help')));
			} else if (CdiUtil::isCdiDBSync()) {
				$div->appendChild(new XMLElement('p', 'The extension is designed to allow manual propagation of structural changes between environments in a DTAP setup.
													   It is imperitive that you have a single "Master" instance (usually your development environment). This is important because the auto-increment values need to be exactly the same on each database table in every environment. 
													   Switching between modes is therefore not recommended. If needed, make sure you only switch instance mode after you have ensured that you have restored all databases from the same source.', array('class' => 'help')));
			}
			$div->appendChild(new XMLElement('p', 'You need to save your changes before you can configure this instance, or reload the page to cancel.<br />Be advised: changing instances mode will reset any instance specific configuration settings', array('class' => 'cdiInstanceRestart', 'style' => 'display:none;')));
			return $div;
		}
		
		public static function appendDumpDB() {
			$div = new XMLElement('div', NULL);
			$div->appendChild(new XMLElement('h3','Backup &amp; Restore',array('style' => 'margin: 5px 0;')));

			if(!CdiUtil::hasDumpDBInstalled()) {
				$div->appendChild(new XMLElement('p', 'To enable backup and restore you need to install the <a href="http://symphony-cms.com/download/extensions/view/40986/">Dump DB</a> extension (version 1.10 or higher)'));
			} else {
				// Enable automatic backups
				$label = Widget::Label();
				$label->setAttribute('style','margin-bottom: 4px;position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][backup-enabled]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','backup-enabled');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') == 'yes') { $input->setAttribute('checked', 'checked'); }
				$label->setValue($input->generate() . ' Create an automatic backup prior to executing structural updates');
				$div->appendChild($label);

				// Overwrite existing backup
				$label = Widget::Label();
				$label->setAttribute('style','margin-bottom: 4px;position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][backup-overwrite]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','backup-overwrite');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') != 'yes') { 
					$input->setAttribute('disabled', 'disabled'); 
				} else if(Symphony::Configuration()->get('backup-overwrite', 'cdi') == 'yes') { 
					$input->setAttribute('checked', 'checked'); 
				}
				$label->setValue($input->generate() . ' Overwrite any existing backup file (if unchecked a new backup file is created on each update)');
				$div->appendChild($label);
				
				// Restore backup on failure
				$label = Widget::Label();
				$label->setAttribute('style','margin-bottom: 4px;position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][restore-enabled]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','restore-enabled');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') != 'yes') { 
					$input->setAttribute('disabled', 'disabled'); 
				} else if(Symphony::Configuration()->get('restore-enabled', 'cdi') == 'yes') { 
					$input->setAttribute('checked', 'checked'); 
				}
				$label->setValue($input->generate() . ' Automatically restore the created backup when experiencing failures during update');
				$div->appendChild($label);

				// Backup & Restore in maintenance mode
				$label = Widget::Label();
				$label->setAttribute('style','position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][maintenance-enabled]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','maintenance-enabled');
				if(Symphony::Configuration()->get('backup-enabled', 'cdi') != 'yes') { 
					$input->setAttribute('disabled', 'disabled'); 
				} else if(Symphony::Configuration()->get('maintenance-enabled', 'cdi') == 'yes') { 
					$input->setAttribute('checked', 'checked'); 
				}
				$label->setValue($input->generate() . ' Switch to "Maintenance" mode when performing database updates');
				$div->appendChild($label);
			}
			$div->appendChild(new XMLElement('p', 'It is recommended to enable automatic backup of your Symphony database prior to updating it. 
												   In case of execution errors or data corruption this allows you to quickly revert to a working configuration.', array('class' => 'help')));
			return $div;
		}
		
		public static function appendRestore() {
			$div = new XMLElement('div', NULL,array('style'=>'margin-bottom: 1.5em;','class' => 'cdiRestore'));
			if(CdiUtil::hasDumpDBInstalled()) {
				$div->appendChild(new XMLElement('h3','Restore Symphony database',array('style' => 'margin: 5px 0;')));
				$table = new XMLElement('table', NULL, array('cellpadding' => '0', 'cellspacing' => '0', 'border' => '0', 'style' => 'margin-bottom: 10px;'));
				$files = CdiDumpDB::getBackupFiles();
				if(count($files) > 0) {
					rsort($files);
					foreach($files as $file) {
						$filename = explode('-',$file);
						if($entryCount == 5) { break; }
						$tr = new XMLElement('tr',null);
						$linkbutton = new XMLElement('a',date('d-m-Y H:i:s', (int)$filename[0]),array('href' => URL . '/symphony/extension/cdi/download/?ref=' . $file));
						$tr->appendChild(new XMLElement('td',$linkbutton->generate(),array('width' => '150', 'style' => 'vertical-align:middle;')));
						$tr->appendChild(new XMLElement('td',$filename[1],array('style' => 'vertical-align:middle;')));
						$td = new XMLElement('td',null,array('width' => '75'));
						$button = new XMLElement('input',null, array('value' => 'Restore', 'name' => 'action[cdi_restore]', 'type' => 'button', 'class' => 'cdi_restore_action', 'ref' => $file));
						$td->appendChild($button);
						$tr->appendChild($td);
						$table->appendChild($tr);
						$entryCount++;
					}
				}
				$tr = new XMLElement('tr',null,array('class' => 'cdiNoLastBackupCell'));
				$tr->appendChild(new XMLElement('td','There is no recent Symphony database to restore'));
				if($entryCount != 0) { $tr->setAttribute('style','display: none'); }
				$table->appendChild($tr);
				$div->appendChild($table);

				$uploadContainer = new XMLElement('div',null,array('class' => 'cdiRestoreUpload'));
				if($entryCount != 0) { $uploadContainer->setAttribute('style','display: none'); }
				$span = new XMLElement('span',NULL,array('class' => 'frame'));
				$span->appendChild(new XMLElement('input',NULL,array('name' => 'dumpdb_restore_file', 'type' => 'file')));
				$uploadContainer->appendChild($span);
				
				$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
				$button->appendChild(new XMLElement('input',null,array('value' => 'Upload', 'name' => 'action[dumpdb_restore]', 'type' => 'submit', 'class' => 'cdi_import_action')));
				$button->appendChild(new XMLElement('span','&nbsp;Press "Upload" to restore the Symphony Database.'));
				$uploadContainer->appendChild($button);
				$div->appendChild($uploadContainer);
				
				if($entryCount != 0) {
					$button = new XMLElement('div',NULL,array('style' => 'margin: 0 0 10px 10px;'));
					$button->appendChild(new XMLElement('input', null, array('value' => 'Clear', 'name' => 'action[cdi_clear_restore]', 'type' => 'button', 'class' => 'cdi_clear_restore_action')));
					$button->appendChild(new XMLElement('span','&nbsp;Press "Clear" to remove all Symphony database backups'));
					$div->appendChild($button);
				}
				
				$div->appendChild(new XMLElement('p', 'Restoring a backup of your Symphony database will replace the entire structure and data of this instance. You can use this to synchronize instances, but be carefull to prevent data loss.', array('class' => 'help')));
			}
			return $div;
		}
		
		public static function appendCdiMasterQueries() {
			$div = new XMLElement('div', NULL,array('style'=>'margin-bottom: 1.5em;', 'class' => 'cdiLastQueries'));
			$div->appendChild(new XMLElement('h3','The last 5 queries logged',array('style' => 'margin-bottom: 5px;')));
			$table = new XMLElement('table', NULL, array('cellpadding' => '0', 'cellspacing' => '0', 'border' => '0'));
			$cdiLogEntries = CdiLogQuery::getCdiLogEntries();
			if(count($cdiLogEntries) > 0) {
				rsort($cdiLogEntries);
				foreach($cdiLogEntries as $entry) {
					if($entryCount == 5) { break; }
					$tr = new XMLElement('tr',null);
					$tr->appendChild(new XMLElement('td',date('d-m-Y h:m:s', $entry[0]),array('width' => '150')));
					$tr->appendChild(new XMLElement('td',htmlspecialchars($entry[3])));
					$table->appendChild($tr);
					$entryCount++;
				}
			}
			$tr = new XMLElement('tr',null,array('class' => 'cdiNoLastQueriesCell'));
			if($entryCount != 0) { $tr->setAttribute('style','display:none;'); }
			$tr->appendChild(new XMLElement('td','There are no entries in the CDI log'));
			$table->appendChild($tr);
			
			$div->appendChild($table);
			return $div;
		}
		
		public static function appendCdiSlaveQueries() {
			$div = new XMLElement('div', NULL,array('style'=>'margin-bottom: 1.5em;','class' => 'cdiLastQueries'));
			$div->appendChild(new XMLElement('h3','The last 5 queries executed',array('style' => 'margin-bottom: 5px;')));
			$table = new XMLElement('table', NULL, array('cellpadding' => '0', 'cellspacing' => '0', 'border' => '0'));
			$cdiLogEntries = Symphony::Database()->fetch("SELECT * FROM tbl_cdi_log ORDER BY `date` DESC LIMIT 0,5");
			if(count($cdiLogEntries) > 0) {
				foreach($cdiLogEntries as $entry) {
					$tr = new XMLElement('tr',null);
					$tr->appendChild(new XMLElement('td',$entry['date'],array('width' => '150')));
					$tr->appendChild(new XMLElement('td',$entry['author']),array('width' => '200'));
					$tr->appendChild(new XMLElement('td',$entry['query_hash']));
					$table->appendChild($tr);
					$entryCount++;
				}
			}

			$tr = new XMLElement('tr',null,array('class' => 'cdiNoLastQueriesCell'));
			if($entryCount != 0) { $tr->setAttribute('style','display:none;'); }
			$tr->appendChild(new XMLElement('td','No CDI queries have been executed on this instance'));
			$table->appendChild($tr);

			$div->appendChild($table);
			return $div;
		}

		public static function appendDBSyncImport() {
			$div = new XMLElement('div', NULL, array('class' => 'cdiImport'));
			$div->appendChild(new XMLElement('h3','Import SQL Statements',array('style' => 'margin-bottom: 5px;')));
			
			$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
			$button->appendChild(new XMLElement('input',null,array('value' => 'Import', 'name' => 'action[cdi_import]', 'type' => 'submit', 'class' => 'cdi_import_action')));
			$button->appendChild(new XMLElement('span','&nbsp;Press "Import" to synchronise the Symphony Database.'));
			$div->appendChild($button);
			
			$label = Widget::Label();
			$label->setAttribute('style','margin: -12px 0 12px 62px;position:relative;padding-left:18px;');
			$input = Widget::Input('settings[cdi][deleteSyncFile]', 'yes', 'checkbox');
			$input->setAttribute('style','position:absolute;left:0px;');
			$input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Remove <em>' . CDI_DB_SYNC_FILENAME . '</em> after a succesful import');
			$div->appendChild($label);
			
			$div->appendChild(new XMLElement('p', 'All SQL statements in the Database Synchroniser file will be executed on this Symphony instance. When all statements have been succesfully imported the file will be deleted.', array('class' => 'help')));

			if(!file_exists(CDI_DB_SYNC_FILE)) { $div->setAttribute('style','display: none'); }
			return $div;
		}
		
		public static function appendDBSyncImportFile() {
			$div = new XMLElement('div', NULL,array('class' => 'cdiImportFile'));
			$div->appendChild(new XMLElement('h3','Import SQL Statements',array('style' => 'margin-bottom: 5px;')));

			$span = new XMLElement('span',NULL,array('class' => 'frame'));
			$span->appendChild(new XMLElement('input',NULL,array('name' => 'cdi_import_file', 'type' => 'file')));
			$div->appendChild($span);
			
			$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
			$button->appendChild(new XMLElement('input',null,array('value' => 'Import', 'name' => 'action[cdi_import]', 'type' => 'submit', 'class' => 'cdi_import_action')));
			$button->appendChild(new XMLElement('span','&nbsp;Press "Import" to synchronise the Symphony Database.'));
			$div->appendChild($button);
			
			$div->appendChild(new XMLElement('p', 'All SQL statements in the Database Synchroniser file will be executed on this Symphony instance. When all statements have been succesfully imported the file will be deleted.', array('class' => 'help')));

			if(file_exists(CDI_DB_SYNC_FILE)) { $div->setAttribute('style','display: none'); }
			return $div;
		}
		
		public static function appendDBExport() {
			$div = new XMLElement('div', NULL, array('class' => 'cdiExport'));
			$div->appendChild(new XMLElement('h3','Export current Symphony database',array('style' => 'margin-bottom: 5px;')));
			if(!CdiUtil::hasDumpDBInstalled()) {
				$div->appendChild(new XMLElement('p', 'To enable database export functionality you need to install the <a href="http://symphony-cms.com/download/extensions/view/40986/">Dump DB</a> extension (version 1.10 or higher)'));
			} else {
				$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
				$button->appendChild(new XMLElement('input',null,array('value' => 'Export', 'name' => 'action[cdi_export]', 'type' => 'button', 'class' => 'cdi_export_action')));
				$button->appendChild(new XMLElement('span','&nbsp;Press "Export" to create a full backup of the Symphony Database.'));
				$div->appendChild($button);
	
				
				$label = Widget::Label();
				$label->setAttribute('style','margin: -12px 0 12px 62px;position:relative;padding-left:18px;');
				$input = Widget::Input('settings[cdi][manual-backup-overwrite]', 'yes', 'checkbox');
				$input->setAttribute('style','position:absolute;left:0px;');
				$input->setAttribute('class','manual-backup-overwrite');
				if(Symphony::Configuration()->get('manual-backup-overwrite', 'cdi') == 'yes') { 
					$input->setAttribute('checked', 'checked'); 
				}
				$label->setValue($input->generate() . ' Overwrite existing backup file');
				$div->appendChild($label);
				
				$div->appendChild(new XMLElement('p', 'You can use the export to synchronise your databases between environments. Be advised: this will copy all data. If your production environment has user-generated content you need to be carefull for data loss.', array('class' => 'help')));
			}
			return $div;
		}

		public static function appendDownloadLog() {
			$div = new XMLElement('div',NULL,array('class' => 'cdiDownload'));
			$div->appendChild(new XMLElement('h3','Download Log Entries',array('style' => 'margin-bottom: 5px;')));
			$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
			if(CdiUtil::isCdi()) {
				$linkbutton = new XMLElement('a','Download',array('href' => URL . '/symphony/extension/cdi/download/?ref=' . CDI_FILENAME));
				$button->appendChild(new XMLElement('span','&nbsp;Press ' . $linkbutton->generate() . ' to retrieve a local copy of the CDI log entries'));
				$div->appendChild($button);
				$div->appendChild(new XMLElement('p', 'You can use the "Download" button to retrieve a local copy of the CDI log file. This allows you to manually upload the CDI logs to a Slave instance.', array('class' => 'help')));
			} else {
				$linkbutton = new XMLElement('a','Download',array('href' => URL . '/symphony/extension/cdi/download/?ref=' . CDI_DB_SYNC_FILENAME));
				$button->appendChild(new XMLElement('span','&nbsp;Press ' . $linkbutton->generate() . ' to retrieve a local copy of <em>' . CDI_DB_SYNC_FILENAME . '</em>'));
				$div->appendChild($button);
				$div->appendChild(new XMLElement('p', 'You can use the "Download" button to retrieve a local copy of the current <em>' . CDI_DB_SYNC_FILENAME . '</em> file. 
													   Upload the file to your slave instances to synchronize the Symphony database.', array('class' => 'help')));
			}
			return $div;
		}
		
		public static function appendClearLog() {
			$div = new XMLElement('div',NULL,array('class' => 'cdiClear'));
			$div->appendChild(new XMLElement('h3','Clear Log Entries',array('style' => 'margin-bottom: 5px;')));
			$button = new XMLElement('div',NULL,array('style' => 'margin: 10px 0;'));
			$button->appendChild(new XMLElement('input', null, array('value' => 'Clear', 'name' => 'action[cdi_clear]', 'type' => 'button', 'class' => 'cdi_clear_action')));
			if(CdiUtil::isCdi()) {
				$button->appendChild(new XMLElement('span','&nbsp;Press "Clear" to remove all CDI log entries from disk and/or Symphony Database'));
				$div->appendChild($button);
				$div->appendChild(new XMLElement('p', 'You can use the "Clear" button to clean up old CDI logs. 
														 Ensure that all your Symphony have been updated either by CDI (check the last executed queries list above) or by manually restoring the same database backup on all instances.
														 Make sure that you clear the log files on every instance (including the "Master" instance). It is important that the database schemas are synchronized before starting with a clean sheet.', array('class' => 'help')));
			} else {
				$button->appendChild(new XMLElement('span','&nbsp;Press "Clear" to remove <em>' . CDI_DB_SYNC_FILENAME . '</em> from disk'));
				$div->appendChild($button);
				$div->appendChild(new XMLElement('p', 'You can use the "Clear" button to remove current <em>' . CDI_DB_SYNC_FILENAME . '</em> file. 
													   Ensure that all your Symphony have been updated either by CDI or by manually restoring the same database backup on all instances.
													   Make sure that you clear the <em>' . CDI_DB_SYNC_FILENAME . '</em> files on every instance (including the "Master" instance). It is important that the database schemas are synchronized before starting with a clean sheet.', array('class' => 'help')));
			}
			return $div;
		}		
		
		public static function appendApiKey() {
			$div = new XMLElement('div');
			$div->appendChild(new XMLElement('h3', 'Synchronize CDI Slave', array('style' => 'margin: 5px 0;')));
			$frame = new XMLElement('span', null, array('class' => 'frame'));
			$frame->appendChild(new XMLElement('a', URL . '/symphony/extension/cdi/update/' . Symphony::Configuration()->get('api_key','cdi'), array('href' => URL . '/symphony/extension/cdi/update/' . Symphony::Configuration()->get('api_key','cdi'))));
			$div->appendChild($frame);
			$div->appendChild(new XMLElement('p', 'Use the above URL to update this CDI Slave instance. 
												   There is no extra configuration needed, so it is possible to automate the update process using Curl.', array('class' => 'help')));
			return $div;
		}
		
	}

?>
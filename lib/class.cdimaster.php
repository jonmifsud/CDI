<?php

	require_once(EXTENSIONS . '/cdi/lib/class.cdiutil.php');
	require_once(EXTENSIONS . '/cdi/lib/class.cdilogquery.php');
	
	class CdiMaster {

		private static $lastEntryTS;
		private static $lastEntryOrder;
		private static $meta_written = FALSE;
		
		public static function install() {
			self::uninstall();
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_cdi_log`");
			if (!file_exists(CDIROOT)) { mkdir(CDIROOT); }
		}
		
		public static function uninstall() {
			if(file_exists(CDI_FILE)) { unlink(CDI_FILE); }
		}
		
		/**
		 * Pretty-print JSON string
		 *
		 * Use 'format' option to select output format - currently html and txt supported, txt is default
		 * Use 'indent' option to override the indentation string set in the format - by default for the 'txt' format it's a tab
		 *
		 * @param string $json Original JSON string
		 * @param array $options Encoding options
		 * @return string
		 */
		function json_pretty($json, $options = array())
		{
		    $tokens = preg_split('|([\{\}\]\[,])|', $json, -1, PREG_SPLIT_DELIM_CAPTURE);
		    $result = '';
		    $indent = 0;

		    $format = 'txt';

		    //$ind = "\t";
		    $ind = "    ";

		    if (isset($options['format'])) {
		        $format = $options['format'];
		    }

		    switch ($format) {
		        case 'html':
		            $lineBreak = '<br />';
		            $ind = '&nbsp;&nbsp;&nbsp;&nbsp;';
		            break;
		        default:
		        case 'txt':
		            $lineBreak = "\n";
		            //$ind = "\t";
		            $ind = "    ";
		            break;
		    }

		    // override the defined indent setting with the supplied option
		    if (isset($options['indent'])) {
		        $ind = $options['indent'];
		    }

		    $inLiteral = false;
		    foreach ($tokens as $token) {
		        if ($token == '') {
		            continue;
		        }

		        $prefix = str_repeat($ind, $indent);
		        if (!$inLiteral && ($token == '{' || $token == '[')) {
		            $indent++;
		            if (($result != '') && ($result[(strlen($result) - 1)] == $lineBreak)) {
		                $result .= $prefix;
		            }
		            $result .= $token . $lineBreak;
		        } elseif (!$inLiteral && ($token == '}' || $token == ']')) {
		            $indent--;
		            $prefix = str_repeat($ind, $indent);
		            $result .= $lineBreak . $prefix . $token;
		        } elseif (!$inLiteral && $token == ',') {
		            $result .= $token . $lineBreak;
		        } else {
		            $result .= ( $inLiteral ? '' : $prefix ) . $token;

		            // Count # of unescaped double-quotes in token, subtract # of
		            // escaped double-quotes and if the result is odd then we are 
		            // inside a string literal
		            if ((substr_count($token, "\"") - substr_count($token, "\\\"")) % 2 != 0) {
		                $inLiteral = !$inLiteral;
		            }
		        }
		    }
		    return $result;
		}
		
		/**
		 * If it is proven to be a valid SQL Statement worthy of logging, the persistQuery() function will
		 * write the statement to file and log it
		 * @param string $query The SQL Statement that will be saved to file and CDI log
		 */
		public static function persistQuery($query) {
			try {
				$ts = time();
		
				if(self::$lastEntryTS != $ts) {
					self::$lastEntryTS = $ts;
					self::$lastEntryOrder = 0;
				} else {
					self::$lastEntryOrder++;
				}
				
				$id = $ts . '-' . self::$lastEntryOrder;
				$hash = md5($id . $query);
				$date = date('Y-m-d H:i:s', $ts);
				
				try{
					//We are only logging this to file because we do not execute CDI queries on the MASTER instance
					//The database table `tbl_cdi_log` is removed from the database on the MASTER instance.
					//It is the repsonsibility of the user to ensure that they only have a single MASTER instance 
					//and that they protect the integrity of the Symphony database
					$entries = CdiLogQuery::getCdiLogEntries();
					$entries[$id] = array(0 => $ts, 1 => self::$lastEntryOrder, 2 => $hash, 3 => $query);
					file_put_contents(CDI_FILE, CdiMaster::json_pretty(json_encode($entries)) );
					return true;
				} catch(Exception $e) {
					//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
					CdiLogQuery::rollback($hash,$ts,$order);
					return false;
				}
			} catch(Exception $e) {
				//TODO: think of some smart way of dealing with errors, perhaps through the preference screen or a CDI Status content page?
				return false;
			}
		}
	}
?>
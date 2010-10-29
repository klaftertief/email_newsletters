<?php

	/**
	 * Class: Email Newsletter
	 *
	 * @package Email Newsletters
	 * @author Michael Eichelsdoerfer
	 */
	class EmailNewsletter
	{
		protected $_field_id;
		protected $_entry_id;
		protected $_section_id;
		protected $_prefs;
		protected $_field_data;
		protected $_entry_data;
		protected $_config;
		protected $_log_file;

		/**
		 * Constructor
		 */
		public function __construct($field_id, $entry_id, $domain)
		{
			define('DOMAIN', $domain);
			define('DOCROOT', rtrim(realpath(dirname(__FILE__) . '/../../../'), '/'));
			define('EXTENSIONS', DOCROOT . '/extensions');
			require_once(DOCROOT . '/symphony/lib/boot/func.utilities.php');
			require_once(DOCROOT . '/symphony/lib/boot/defines.php');
			@include_once(BOOT . '/class.object.php'); ## Symphony < 2.0.8
			require_once(CORE . '/class.administration.php');
			$this->_field_id = $field_id;
			$this->_entry_id = $entry_id;
			$this->_section_id = Administration::instance()->Database->fetchVar('parent_section', 0, "SELECT parent_section FROM `tbl_fields` WHERE `id` = $this->_field_id LIMIT 1");
			$this->_prefs = Administration::instance()->Configuration->get('email-newsletters');
			$this->_field_data = Administration::instance()->Database->fetchRow(0, "SELECT * FROM `tbl_fields_email_newsletter` WHERE `field_id` = $this->_field_id LIMIT 1");
			$this->_entry_data = Administration::instance()->Database->fetchRow(0, "SELECT * FROM `tbl_entries_data_".$this->_field_id."` WHERE `entry_id` = $this->_entry_id LIMIT 1");
		}

/*-------------------------------------------------------------------------
	Init
-------------------------------------------------------------------------*/
		/**
		 * Init function - prepare everything and go for it
		 *
		 * @return void
		 */
		public function init()
		{
			$data = array(
				'status' => 'processing',
				'config_xml'   => $this->_field_data['config_xml'],
			);
			$this->__updateEntryData($data);

			$this->_config = simplexml_load_string($this->_entry_data['config_xml']);
			$live_mode = (string)$this->_config->{'live-mode'} == '1' ? true : false;

			## check for sender ID
			$sender_id = $this->_entry_data['sender_id'];
			if(empty($sender_id))
			{
				$this->__exitError(__('ERROR: Sender is missing.'));
			}

			## look up replacement definitions (personalization) in XML
			$replacements = array();
			foreach($this->_config->xpath('//search-strings/item') as $search)
			{
				$replacements[(string)$search] = (string)$search['replacement-node'];
			}

			## load recipient pages and build arrays
			$rec_group_ids = $this->_entry_data['rec_group_ids'];
			if(empty($rec_group_ids))
			{
				$this->__exitError(__('ERROR: Recipient group is missing.'));
			}
			$mailto = array();
			$invalid = array();
			$decorator_replacements = array();
			foreach($this->_config->xpath('//recipients/group') as $group)
			{
				if(in_array($group['id'], explode(',',$rec_group_ids)))
				{
					$recipients_xml = @simplexml_load_string($this->__loadSymphonyPage($group['page-id'], $group['url-appendix']));
					if(!$recipients_xml)
					{
						$this->__exitError('ERROR: The recipients page with page ID ' . $group['id'] . ' could not be loaded as XML.');
					}
					if(!@$recipients_xml->xpath('//'.$group['entry-node']))
					{
						$this->__exitError('ERROR: Recipients page with page ID' . ': ' . $group['id']. ': '.'entry node error');
					}
					if(!@$recipients_xml->xpath('//'.$group['email-node']))
					{
						$this->__exitError('ERROR: Recipients page with page ID' . ': ' . $group['id']. ': '.'email node error');
					}

					## build arrays
					foreach($recipients_xml->xpath('//'.$group['entry-node']) as $entry)
					{
						$email = (string)$entry->$group['email-node'];
						$name = ($entry->$group['name-node'] ? (string)$entry->$group['name-node'] : NULL);
						## don't use invalid email addresses for SwiftMailer - sending would break!
						if(filter_var($email, FILTER_VALIDATE_EMAIL))
						{
							$mailto[$email] = $name;
							## build decorator (personalization) replacements array
							foreach($replacements as $search => $replace)
							{
								$decorator_replacements[$email][$search] = $entry->$group[$replace] ? (string)$entry->$group[$replace] : $search;
							}
						}
						else
						{
							$invalid[$email] = $name;
						}
					}
				}
			}
			$data = array(
				'rec_mailto'       => serialize($mailto),
				'rec_invalid'      => serialize($invalid),
				'rec_replacements' => serialize($decorator_replacements),
				'stats_rec_total'  => count($mailto) + count($invalid),
				'stats_rec_errors' => count($invalid),
			);
			$this->__updateEntryData($data);

			## load content
			$page_html_id           = (string)$this->_config->content->{'page-html'}['page-id'];
			$page_text_id           = (string)$this->_config->content->{'page-text'}['page-id'];
			$page_html_url_appendix = (string)$this->_config->content->{'page-html'}['url-appendix'];
			$page_text_url_appendix = (string)$this->_config->content->{'page-text'}['url-appendix'];
			$content_html = !empty($page_html_id) ? $this->__loadSymphonyPage($page_html_id, $page_html_url_appendix) : NULL;
			$content_text = !empty($page_text_id) ? $this->__loadSymphonyPage($page_text_id, $page_text_url_appendix) : NULL;
			$content_text = html_entity_decode($content_text, ENT_QUOTES, 'UTF-8');

			$data = array(
				'content_html' => $content_html,
				'content_text' => $content_text,
			);
			$this->__updateEntryData($data);

			## create logfile directory
			$directory_config = Administration::instance()->Configuration->get('directory');
			$directory_write_mode = $directory_config['write_mode'];

			$path = DOCROOT . '/manifest/logs/email-newsletters';
			if(!$this->__realiseDirectory($path, $directory_write_mode))
			{
				$this->__exitError('ERROR: Could not create log directory in manifest/logs.');
			}
			## try and keep the logfile unique
			$this->_log_file = $path . '/' . date("Ymd-His") . '-' . uniqid() . '.txt';
			## save logfile path to database
			$this->__updateEntryData(array(
				'log_file' => $this->_log_file,
			));
			## start logging
			if($live_mode !== true)
			{
				$log_message = "LIVE MODE HAS NOT BEEN SET -- NO EMAILS WILL BE SENT

"
				;
				$this->__log($log_message);
			}
			$log_message =
"Email Newsletter Log
====================

" . date('c') . ": Process started.

--- Newsletter Subject START
" . $this->_entry_data['subject'] . "
--- Newsletter Subject END

--- Newsletter HTML START
" . $content_html . "
--- Newsletter HTML END

--- Newsletter TEXT START
" . $content_text . "
--- Newsletter TEXT END

--- Recipients START
" . print_r($mailto, TRUE) . "
--- Recipients END

--- Invalid Recipients START
" . print_r($invalid, TRUE) . "
--- Invalid Recipients END
"
			;
			$this->__log($log_message);

			## send it, but make it sleep first to allow 'last minute cancelling'
			sleep(6);
			$this->send();
		}

/*-------------------------------------------------------------------------
	Send
-------------------------------------------------------------------------*/
		/**
		 * Send function
		 *
		 * @return void
		 */
		public function send()
		{
			$this->_entry_data = Administration::instance()->Database->fetchRow(0, "SELECT * FROM `tbl_entries_data_".$this->_field_id."` WHERE `entry_id` = $this->_entry_id LIMIT 1");

			## vars
			$this->_config   = simplexml_load_string($this->_entry_data['config_xml']);
			$this->_log_file = $this->_entry_data['log_file'];
			$live_mode       = $this->_config->{'live-mode'} == '1' ? true : false;
			$sender_id       = $this->_entry_data['sender_id'];
			$sender          = $this->_config->xpath("senders/item[@id = $sender_id]");
			$status          = $this->_entry_data['status'];

			if($status == 'cancel')
			{
				## update logfile and exit
$log_message = "
--- " . date('c') . ": Process has been cancelled.
"
;
				$this->__log($log_message);
				$this->__exitError(__('ERROR: Process has been cancelled.'));
			}

			$mailer_params['smtp_host']       = (string)$sender[0]['smtp-host'];
			$mailer_params['smtp_port']       = (string)$sender[0]['smtp-port'];
			$mailer_params['smtp_username']   = (string)$sender[0]['smtp-username'];
			$mailer_params['smtp_password']   = (string)$sender[0]['smtp-password'];
			$mailer_params['from_email']      = (string)$sender[0]['from-email'];
			$mailer_params['from_name']       = (string)$sender[0]['from-name'];
			$mailer_params['reply_to_email']  = (string)$sender[0]['reply-to-email'];
			$mailer_params['reply_to_name']   = (string)$sender[0]['reply-to-name'];
			$mailer_params['return_path']     = (string)$sender[0]['return-path'];
			$mailer_params['throttle_number'] = (string)$this->_config->throttling->{"emails-per-time-period"};
			$mailer_params['throttle_period'] = (string)$this->_config->throttling->{"time-period-in-seconds"};

			$mailto                 = unserialize($this->_entry_data['rec_mailto']);
			$decorator_replacements = unserialize($this->_entry_data['rec_replacements']);
			$content_html           = $this->_entry_data['content_html'];
			$content_text           = $this->_entry_data['content_text'];

			## load the SwiftMailer library
			$swiftmailer_location = ($this->_prefs['swiftmailer-location'] ? $this->_prefs['swiftmailer-location'] : 'email_newsletters/lib/swiftmailer');
			require_once(EXTENSIONS . '/' . $swiftmailer_location . '/lib/swift_required.php');

			## create transport
			$transport = Swift_SmtpTransport::newInstance($mailer_params['smtp_host'], $mailer_params['smtp_port'] ? $mailer_params['smtp_port'] : 25)
			  ->setUsername($mailer_params['smtp_username'])
			  ->setPassword($mailer_params['smtp_password'])
			;

			## test transport
			try
			{
			    $transport->start();
			}
			catch(Swift_TransportException $e)
			{
			    $this->__exitError('SwiftMailer exception: ' . $e->getMessage());
			}

			## create mailer instance
			$mailer = Swift_Mailer::newInstance($transport);

			## add decorator (personalization)
			$mailer->registerPlugin(new Swift_Plugins_DecoratorPlugin($decorator_replacements));

			## suppressing a SwiftMailer bug;
			## do not remove this if you don't really know the bug!!!
			Swift_Preferences::getInstance()->setCacheType('null');

			## find recipients 'slice'
			$start = $this->_entry_data['stats_rec_sent'];
			$remaining = count($mailto) - $start;
			$slice_size = intval($mailer_params['throttle_number']) ? intval($mailer_params['throttle_number']) : $remaining;
			$time_period = intval($mailer_params['throttle_period']) ? intval($mailer_params['throttle_period']) : 0;

			$time_start = time();
			$time_end = $time_start + $time_period;
			$mailto_slice = array_slice($mailto, $start, $slice_size);
			$failures = array();

			## create message
			try
			{
				$message = Swift_Message::newInstance()
					->setSubject($this->_entry_data['subject'])
					->setFrom(array($mailer_params['from_email'] => !empty($sender[0]['from-name']) ? (string)$sender[0]['from-name'] : NULL))
					->setTo($mailto_slice)
				;
			}
			catch (Exception $e)
			{
				$this->__log('Caught exception: ' . $e->getMessage());
				$this->__exitError('Caught exception: ' . $e->getMessage());
			}
			if(!empty($content_text)) $message->addPart($content_text, 'text/plain');
			if(!empty($content_html)) $message->addPart($content_html, 'text/html');
			if(!empty($mailer_params['reply_to_email']))
			{
				$headers = $message->getHeaders();
				$headers->addMailboxHeader('Reply-To', array(
					$mailer_params['reply_to_email'] => !empty($sender[0]['reply-to-name']) ? (string)$sender[0]['reply-to-name'] : NULL,
				));
			}
			if(!empty($mailer_params['return_path']))
			{
				$message->setReturnPath($mailer_params['return_path']);
			}

			## send and receive count from $mailer instance if live mode is true (else: simulate count)
			$num_sent = $live_mode ? $mailer->batchSend($message, $failures) : count($mailto_slice);

			## update logfile - failures array will return from $mailer instance
			$log_message = "
--- " . date('c') . ": mailing recipients " . ($start + 1) . " to " . ($start + count($mailto_slice)) . " START

Recipients:
" . print_r($mailto_slice, TRUE) . "
Failures:
" . print_r($failures, TRUE) . "
--- " . date('c') . ": mailing recipients " . ($start + 1) . " to " . ($start + count($mailto_slice)) . " END
"
			;
			$this->__log($log_message);

			## update statistics
			$statistics = array();
			$statistics['stats_rec_total'] = $this->_entry_data['stats_rec_total'];
			$statistics['stats_rec_sent'] = isset($this->_entry_data['stats_rec_sent']) ? $this->_entry_data['stats_rec_sent'] + $num_sent : $num_sent;
			$statistics['stats_rec_errors'] = isset($this->_entry_data['stats_rec_errors']) ? $this->_entry_data['stats_rec_errors'] + count($failures) : count($failures);
			$this->__updateEntryData($statistics);

			$remaining -= $slice_size;
			$time_left = $time_end - time();
			$time_sleep = $time_left > 0 ? $time_left : 0;
			if($remaining > 0)
			{
				sleep($time_sleep);

				## build the command to initiate the background mailer process, like so:
				## php /path/to/your/website/extensions/email_newsletters/lib/init.php 123 5678 www.example.com resume > /dev/null &
				$cmd  = 'env -i php ' . EXTENSIONS . '/email_newsletters/lib/init.php' . ' ';
				$cmd .= $this->_field_id . ' ';
				$cmd .= $this->_entry_id . ' ';
				$cmd .= DOMAIN . ' ';
				$cmd .= 'resume' . ' ';
				$cmd .= '> /dev/null &';
				shell_exec($cmd);
			}
			else
			{
				## update logfile
				$log_message = "
--- Statistics START
" . print_r($statistics, TRUE) . "
--- Statistics END

" . date('c') . ": Process finished.
"
				;
				$this->__log($log_message);

				## set logfile permissions
				$file_config = Administration::instance()->Configuration->get('file');
				$file_write_mode = $file_config['write_mode'];
				chmod($this->_log_file, @intval($file_write_mode, 8));

				## gz-encode logfile
				$this->__gzEncode($this->_log_file);

				## update newsletter status (success) and remove error message (if any)
				$this->__updateEntryData(array('status' => 'sent', 'error_message' => NULL));
			}
			return;
		}

/*-------------------------------------------------------------------------
	Helpers
-------------------------------------------------------------------------*/
		/**
		 * Load page if response header is '200'; else: throw custom error;
		 *
		 * @param string $id - Symphony page ID
		 * @return string - page content (without headers)
		 */
		private function __loadSymphonyPage($page_id, $url_appendix)
		{
			$path = Administration::instance()->resolvePagePath($page_id);
			if(!$path)
			{
				$this->__exitError('ERROR: The page path for page ID ' . $page_id . ' could not be resolved.');
			}
			$url = URL . '/' . $path . '/' . $this->__replaceParamsInString($url_appendix);

			## check for 'admin' page type in tbl_pages_types
			$db_admin_row = Administration::instance()->Database->fetchRow(0, "SELECT * FROM `tbl_pages_types` WHERE `page_id` = '{$page_id}' AND `type` = 'admin' LIMIT 1");
			$page_type_admin = !empty($db_admin_row) ? true : false;

			if($page_type_admin)
			{
				## remove 'admin' page type from tbl_pages_types (i.e. make page readable)
				Administration::instance()->Database->delete('tbl_pages_types', " `page_id` = '{$page_id}' AND `type` = 'admin' ");
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_HEADER, TRUE);
			curl_setopt($ch, CURLOPT_NOBODY, TRUE);
			$header = curl_exec($ch);
			$pos = strpos($header, '200 OK');
			if($pos === FALSE)
			{
				$this->__exitError(__('ERROR: Page load failed.') . ' ID: ' . $page_id);
			}
			curl_close($ch);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_NOBODY, FALSE);
			$page = curl_exec($ch);
			curl_close($ch);

			if($page_type_admin)
			{
				## insert 'admin page type' into tbl_pages_types (i.e. protect page)
				Administration::instance()->Database->insert(
					array(
						'page_id' => ".$page_id.",
						'type' => 'admin'
					),
					'tbl_pages_types'
				);
			}

			return $page;
		}

		/**
		 * Update entry data
		 *
		 * @return
		 **/
		private function __updateEntryData($array)
		{
			Administration::instance()->Database->update($array, 'tbl_entries_data_'.$this->_field_id, "`entry_id` = '".$this->_entry_id."'");
			$this->_entry_data = Administration::instance()->Database->fetchRow(0, "SELECT * FROM `tbl_entries_data_".$this->_field_id."` WHERE `entry_id` = $this->_entry_id LIMIT 1");
			return true;
		}

		/**
		 * Write error and exit
		 *
		 * @return exit
		 **/
		private function __exitError($error_message)
		{
			$this->__updateEntryData(array('status' => 'error', 'error_message' => $error_message));
			exit();
		}

		/**
		 * Realise Directory
		 *
		 * @param string $path
		 * @param string $mode
		 * @return boolean
		 */
		private function __realiseDirectory($path, $mode)
		{
			if(!empty($path))
			{
				if(@file_exists($path) && !@is_dir($path))
				{
					return false;
				}
				elseif(!@is_dir($path))
				{
					@mkdir($path);
					@chmod($path, @intval($mode, 8));
				}
			}
			return true;
		}

		/**
		 * Create log file
		 *
		 * @param string $log_message
		 * @return boolean
		 */
		private function __log($log_message)
		{
			if(!$handle = fopen($this->_log_file, 'ab')) $this->__exitError('ERROR: Could not open log file.');
			if(fwrite($handle, $log_message) === FALSE) $this->__exitError('ERROR: Could not write to log file.');
			fclose($handle);
			return true;
		}

		/**
		 * GZ-encode file (and keep permissions)
		 *
		 * @param string $file - file path
		 * @return boolean
		 */
		private function __gzEncode($file)
		{
			if(function_exists('gzencode'))
			{
				$data = implode("", file($file));
				$gzdata = gzencode($data, 9);
				$gzfile = $file.".gz";
				$fp = fopen($gzfile, "wb");
				$fwrite = fwrite($fp, $gzdata);
				fclose($fp);
				if($fwrite)
				{
					@chmod($gzfile, fileperms($file));
					unlink($file);
					return true;
				}
			}
			return false;
		}

		/**
		 * Replace parameters in string
		 *
		 * @param string $string
		 * @return string $string
		 */
		private function __replaceParamsInString($string)
		{
			$params = $this->__findParamsInString($string);
			if(is_array($params) && !empty($params))
			{
				foreach($params as $value)
				{
					if($value == 'id')
					{
						$string = str_replace('{$'.$value.'}', $this->_entry_id, $string);
					}
					else if($field_id = Symphony::Database()->fetchVar('id', 0, "SELECT id FROM `tbl_fields` WHERE `element_name` = '".$value."' AND `parent_section` = '".$this->_section_id."' LIMIT 1"))
					{
						$field_handle = Symphony::Database()->fetchVar('handle', 0, "SELECT handle FROM `tbl_entries_data_".$field_id."` WHERE `entry_id` = '".$this->_entry_id."' LIMIT 1");
						$string = str_replace('{$'.$value.'}', $field_handle, $string);
					}
				}
			}
			return $string;
		}

		/**
		 * Find parameters in string
		 *
		 * @param string $string
		 * @return array $params
		 */
		private function __findParamsInString($string)
		{
			preg_match_all('/{\$([^:}]+)(::handle)?}/', $string, $matches);
			$params = array_unique($matches[1]);
			if(!is_array($params) || empty($params)) return array();
			return $params;
		}

	}

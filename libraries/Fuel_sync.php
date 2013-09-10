<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * FUEL CMS
 * http://www.getfuelcms.com
 *
 * An open source Content Management System based on the 
 * Codeigniter framework (http://codeigniter.com)
 */

// ------------------------------------------------------------------------

/**
 * Fuel Sync object 
 *
 * @package		FUEL CMS
 * @subpackage	Libraries
 * @category	Libraries
 */

// --------------------------------------------------------------------

class Fuel_sync extends Fuel_advanced_module {
	
	public $remote = ''; // the name of the remote server
	public $include = array('assets', 'data'); // what to include in sync. Options are "assets", "data", "both"
	public $change_threshold = 50; // the number of detected changes that are allowed before an error is thrown saying "This Don't Look Right!"
	public $asset_folders = array('images', 'pdf'); // the asset folders to be included in the sync
	public $db_tables = 'AUTO'; // the database tables to include in the sync. AUTO will automatically sync the basic FUEL tables as well as all custom module tables
	public $asset_compare_methods = array('date', 'size'); // methods to use to determine if the assets are different
	public $exclude_assets = '#\.html$|\.htaccess$|\.git|\.php|\.js#';
	public $allow_deletes = TRUE; // determines whether to delete local assets during sync process if they are not included in the remote list
	public $create_backup = TRUE; // determines whether to create a backup of the assets and data before running the sync
	public $test_mode = FALSE; // determines whether to run the syncing process in test mode which will not alter any assets or data and just provide you a log of what will happen
	public $new_folder_permissions = 0777; // the folder permissions assigned to newly created folders during the sync
	public $db_sync_prefs = array(
				'tables'      => array(),           // the database tables to include in the sync. AUTO will automatically sync the basic FUEL tables as well as all custom module tables
				'ignore'      => array(),           // list of tables to omit from the backup
				'add_drop'    => TRUE,              // whether to add DROP TABLE statements to backup file
				'add_insert'  => TRUE,              // whether to add INSERT data to backup file
				);

	protected $_logs = array(); // log of items indexed
	protected $_local_assets = NULL; // cache of local assets
	protected $_remote_assets = NULL; // cache of remote assets
	
	const LOG_ERROR = 'error';
	const LOG_DELETED = 'warning';
	const LOG_INFO = 'notification';
	const LOG_SYNCED = 'success';

	/**
	 * Constructor - Sets Fuel_backup preferences
	 *
	 * The constructor can be passed an array of config values
	 */
	function __construct($params = array())
	{
		parent::__construct();
		$this->initialize($params);
		$this->CI->load->library('curl');
		$this->CI->load->library('encrypt');
		$this->CI->load->helper('file');

		$curl_opts = array('timeout' => 60, 'connect_timeout' => 60);
		$this->CI->curl->initialize($curl_opts);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Initialize the backup object
	 *
	 * Accepts an associative array as input, containing backup preferences.
	 * Also will set the values in the config as properties of this object
	 *
	 * @access	public
	 * @param	array	config preferences
	 * @return	void
	 */	
	function initialize($params)
	{
		parent::initialize($params);
		$this->set_params($this->_config);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Sets the remote server to sync with
	 *
	 * @access	public
	 * @param	string	The path to the remote server including the 'http://'
	 * @return	void
	 */	
	function set_remote($remote)
	{
		$this->remote = $remote;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Sets the remote server to sync with
	 *
	 * @access	public
	 * @param	string	The path to the remote server including the 'http://'
	 * @param	array	Initializaiton parameters
	 * @return	void
	 */
	function pull($remote = '', $params = array())
	{
		
		// if the first parameter is an array, we will call set_params to initialize preferences
		if (is_array($remote))
		{
			$this->set_params($remote);
		}
		// if first parameter is a string, we assume it's the path to the remote server
		else if (is_string($remote) AND !empty($remote))
		{
			$this->set_remote($remote);
		}

		// if the second parameter is an array, then we initialize preferences
		if (is_array($params))
		{
			$this->set_params($params);
		}
		
		// if no remote is set, then we return and add the error
		if (empty($this->remote))
		{
			$this->_add_error(lang('sync_error_no_remote'));
			return;
		}

		// first create a backup before we do anything
		if ($this->create_backup)
		{
			$this->_create_backup();
		}

		// check if "assets" is included in what needs to be synced
		if (in_array('assets', $this->include))
		{
			$this->pull_assets();	
		}

		// check if "data" is included in what needs to be synced
		if (in_array('data', $this->include))
		{
			$this->pull_data();
		}
	}


	// --------------------------------------------------------------------
	
	/**
	 * Pulls the assets from the remote server
	 *
	 * @access	public
	 * @param	boolean	Determines whether to delete local assets
	 * @param	boolean	Determines whether to run the syncing without actually changing any images or data
	 * @return	void
	 */
	function pull_assets($delete = NULL, $test_mode = NULL)
	{
		// if the $delete parameter is set, we will use that value, otherwise, we use objects allow_deletes value
		if (is_null($delete))
		{
			$delete = $this->allow_deletes;
		}

		// if the $test_mode parameter is set, we will use that value, otherwise, we use objects test_mode value
		if (is_null($test_mode))
		{
			$test_mode = $this->is_test_mode();
		}

		// get the differences between local and remote files that need to be synced locally
		$to_sync = $this->local_diff_to_sync();


		// check if the number of files is above the specified threshold and if so, add error and return
		if (count($to_sync) >= $this->change_threshold)
		{
			$this->log_message(lang('sync_error_exceeded_threshold', $this->change_threshold));
			return;
		}

		// if there are asset files detected to be out of sync, we will grab them from the remote server
		if (!empty($to_sync))
		{
			foreach ($to_sync as $asset)
			{
				$remote_url = $this->remote_asset_path($asset);
				$local_path = assets_server_path($asset);
				$local_dir = dirname($local_path);
				
				// make the directory if it doesn't exist
				if (!is_dir($local_dir))
				{
					mkdir($local_dir, $this->new_folder_permissions, TRUE);
				}

				if (is_really_writable($local_dir))
				{
					$post['token'] = $this->get_token();
					$this->CI->curl->add_session($remote_url, 'post', $post);
					$fp = @fopen($local_path, FOPEN_WRITE_CREATE_DESTRUCTIVE);

					// if test mode, we don't actually perform the local file saving
					if (!$test_mode AND $fp)
					{
						$this->CI->curl->set_option(CURLOPT_FILE, $fp);
					}
					
					$this->CI->curl->exec();

					// log any curl errors
					if ($this->CI->curl->has_error())
					{
						$this->log_message(lang('sync_error_remote', $asset, current($this->CI->curl->error())));
					}
					@fclose($fp);

					// log it for display later
					$this->log_message(lang('sync_asset_added', $asset), self::LOG_SYNCED);
				}
				else
				{
					echo 'NOT WRITABLE'.$local_dir;
				}
			}
		}

		// if delete parameter is TRUE, we will delete any local files that are not on the remote
		if ($delete)
		{
			$to_delete = $this->local_diff_to_delete();
			foreach ($to_delete as $asset)
			{
				$local_path = assets_server_path($asset);
				if (file_exists($local_path))
				{

					// if test mode, we don't actually delete
					if (!$test_mode)
					{
						@unlink($local_path);
					}
					
					// log it for display later
					$this->log_message(lang('sync_asset_removed', $asset), self::LOG_DELETED);
				}
			}
		}

	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns a list of local assets
	 *
	 * @access	public
	 * @param	array	An array of asset folders to include in the list
	 * @param	mixed 	An array or a regular expression string of files to exclude from the list
	 * @return	void
	 */
	function local_assets($folders = NULL, $exclude = NULL)
	{
		// return cached version if it exists
		if (isset($this->_local_assets))
		{
			return $this->_local_assets;
		}

		if (empty($folders))
		{
			$folders = $this->asset_folders;	
		}

		if (empty($exclude))
		{
			$exclude = $this->exclude_assets;
		}

		$assets_path = assets_server_path();
		$files = array();
		foreach($folders as $folder)
		{
			$asset_files = get_dir_file_info($assets_path.$folder, FALSE);	
			foreach($asset_files as $file => $info)
			{
				if (strncmp($file, '.', 1) !== 0  AND 
					(empty($exclude) OR (is_array($exclude) AND !in_array($file, $exclude)) OR (is_string($exclude) AND !preg_match($exclude, $file)))
				)
				{
					$file_path = assets_server_to_web_path($file);
					$file_path = str_replace(assets_path(), '', $file_path);
					$files[$file_path] = $info;
				}
			}
		}
		$this->_local_assets = $files;
		return $files;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of remote assets to be synced
	 *
	 * @access	public
	 * @return	array
	 */
	function remote_assets()
	{
		// return cached version if it exists
		if (isset($this->_remote_assets))
		{
			return $this->_remote_assets;
		}

		$remote_url = $this->remote_asset_url();
		$post['token'] = $this->get_token();
		$this->CI->curl->add_session($remote_url, 'post', $post);

		$data = $this->CI->curl->exec();
		if ($this->CI->curl->has_error())
		{
			$this->log_message(lang('sync_error_remote_assets', $remote_url, current($this->CI->curl->error())));
		}
		$data_decoded = json_decode($data, TRUE);
		$this->_remote_assets = $data_decoded;
		return $data_decoded;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of assets that don't appear on the remote site and should be deleted locally
	 *
	 * @access	public
	 * @return	array
	 */
	function local_diff_to_delete()
	{
		$local_assets = (array)$this->local_assets();
		$remote_assets = (array)$this->remote_assets();
		$to_delete = array_values(array_diff(array_keys($local_assets), array_keys($remote_assets)));
		return $to_delete;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of assets that have different file sizes, timestamps or both, that should be synced locally
	 *
	 * @access	public
	 * @return	array
	 */
	function local_diff_to_sync()
	{
		$local_assets = $this->local_assets();
		$remote_assets = $this->remote_assets();
		$to_sync = array();

		if (!empty($remote_assets))
		{
			foreach($remote_assets as $key => $asset)
			{
				if (!isset($local_assets[$key]))
				{
					$to_sync[] = $key;
				}
				else
				{
					foreach($this->asset_compare_methods() as $method)
					{
						switch($method)
						{
							case 'date':
								if ($local_assets[$key]['date'] < $asset['date'])
								{
									$to_sync[] = $key;
									continue 2;
								}
								break;

							case 'size':
								if ($local_assets[$key]['size'] != $asset['size'])
								{
									$to_sync[] = $key;
									continue 2;
								}
								break;
						}
					}
				}
			}
			return $to_sync;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the SQL data. This is used in the controller that the local machine calls when syncing
	 *
	 * @access	public
	 * @param	array An array of DB backup preferences (http://ellislab.com/codeigniter/user-guide/database/utilities.html#backup)
	 * @return	string
	 */
	function local_data($db_sync_prefs = array())
	{
		// Load the DB utility class
		$this->CI->load->dbutil();
		
		// need to do text here to make some fixes
		if (empty($db_sync_prefs))
		{
			$db_sync_prefs = $this->db_sync_prefs;	
		}

		$db_sync_prefs['format'] = 'txt';
		$backup =& $this->CI->dbutil->backup($db_sync_prefs);

		return $backup;	
	}

	// --------------------------------------------------------------------
	
	/**
	 * Pulls the data from the remote site and replaces the local data
	 *
	 * @access	public
	 * @param	boolean Whether to use test mode or not
	 * @return	void
	 */
	function pull_data($test_mode = FALSE)
	{
		// if the $test_mode parameter is set, we will use that value, otherwise, we use objects test_mode value
		if (is_null($test_mode))
		{
			$test_mode = $this->is_test_mode();
		}

		// Load the DB utility class
		$data = $this->remote_data();

		// check if it's test mode or not before loading the sql
		if (!$test_mode)
		{
			$this->CI->load->database();
			$this->CI->db->load_sql($data);	
		}
		
		// log it for display later
		$db_prefs = $this->config('db_sync_prefs');
		if (!empty($db_prefs['tables']))
		{
			$tables = implode(', ', $db_prefs['tables']);
		}
		else
		{
			$tables = 'All tables';
		}
		
		$this->log_message(lang('sync_data', $tables),  self::LOG_INFO);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Retuns the data from the remote site
	 *
	 * @access	public
	 * @return	string
	 */
	function remote_data()
	{
		$remote_url = $this->remote_data_url();
		$post['token'] = $this->get_token();
		$this->CI->curl->add_session($remote_url, 'post', $post);
		$data = $this->CI->curl->exec();
		if ($this->CI->curl->has_error())
		{
			$this->log_message(lang('sync_error_remote_data', current($this->CI->curl->error())));
		}

		return $data;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of the different asset compare methods (date, size) to use when comparing local and remote files for syncing
	 *
	 * @access	public
	 * @return	array
	 */
	function asset_compare_methods()
	{
		if (is_string($this->asset_compare_methods))
		{
			return array($this->asset_compare_methods);
		}
		return (array)$this->asset_compare_methods;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the remote asset path URL
	 *
	 * @access	public
	 * @param	string The asset file path (e.g. images/my_pig.jpg)
	 * @return	string
	 */
	function remote_asset_path($asset = '')
	{
		$remote_base = rtrim($this->remote, '/');
		$remote_asset = prep_url($remote_base.'/'.$this->CI->asset->assets_path);
		if (!empty($asset))
		{
			$remote_asset .= $asset;
		}
		return $remote_asset;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the remote URL path of where to retrieve a list of remote assets
	 *
	 * @access	public
	 * @return	string
	 */
	function remote_asset_url()
	{
		$remote_url = $this->_remote_url('assets');
		return $remote_url;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the remote URL path of where to retrieve the remote data dump
	 *
	 * @access	public
	 * @return	string
	 */
	function remote_data_url()
	{
		$remote_url = $this->_remote_url('data');
		return $remote_url;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns a key/value array used for the remote servers list
	 *
	 * @access	public
	 * @return	array
	 */
	function remote_options()
	{
		$remotes = $this->config('remotes');
		if (is_string($remotes))
		{
			$remotes = array($remotes);
		}
		$options = array();
		foreach($remotes as $key => $remote)
		{
			$value = (is_int($key)) ? $remote : $key;
			$options[$value] = $remote;
		}
		return $options;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns a boolean value as to whether this module is enabled and thus remotely accessible. This value must be set on the remote servers version
	 *
	 * @access	public
	 * @return	boolean
	 */
	function is_enabled()
	{
		return (bool) $this->config('enabled');
	}

	// --------------------------------------------------------------------
	
	/**
	 * Sets whether the syncing process should be in "test" mode or not which means no assets or data will actually be pulled and synced
	 *
	 * @access	public
	 * @return	void
	 */
	function set_test_mode($mode)
	{
		$this->test_mode = $mode;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns boolean value as to whether the module is in test mode or not
	 *
	 * @access	public
	 * @return	boolean
	 */
	function is_test_mode()
	{
		return $this->test_mode;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the security token used for authenticating with the remote server
	 *
	 * @access	public
	 * @return	string
	 */
	function get_token()
	{
		return $this->CI->encrypt->sha1($this->CI->config->item('encryption_key'));
	}

	// --------------------------------------------------------------------
	
	/**
	 * Validates whether the syncing process can from the remote server's perspective based on matching security tokens and whether the module is enabled
	 *
	 * @access	public
	 * @return	boolean
	 */
	function validate()
	{

		if ($this->is_enabled() AND isset($_POST['token']) AND $_POST['token'] == $this->CI->encrypt->sha1($this->CI->config->item('encryption_key')))
		{
			return TRUE;
		}
		return FALSE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Adds an item to the index log
	 * 
	 * Used when printing out the index log informaiton
	 *
	 * @access	public
	 * @param	string	Log message
	 * @param	string	Type of log message
	 * @return	void
	 */	
	function log_message($msg, $type = self::LOG_ERROR)
	{
		$this->_logs[$type][] = $msg;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of log messages
	 * 
	 * Used when printing out the index log informaiton
	 *
	 * @access	public
	 * @return	array
	 */	
	function logs()
	{
		return $this->_logs;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Adds an item to the index log
	 * 
	 * Used when printing out the index log informaiton
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	boolean
	 * @return	string
	 */	
	function display_log($type = 'all', $tag = 'span', $return = FALSE)
	{
		$str = '';
		$types = array(self::LOG_INFO, self::LOG_ERROR, self::LOG_DELETED, self::LOG_SYNCED);
		
		if (is_string($type))
		{
			if (empty($type) OR !in_array($type, $types))
			{
				$type = $types;
			}
			else
			{
				$type = (array) $type;
			}
		}

		foreach($types as $t)
		{
			if (isset($this->_logs[$t]))
			{
				foreach($this->_logs[$t] as $l)
				{
					if (!empty($tag))
					{
						$str .= '<'.$tag.' class="'.$t.'">';
					}
					$str .= $l."\n";
					if (!empty($tag))
					{
						$str .= '</'.$tag.'>';
					}
				}
				if (!$return)
				{
					echo $str;
				}
			}
		}
		
		return $str;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the base URL path to the remote servers syncing controller
	 *
	 * @access	protected
	 * @param	string The method to call on the syncing controller. Either "assets" or "data"
	 * @return	void
	 */
	protected function _remote_url($type)
	{
		$remote_base = rtrim($this->remote, '/');
		$remote_url = prep_url($remote_base.'/'.FUEL_FOLDER.'/'.SYNC_FOLDER.'/'.$type);
		return $remote_url;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Creates backup of data/assets before syncing
	 *
	 * @access	protected
	 * @return	void
	 */
	protected function _create_backup()
	{
		if ($this->fuel->modules->exists('backup'))
		{
			$params = array('download' => FALSE);

			// include assets if assets selected for syncing
			if (in_array('assets', $this->include))
			{
				$params['include_assets'] = TRUE;
			}

			$this->fuel->backup->do_backup($params);
			
			// log it for display later
			$backup_path = $this->fuel->backup->backup_data();
			$this->log_message(lang('sync_backed_up', $backup_path['full_path']),  self::LOG_INFO);
		}
	}


}
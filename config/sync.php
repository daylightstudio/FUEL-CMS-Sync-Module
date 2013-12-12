<?php
/*
|--------------------------------------------------------------------------
| FUEL NAVIGATION: An array of navigation items for the left menu
|--------------------------------------------------------------------------
*/
$config['nav']['tools']['tools/sync'] = 'Sync';


/*
|--------------------------------------------------------------------------
| ADDITINOAL SETTINGS:
|--------------------------------------------------------------------------
*/
$config['sync'] = array();
$config['sync']['enabled'] = TRUE;

// the name of the remote server
$config['sync']['remotes'] = array();


// the number of detected changes that are allowed before an error is thrown saying "This Don't Look Right!"
$config['sync']['change_threshold'] = 50;

// the asset folders to be included in the sync
$config['sync']['asset_folders'] = array('images', 'pdf');

// what to include in sync. Options are "assets", "data", "both"
$config['sync']['include'] = array('assets');

// what to include in sync. Options are "assets", "data", "both"
$config['sync']['asset_compare_methods'] = array('date', 'size');

// methods to use to determine if the assets are different
$config['sync']['exclude_assets'] = '#\.html$|\.htaccess$|\.git|\.php|\.js#';

// determines whether to delete local assets during sync process if they are not included in the remote list
$config['sync']['allow_deletes'] = FALSE;

// determines whether to create a backup of the assets and data before running the sync
$config['sync']['create_backup'] = TRUE; 

// determines whether to run the syncing process in test mode which will not alter any assets or data and just provide you a log of what will happen
$config['sync']['test_mode'] = FALSE; 

// the folder permissions assigned to newly created folders during the sync
$config['sync']['new_folder_permissions'] = 0775;

// database syncing preferences
$config['sync']['db_sync_prefs'] = array('tables'  => array(),
				'ignore'      => array(),           // list of tables to omit from the backup
				'add_drop'    => TRUE,              // whether to add DROP TABLE statements to backup file
				'add_insert'  => TRUE,              // whether to add INSERT data to backup file
				);

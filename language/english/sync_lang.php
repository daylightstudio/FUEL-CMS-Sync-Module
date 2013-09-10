<?php 
$lang['module_sync'] = 'Sync';
$lang['sync_error_no_remote'] = 'Please set a valid remote location';
$lang['sync_error_remote_assets'] = 'Error getting remote assets: %1s "%2s"';
$lang['sync_error_remote_data'] = 'Error getting remote data: "%s"';
$lang['sync_error_invalid_token'] = 'Invalid token for syncing';
$lang['sync_error_exceeded_threshold'] = 'The number of files to sync is greater then the threshold %s';
$lang['sync_error_remote'] = 'Error syncing: %1s "%2s"';
$lang['sync_backed_up'] = 'Backed up: %s';

$lang['sync_asset_added'] = 'Asset added: %s';
$lang['sync_asset_removed'] = 'Asset removed: %s';
$lang['sync_data'] = 'Data synced: %s';

$lang['sync_comment_remotes'] = 'Select the remote server you want to sync with';
$lang['sync_comment_include'] = 'Determines whether to include assets, data or both';
$lang['sync_comment_asset_folders'] = 'The asset folders to sync';
$lang['sync_comment_asset_compare_methods'] = 'The methods used to compare the assets with date comparing the last modified date and size comparing the file sizes';
$lang['sync_comment_allow_deletes'] = 'Allow local files to be deleted if they aren\'t on the remote';
$lang['sync_comment_create_backups'] = 'Create a backup version using the Backup module';
$lang['sync_comment_exclude_assets'] = 'A regular expression used to exclude certain files';
$lang['sync_comment_test_mode'] = 'Run the syncing in test mode so as not to change any local assets or data';

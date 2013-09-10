<?php
require_once(FUEL_PATH.'/libraries/Fuel_base_controller.php');

class Sync_module extends Fuel_base_controller {
	
	public $nav_selected = 'tools/sync$|tools/sync/:any';
	

	function __construct()
	{
		// check if it is CLI or a web hook otherwise we need to validate
		$validate = (php_sapi_name() == 'cli' OR defined('STDIN')) ? FALSE : TRUE;
		parent::__construct($validate);

		// validate user has permission
		if ($validate)
		{
			$this->fuel->admin->check_login();
			$this->_validate_user('sync');
		}
	}

	function index()
	{
		// so tooltips will show up
		$this->js_controller_params['method'] = 'add_edit';
		
		$this->load->library('form_builder');
		$this->form_builder->load_custom_fields(APPPATH.'config/custom_fields.php');

		$config = $this->fuel->sync->config();

		$fields = array();

		$fields['General'] = array('type' => 'fieldset', 'class' => 'tab');
		$fields['remote'] = array('type' => 'select', 'options' => $this->fuel->sync->remote_options(), 'comment' => lang('sync_comment_remotes'));
		$asset_folder_options = $this->fuel->assets->dirs();
		$fields['include'] = array('type' => 'multi', 'options' => array('assets' => 'assets', 'data' => 'data'), 'value' => $config['include'], 'comment' => lang('sync_comment_include'));
		if ($this->fuel->modules->exists('backup'))
		{
			$fields['create_backup'] = array('type' => 'checkbox', 'value' => 1, 'checked' => $config['create_backup'], 'comment' => lang('sync_comment_create_backups'));
		}
		$fields['test_mode'] = array('type' => 'checkbox', 'value' => 1, 'checked' => $config['test_mode'], 'comment' => lang('sync_comment_test_mode'));

		$fields['Assets'] = array('type' => 'fieldset', 'class' => 'tab');
		$fields['asset_folders'] = array('type' => 'multi', 'options' => $asset_folder_options, 'value' => $config['asset_folders'], 'comment' => lang('sync_comment_asset_folders'));
		$fields['allow_deletes'] = array('type' => 'checkbox', 'value' => 1, 'checked' => $config['allow_deletes'], 'comment' => lang('sync_comment_allow_deletes'));
		$fields['asset_compare_methods'] = array('type' => 'multi', 'options' => array('date' => 'date', 'size' => 'size'), 'value' => $config['asset_compare_methods'], 'comment' => lang('sync_comment_asset_compare_methods'));
		$fields['change_threshold'] = array('type' => 'number', 'style' => 'width: 50px;', 'size' => 5, 'value' => $config['change_threshold'], 'comment' => lang('sync_comment_remotes'));
		$fields['exclude_assets'] = array('value' => $this->fuel->sync->config('exclude_assets'), 'comment' => lang('sync_comment_exclude_assets'));


		$this->form_builder->set_fields($fields);
		$this->form_builder->set_field_values($_POST);
		$this->form_builder->submit_value = 'Sync';
		$vars['form'] = $this->form_builder->render();
		$vars['page_title'] = $this->fuel->admin->page_title(array(lang('module_sync')), FALSE);
		$vars['form_action'] = fuel_url('tools/sync/run');
		$crumbs = array('tools' => lang('section_tools'), lang('module_sync'));
		$this->fuel->admin->set_titlebar($crumbs, 'ico_sync');
		$this->fuel->admin->render('_admin/sync', $vars);

	}

	function run()
	{
		if (!empty($_POST))
		{
			$config['remote'] = $this->input->post('remote');
			$config['include'] = $this->input->post('include');
			$config['asset_folders'] = $this->input->post('asset_folders');
			$config['asset_compare_methods'] = $this->input->post('asset_compare_methods');
			$config['allow_deletes'] = ($this->input->post('allow_deletes')) ? TRUE : FALSE;
			$config['create_backup'] = ($this->input->post('create_backup')) ? TRUE : FALSE;
			$config['change_threshold'] = (int) $this->input->post('change_threshold');
			$config['exclude_assets'] = $this->input->post('exclude_assets');
			$config['test_mode'] = ($this->input->post('test_mode')) ? TRUE : FALSE;

			$this->fuel->sync->pull($config);
			$vars['log_msg'] = $this->fuel->sync->display_log('all', 'span', TRUE);
			$this->load->module_view(SYNC_FOLDER, '_admin/sync_results', $vars);
		}
	}
	
	
}
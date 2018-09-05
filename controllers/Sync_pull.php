<?php

class Sync_pull extends CI_Controller {

	function __construct()
	{
		parent::__construct();

		// if invalid token, we simply exit
		if (!$this->fuel->sync->validate())
		{
			show_error(lang('sync_error_invalid_token'));
			exit();
		}

	}
	
	function assets()
	{
		$this->load->helper('ajax');
		$folders = ($this->input->post('asset_folders')) ? json_decode($this->input->post('asset_folders'), TRUE) : NULL;
		$local_assets = $this->fuel->sync->local_assets($folders);
		$this->output->set_output(json_encode($local_assets));
		if (is_ajax())
		{
			json_headers();	
		}
	}

	function data()
	{
		$data = $this->fuel->sync->local_data();
		$this->output->set_output($data);
	}
}
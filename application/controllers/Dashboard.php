<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dashboard extends CI_Controller {

	public function index()
	{
		// Check our version and run any migrations
		$this->load->library('Migration');
		$this->migration->current();	
		
		// Database connections
		$this->load->model('logbook_model');
		$this->load->model('user_model');
		if(!$this->user_model->authorize($this->config->item('auth_mode'))) {
			if($this->user_model->validate_session()) {
				$this->user_model->clear_session();
				show_error('Access denied<p>Click <a href="'.site_url('user/login').'">here</a> to log in as another user', 403);
			} else {
				redirect('user/login');
			}
		}
		
		// Calculate Lat/Lng from Locator to use on Maps
		if($this->session->userdata('user_locator')) {
				$this->load->library('qra');

				$qra_position = $this->qra->qra2latlong($this->session->userdata('user_locator'));
				$data['qra'] = "set";
				$data['qra_lat'] = $qra_position[0];
				$data['qra_lng'] = $qra_position[1];   
		} else {
				$data['qra'] = "none";
		}
 
		$this->load->model('stations');
		$data['current_active'] = $this->stations->find_active();

		$setup_required = false;

		if($setup_required) {
			$data['page_title'] = "Cloudlog Setup Checklist";

			$this->load->view('interface_assets/header', $data);
			$this->load->view('setup/check_list');
			$this->load->view('interface_assets/footer');
		} else {

			// 
			$this->load->model('cat');

			$data['radio_status'] = $this->cat->recent_status();

			// Store info
			$data['todays_qsos'] = $this->logbook_model->todays_qsos();
			$data['total_qsos'] = $this->logbook_model->total_qsos();
			$data['month_qsos'] = $this->logbook_model->month_qsos();
			$data['year_qsos'] = $this->logbook_model->year_qsos();
			
			$data['total_countries'] = $this->logbook_model->total_countries();
			$data['total_countries_confirmed_paper'] = $this->logbook_model->total_countries_confirmed_paper();
			$data['total_countries_confirmed_eqsl'] = $this->logbook_model->total_countries_confirmed_eqsl();
			$data['total_countries_confirmed_lotw'] = $this->logbook_model->total_countries_confirmed_lotw();
			
			$data['total_qsl_sent'] = $this->logbook_model->total_qsl_sent();
			$data['total_qsl_recv'] = $this->logbook_model->total_qsl_recv();
			$data['total_qsl_requested'] = $this->logbook_model->total_qsl_requested();
					
			$data['last_five_qsos'] = $this->logbook_model->get_last_qsos('11');

			$data['page_title'] = "Dashboard";

			$this->load->model('dxcc');
			$dxcc = $this->dxcc->list_current();

			$current = $this->logbook_model->total_countries_current();

			$data['total_countries_needed'] = count($dxcc->result()) - $current;

			$this->load->view('interface_assets/header', $data);
			$this->load->view('dashboard/index');
			$this->load->view('interface_assets/footer');
		}

	}
	
	function map() {
		$this->load->model('logbook_model');
		
		$this->load->library('qra');

		//echo date('Y-m-d')
		$raw = strtotime('Monday last week');
		
		$mon = date('Y-m-d', $raw);
		$sun = date('Y-m-d', strtotime('Monday next week'));

		$qsos = $this->logbook_model->map_week_qsos($mon, $sun);

		echo "{\"markers\": [";
		$count = 1;
		foreach ($qsos->result() as $row) {
			//print_r($row);
			if($row->COL_GRIDSQUARE != null) {
				$stn_loc = $this->qra->qra2latlong($row->COL_GRIDSQUARE);
				if($count != 1) {
					echo ",";
				}

				if($row->COL_SAT_NAME != null) { 
						echo "{\"lat\":\"".$stn_loc[0]."\",\"lng\":\"".$stn_loc[1]."\", \"html\":\"Callsign: ".$row->COL_CALL."<br />Date/Time: ".$row->COL_TIME_ON."<br />SAT: ".$row->COL_SAT_NAME."<br />Mode: ".$row->COL_MODE."\",\"label\":\"".$row->COL_CALL."\"}";
				} else {
						echo "{\"lat\":\"".$stn_loc[0]."\",\"lng\":\"".$stn_loc[1]."\", \"html\":\"Callsign: ".$row->COL_CALL."<br />Date/Time: ".$row->COL_TIME_ON."<br />Band: ".$row->COL_BAND."<br />Mode: ".$row->COL_MODE."\",\"label\":\"".$row->COL_CALL."\"}";
				}

				$count++;

			} else {
				if($count != 1) {
					echo ",";
				}

				$result = $this->logbook_model->dxcc_lookup($row->COL_CALL, $row->COL_TIME_ON);
		
				if(isset($result)) {
					$lat = $result['lat'];
					$lng = $result['long'];
				}
				echo "{\"lat\":\"".$lat."\",\"lng\":\"".$lng."\", \"html\":\"Callsign: ".$row->COL_CALL."<br />Date/Time: ".$row->COL_TIME_ON."<br />Band: ".$row->COL_BAND."<br />Mode: ".$row->COL_MODE."\",\"label\":\"".$row->COL_CALL."\"}";
				$count++;
			}

		}
		echo "]";
		echo "}";

	}
	
	
	function todays_map() {
		$this->load->library('qra');
		$this->load->model('logbook_model');
		// TODO: Auth
		$qsos = $this->logbook_model->get_todays_qsos('');

	
		echo "{\"markers\": [";

		foreach ($qsos->result() as $row) {
			//print_r($row);
			if($row->COL_GRIDSQUARE != null) {
				$stn_loc = $this->qra->qra2latlong($row->COL_GRIDSQUARE);
				echo "{\"point\":new GLatLng(".$stn_loc[0].",".$stn_loc[1]."), \"html\":\"Callsign: ".$row->COL_CALL."<br />Date/Time: ".$row->COL_TIME_ON."<br />Band: ".$row->COL_BAND."<br />Mode: ".$row->COL_MODE."\",\"label\":\"".$row->COL_CALL."\"},";
			} else {
				$query = $this->db->query('
					SELECT *
					FROM dxcc_entities
					WHERE prefix = SUBSTRING( \''.$row->COL_CALL.'\', 1, LENGTH( prefix ) )
					ORDER BY LENGTH( prefix ) DESC
					LIMIT 1 
				');
				
				foreach ($query->result() as $dxcc) {
					echo "{\"point\":new GLatLng(".$dxcc->lat.",".$dxcc->long."), \"html\":\"Callsign: ".$row->COL_CALL."<br />Date/Time: ".$row->COL_TIME_ON."<br />Band: ".$row->COL_BAND."<br />Mode: ".$row->COL_MODE."\",\"label\":\"".$row->COL_CALL."\"},";
				}
			}
			
		}
		echo "]";
		echo "}";

	}
	
}

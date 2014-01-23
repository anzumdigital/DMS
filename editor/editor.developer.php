<?php

/* 
 * Enables and disables funcationality primarily of interest to advanced and developer users. 
 */ 
class PLDeveloperTools {
	
	
	function __construct(){

		if( ! is_pl_debug() )
			return;

		// Add tab to toolbar 
		add_filter('pl_toolbar_config', array( $this, 'toolbar'));
		
		// Add developer settings to JSON blob
		add_filter('pl_json_blob_objects', array( $this, 'add_to_blob'));
		
		add_action('wp_footer', array( $this, 'draw_developer_data'), 200);

		$this->url = PL_PARENT_URL . '/editor';
		
		global $pl_perform; 
		$pl_perform = array(); 
	}
	
	function draw_developer_data(){
	
			?><script>
				!function ($) {

					$.plDevData = {
						<?php echo $this->pl_performance_object();?>
					}


				}(window.jQuery);
			</script>
			<?php

	}
	
	function pl_performance_object(){
		
		// blob objects to add to json blob // format: array( 'name' => array() )
		$blob_objects = apply_filters('pl_performance_object', $this->basic_performance() ); 
		
		$output = '';
		if( ! empty($blob_objects) ){
			
			foreach( $blob_objects as $name => $array ){
				$output .= sprintf('%s:%s, %s', $name, json_encode( pl_arrays_to_objects( $array ) ), "\n\n");
			}
		}
		
		return $output;
		
	}
	
	function basic_performance(){
		
		global $pl_start_time, $pl_start_mem, $pl_perform;
		
		
		
		$pl_perform['memory'] = array(
			'num'		=> round( (memory_get_usage() - $pl_start_mem) / (1024 * 1024), 3 ),
			'label'		=> 'MB',
			'title'		=> __( 'Editor Memory', 'pagelines' ),
			'info'		=> __( 'Amount of memory used by the DMS editor in MB during this page load.', 'pagelines' )
		);
		
		$pl_perform['queries'] = array(
			'num'		=> get_num_queries(),
			'label'		=> __( 'Queries', 'pagelines' ),
			'title'		=> __( 'Total Queries', 'pagelines' ),
			'info'		=> __( 'The number of database queries during the WordPress/Editor execution.', 'pagelines' )
		);
		
		$pl_perform['total_time'] = array(
			'num'		=> timer_stop( 0 ),
			'label'		=> __( 'Seconds', 'pagelines' ),
			'title'		=> __( 'Total Time', 'pagelines' ),
			'info'		=> __( 'Total time to render this page including WordPress and DMS editor.', 'pagelines' )
		);
		
		$pl_perform['time'] = array(
			'num'		=> round( microtime(TRUE) - $pl_start_time, 3),
			'label'		=> __( 'Seconds', 'pagelines' ),
			'title'		=> __( 'Editor Time', 'pagelines' ),
			'info'		=> __( 'Amount of time it took to load this page once DMS had started.', 'pagelines' )
		);
		
		return $pl_perform;
		
	}
	
	function add_to_blob( $objects ){
		
		$objects['dev'] = $this->get_set();
		return $objects;
		
	}

	function toolbar( $toolbar ){

		$toolbar[ 'dev' ] = array(
			'name'	=> '',
			'icon'	=> 'icon-wrench',
		//	'type'	=> 'btn',
			'pos'	=> 105,
			'panel'	=> $this->get_settings_tabs()
		
		);


		return $toolbar;
	}
	
	function get_settings_tabs(){

		$tabs = array();

		$tabs['heading'] = __( 'Developer Tools', 'pagelines' );

		foreach( $this->get_set() as $tabkey => $tab ){

			$tabs[ $tabkey ] = array(
				'key' 	=> $tabkey,
				'name' 	=> $tab['name'],
				'icon'	=> isset($tab['icon']) ? $tab['icon'] : ''
			);
		}
	
		return $tabs;

	}
	

	function get_set( ){

		$settings = array(); 
		
		
		
		$settings['dev_log'] = array(
			'name' 	=> __( 'Logging', 'pagelines' ),
			'icon'	=> 'icon-wrench',
			'opts' 	=> array(

				array(
					'key'		=> 'fill-in',
					'type' 		=> 	'template',
					'template'	=> __( 'Nothing appears to have been logged.', 'pagelines' )
				),
			),
			'class'	=> 'dev_logging'
		);
		
		$settings['dev-page'] = array(
			'name' 	=> __( 'Performance', 'pagelines' ),
			'icon'	=> 'icon-wrench',
			'opts' 	=> array(
				array(
					'key'		=> 'fill-in',
					'type' 		=> 	'template',
					'template'	=> __( 'No performance data exists on the page.', 'pagelines' )
				),
			),
		);
		
		$settings['devopts'] = array(
			'name' 	=> __( 'Options', 'pagelines' ),
			'icon'	=> 'icon-wrench',
			'opts' 	=> $this->basic()
		);

		$settings = apply_filters( 'pl_developer_settings_array', $settings );

		$default = array(
			'icon'	=> 'icon-edit',
			'pos'	=> 100
		);

		foreach($settings as $key => &$info){
			$info = wp_parse_args( $info, $default );
		}
		unset($info);

		uasort($settings, "cmp_by_position" );

		return apply_filters('pl_sorted_developer_array', $settings);
	}


	function basic(){

			$settings = array(
				array(
					'key'		=> 'less_dev_mode',
					'col'		=> 1, 
					'type' 		=> 'check',
					'label' 	=> __( 'Enable LESS dev mode', 'pagelines' ),
					'title' 	=> __( 'LESS Developer Mode', 'pagelines' ),
					'help' 		=> sprintf( __( 'Less subsystem will check files for changed less code on every pageload and recompile if there are changes. %s', 'pagelines' ), $this->get_api_key() )
				),
				array(
					'key'		=> 'no_cache_mode',
					'col'		=> 2, 
					'type' 		=> 'check',
					'label' 	=> __( 'Enable no cache mode', 'pagelines' ),
					'title' 	=> __( 'No Cache Mode', 'pagelines' ),
					'help' 		=> __( 'Disables all caching including all CSS/LESS.', 'pagelines' )
				),
			);
			
		return $settings;
	}
	
	function get_api_key() {

		$key = md5( get_bloginfo( 'admin_email' ) . site_url() );
		$link = sprintf( '%s?pl_purge=%s', trailingslashit( site_url() ), $key );
		return sprintf( '<br />To remote purge all caches and update the js/css cache number use this url: <a href="%s">link</a>', $link );
	}
}

function pl_add_perform_data( $data_point, $title, $label, $description){
	global $pl_perform;
	
	$pl_perform[$label] = array(
		'title'		=> $title, 
		'num'		=> $data_point,
		'label'		=> $label,
		'info'		=> $description
	);
}

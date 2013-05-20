<?php
class PassAPI extends Plugin
{
  # Registration
	# register a device to receive push notifications for a pass
	#
	# POST /v1/devices/<deviceID>/registrations/<typeID>/<serial#>
	# Header: Authorization: ApplePass <authenticationToken>
	# JSON payload: { "pushToken" : <push token, which the server needs to send push notifications to this device> }
	#
	# Params definition
	# deviceID	- the device's identifier
	# pass_typeID	- the bundle identifier for a class of passes, sometimes refered to as the pass topic, e.g. pass.com.apple.backtoschoolgift, registered with WWDR
	# serial		- the pass' serial number
	# pushToken	- the value needed for Apple Push Notification service
	#
	# server action: if the authentication token is correct, associate the given push token and device identifier with this pass
	# server response:
	# --> if registration succeeded: 201
	# --> if this serial number was already registered for this device: 304
	# --> if not authorized: 401
	#
	#
	public function rest_post_v1_devices__deviceID_registrations__typeID__serial($params) {
		$payload = json_decode($_POST);
		
		$auth_token = $_SERVER['HTTP_AUTHORIZATION'];
		$args['serial'] = $params['serial'];
		$args['auth_token'] = $auth_token;
		$args['count'] = true;

		if( Passcard::exists( $args) ) {
			$uuid = $params['deviceID'] . '-' . $params['serial'];
			if( !Registration::exists( array('uuid' => $uuid) ) ) {
				// No registration present, make one.
				$regdata = array();
				$regdata['uuid'] = $uuid;
				$regdata['deviceID'] = $params['deviceID'];
				$regdata['pass_typeID'] = $params['typeID'];
				$regdata['push_token'] = $payload->pushToken;
				$regdata['serial'] = $params['serial'];

				Registration::create( $regdata );
				$status = 201;
			} else {
				// Device already registered, so say so.
				$status = 304;
			}
		} else {
			// No device matching those args, go away.
			$status = 401;
		}
		
		$ar = new AjaxResponse($status, null, null);
		$ar->out();
	}
	
	# Updatable passes
	#
	# get all serial #s associated with a device for passes that need an update
	# Optionally with a query limiter to scope the last update since
	# 
	# GET /v1/devices/<deviceID>/registrations/<typeID>
	# GET /v1/devices/<deviceID>/registrations/<typeID>?passesUpdatedSince=<tag>
	#
	# server action: figure out which passes associated with this device have been modified since the supplied tag (if no tag provided, all associated serial #s)
	# server response:
	# --> if there are matching passes: 200, with JSON payload: { "lastUpdated" : <new tag>, "serialNumbers" : [ <array of serial #s> ] }
	# --> if there are no matching passes: 204
	# --> if unknown device identifier: 404
	#
	#
	public function rest_get_v1_devices__deviceID_registrations__typeID($params) {
		$args = array();
		$serials = array();
        $updatable_passes_payload = array();
		$allowed = $_GET->filter_keys( 'passesUpdatedSince' );
	
		foreach ( $allowed as $key => $value ) {
			$key = $value;
		}

		if( !Registration::exists( array('deviceID' => $params['deviceID']) ) ) {
			$registrations = Registrations::get( array('deviceID' => $params['deviceID'], 'pass_typeID' => $params['typeID']) );
			
			foreach( $registrations as $register ) {
				$serials[] = $register->serial;
			}
			
			if( isset($passesUpdatedSince) && $passesUpdatedSince != '' ) {
				$passes = Passcards::get( array('serial' => $serials, 'where' => "updated_at IS NULL OR updated_at >= $passesUpdatedSince") );
			} else {
				$passes = Passcards::get( array('serial' => $serials) );
			}
		
			if( $passes ) {
				foreach( $registrations as $register ) {
					$reg_serials[] = $register->serial;
				}
				
				$update_time = date(DATE_RFC822);
		        $updatable_passes_payload['lastUpdated'] = $update_time;
		        $updatable_passes_payload['serialNumbers'] = $reg_serials->to_json();
		        $status = 200;
			} else {
		        $updatable_passes_payload['lastUpdated'] = '';
		        $updatable_passes_payload['serialNumbers'] = '';
				$status = 204;
			}
		} else {
	        $updatable_passes_payload['lastUpdated'] = '';
	        $updatable_passes_payload['serialNumbers'] = '';
			$status = 404;
		}
		
		$ar = new AjaxResponse( $status, null, $updatable_passes_payload );
		$ar->out();
	}
	
	# Pass delivery
	#
	# GET /v1/passes/<typeID>/<serial#>
	# Header: Authorization: ApplePass <authenticationToken>
	#
	# server response:
	# --> if auth token is correct: 200, with pass data payload
	# --> if auth token is incorrect: 401
	#
	#
	public function rest_get_v1_passes__typeID__serial($params) {
		$auth_token = $_SERVER['HTTP_AUTHORIZATION'];
		$args['serial'] = $params['serial'];
		$args['serial'] = $params['pass_typeID'];
		$args['auth_token'] = $auth_token;

		if( Passcard::exists($args) ) {
			$pass_output_path = file_get_contents( Site::get_url('user') . '/data/passes/' . $params['serial_number'] . '.pkpass' );
			http_send_content_disposition( $params['serial_number'] . '.pkpass', true );
			http_send_content_type( 'application/vnd.apple.pkpass' );
			http_throttle( 0.1, 2048 );
			http_send_file( $pass_output_path );
		} else {
			$status = 401;
			$ar = new AjaxResponse( $status, null, null );
			$ar->out();
		}
	}
	
	# Unregister
	#
	# unregister a device to receive push notifications for a pass
	# 
	# DELETE /v1/devices/<deviceID>/registrations/<passTypeID>/<serial#>
	# Header: Authorization: ApplePass <authenticationToken>
	#
	# server action: if the authentication token is correct, disassociate the device from this pass
	# server response:
	# --> if disassociation succeeded: 200
	# --> if not authorized: 401
	#
	#
	public function rest_post_v1_version_devices__deviceID_registrations__typeID__serial($parameters) {
		var_dump($parameters); exit();
	}
	
	/*
	* POST request to webServiceURL/version/log
	*/
	public function rest_post_v1_log($parameters) {
		var_dump($_GET); exit();
	}
}
?>

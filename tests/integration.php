<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code(403); exit; }
/** Run with: php tests/integration.php. Uses temporary tables, never live booking tables. */
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['HTTP_HOST'] = 'localhost';
require __DIR__ . '/../../../../wp-load.php';
require_once dirname(__DIR__) . '/appointment-pro.php';
use Praxleo\Appointment\Database\Schema;
use Praxleo\Appointment\Database\Repository as R;
use Praxleo\Appointment\Database\Demo;
use Praxleo\Appointment\Database\Transfer;
use Praxleo\Appointment\Domain\Settings;
use Praxleo\Appointment\Domain\Appointments;
use Praxleo\Appointment\Payments\Payments;
use Praxleo\Appointment\Rest\Rest;
function check($condition, $message) {
 if (!$condition) { fwrite(STDERR, "FAIL $message\n"); throw new RuntimeException($message); }
 echo "PASS $message\n";
}
function request($method, $path, $body = null) {
 $req = new WP_REST_Request($method, '/px-appointment/v1/' . $path);
 if ($body !== null) { $req->set_header('Content-Type', 'application/json'); $req->set_body(wp_json_encode($body)); }
 return rest_do_request($req);
}
$original_prefix = $wpdb->prefix;
$test_prefix = $original_prefix . 'px_test_' . bin2hex(random_bytes(4)) . '_';
$original_settings = get_option('px_appointment_settings', null);
$original_error = get_option('px_appointment_mail_error', null);
$original_version = get_option('px_appointment_db_version', null);
$original_user = get_current_user_id();
add_filter('pre_wp_mail', '__return_true');
try {
 $wpdb->prefix = $test_prefix;
 Schema::install();
 Praxleo\Appointment\Domain\Mailer::seed_templates();
 Settings::save(['timezone'=>'UTC','min_lead_hours'=>0,'max_lead_days'=>60,'local_enabled'=>true,'auto_approve'=>true]);
 $demo = Demo::insert();
 check(!is_wp_error($demo) && !empty($demo['appointments']), 'Demo seeds all booking tables');
 check(count(Transfer::tables()) === 17, 'All 17 reference tables supported');
 $export = Transfer::export();
 check(!empty($export['csv']), 'CSV export includes data');
 $before = count(R::all('appointments'));
 $import = Transfer::import($export['csv']);
 check(!is_wp_error($import) && count(R::all('appointments')) === $before, 'CSV round trip preserves appointments');
 check(is_wp_error(Transfer::import("_table,not_a_table\nid\n1")), 'CSV import rejects unknown tables');
 $rest = rest_get_server();
 (new Rest())->register();
 wp_set_current_user(0);
 check(request('GET','admin/customers')->get_status() === 401, 'Guest cannot read customers');
 check(request('GET','settings')->get_status() === 401, 'Guest cannot read payment secrets');
 check(request('GET','appointments')->get_status() === 401, 'Guest cannot list appointments');
 check(request('POST','payments/bulk-delete',['ids'=>[1]])->get_status()===401,'Guest cannot bulk delete payments');
 check(request('POST','admin/database/reset',[])->get_status() === 401, 'Guest cannot reset database');
 $config = request('GET','config')->get_data();
 check(!isset($config['stripe_secret']) && !isset($config['paypal_secret']), 'Public config excludes secrets');
 check(request('GET','locations')->get_status() === 200, 'Guest location endpoint works');
 check(request('GET','services')->get_status() === 200, 'Guest service endpoint works');
 $admin = get_users(['role'=>'administrator','number'=>1]);
 check(!empty($admin), 'Administrator available for integration checks');
 wp_set_current_user($admin[0]->ID);
 $location = request('POST','admin/locations',['name'=>'Integration venue','enabled'=>1])->get_data();
 $service = request('POST','admin/services',['name'=>'Integration service','duration_sec'=>1800,'price'=>50,'enabled'=>1])->get_data();
 $staff = request('POST','admin/staff',['full_name'=>'Integration staff','enabled'=>1])->get_data();
 check(!empty($location['id']) && !empty($service['id']) && !empty($staff['id']), 'Current admin CRUD creates records');
 $id = $staff['id'];
 $day = new DateTimeImmutable('+3 days', new DateTimeZone('UTC'));
 $date = $day->format('Y-m-d');
 $shift = [['weekday'=>(int)$day->format('w'),'start_time'=>'09:00:00','end_time'=>'17:00:00','location_id'=>$location['id'],'breaks'=>[['start_time'=>'12:00:00','end_time'=>'13:00:00']]]];
 $schedule = request('POST',"staff/$id/schedule",$shift)->get_data();
 check(count($schedule[0]['breaks'] ?? []) === 1, 'Schedule save returns breaks');
 request('POST',"staff/$id/schedule",$shift);
 check(count(R::all('staff_breaks', ['schedule_id'=>$schedule[0]['id']])) === 0, 'Schedule replacement removes old breaks');
 request('POST',"staff/$id/services",[['service_id'=>$service['id'],'location_id'=>$location['id'],'price'=>45]]);
 $catalog = request('GET','services')->get_data();
 $catalog_service = array_values(array_filter($catalog['services'],fn($row)=>(int)$row['id']===(int)$service['id']))[0];
 check($catalog_service['location_ids'] === [(int)$location['id']], 'Public catalog exposes valid service locations');
 R::update('staff',['enabled'=>0],['id'=>$id]);
 $catalog = request('GET','services')->get_data();
 $catalog_service = array_values(array_filter($catalog['services'],fn($row)=>(int)$row['id']===(int)$service['id']))[0];
 check($catalog_service['location_ids'] === [], 'Disabled staff do not make service locations bookable');
 R::update('staff',['enabled'=>1],['id'=>$id]);
 $filtered = new WP_REST_Request('GET','/px-appointment/v1/services');
 $filtered->set_query_params(['location_id'=>$location['id']]);
 check(count(rest_do_request($filtered)->get_data()['services'])===1, 'Location service API filtering remains supported');
 $query = http_build_query(['location_id'=>$location['id'],'service_id'=>$service['id'],'staff_id'=>$id,'date'=>$date]);
 // REST dispatch accepts query parameters through set_query_params.
 $slot_request = new WP_REST_Request('GET','/px-appointment/v1/availability');
 $slot_request->set_query_params(['location_id'=>$location['id'],'service_id'=>$service['id'],'staff_id'=>$id,'date'=>$date]);
 wp_set_current_user(0);
 $slots = rest_do_request($slot_request)->get_data();
 check(count($slots)>0, 'Guest availability returns slots');
 $month_request = new WP_REST_Request('GET','/px-appointment/v1/availability/dates');
 $month_args = ['location_id'=>$location['id'],'service_id'=>$service['id'],'staff_id'=>$id,'month'=>substr($date,0,7)];
 $month_request->set_query_params($month_args);
 $month_data = rest_do_request($month_request)->get_data();
 check(($month_data['dates'][$date] ?? -1)===count($slots), 'Monthly calendar counts match actual availability');
 $off_date=$day->modify('+1 day')->format('Y-m-d');
 if(substr($off_date,0,7)===substr($date,0,7))check($month_data['dates'][$off_date]===0,'Calendar disables dates without scheduled hours');
 $invalid_month = new WP_REST_Request('GET','/px-appointment/v1/availability/dates');
 $invalid_month->set_query_params($month_args+[]);$invalid_month->set_param('month','2026-13');
 check(rest_do_request($invalid_month)->get_status()===400,'Calendar rejects invalid months');
 $leap_month = new WP_REST_Request('GET','/px-appointment/v1/availability/dates');
 $leap_month->set_query_params(array_merge($month_args,['month'=>'2028-02']));
 check(count(rest_do_request($leap_month)->get_data()['dates'])===29,'Calendar includes leap-year dates');
 $extra_id=R::insert('extras',['service_id'=>$service['id'],'title'=>'Long add-on','duration_sec'=>28800,'price'=>0,'max_quantity'=>1,'enabled'=>1]);
 $extras_month = new WP_REST_Request('GET','/px-appointment/v1/availability/dates');
 $extras_month->set_query_params($month_args+['extras'=>wp_json_encode([['id'=>$extra_id,'qty'=>1]])]);
 check(rest_do_request($extras_month)->get_data()['dates'][$date]===0,'Calendar availability includes extra duration');
 Settings::save(['max_lead_days'=>1]);
 check(rest_do_request($month_request)->get_data()['dates'][$date]===0,'Calendar disables dates outside booking horizon');
 Settings::save(['max_lead_days'=>60]);

 check(!array_filter($slots, fn($s)=>substr($s['start'],11,2)==='12'), 'Breaks block availability');
 $payload = ['location_id'=>$location['id'],'service_id'=>$service['id'],'staff_id'=>$id,'start'=>$slots[0]['start'],'customer'=>['full_name'=>'Test Customer','email'=>'booking-integration@example.test'],'payment_method'=>'local','idempotency_key'=>bin2hex(random_bytes(16))];
 check(request('POST','appointments',$payload)->get_status()===400, 'Required custom fields enforced');
 $payload['custom_fields'] = [];
 foreach(R::all('custom_fields',['enabled'=>1]) as $field) { $options=json_decode($field['options_json'] ?: '[]',true); $payload['custom_fields'][$field['id']] = $field['type']==='select' ? ($options[0] ?? 'Test') : 'Test'; }
 $booking_response = request('POST','appointments',$payload);
 $booking = $booking_response->get_data();
 check($booking_response->get_status()===200 && !empty($booking['appointment']['id']), 'Guest can book with local payment');
 check($booking['appointment']['status']==='approved' && (float)$booking['amount']===45.0, 'Staff price override and auto approval applied');
 check(count($booking['payments'])===1 && $booking['payments'][0]['status']==='completed', 'Local payment recorded exactly once');
 R::update('staff_schedule',['end_time'=>'09:30:00'],['staff_id'=>$id]);
 check(rest_do_request($month_request)->get_data()['dates'][$date]===0,'Calendar disables fully booked dates');
 R::update('staff_schedule',['end_time'=>'17:00:00'],['staff_id'=>$id]);

 $repeat = request('POST','appointments',$payload)->get_data();
 check($repeat['appointment']['id']===$booking['appointment']['id'] && count($repeat['payments'])===1, 'Booking retry is idempotent');
 $token = $booking['customer_appointment']['token'];
 check(request('GET','appointments/'.$token)->get_status()===200, 'Booking token lookup works');
 check(request('POST','payments/local',['appointment_id'=>$booking['appointment']['id'],'amount'=>0])->get_status()===403, 'Payment endpoint requires booking token');
 $paid = request('POST','payments/local',['appointment_id'=>$booking['appointment']['id'],'amount'=>0,'token'=>$token])->get_data();
 check((float)$paid['amount']===45.0, 'Client cannot change payment total');
 $conflict=$payload;$conflict['idempotency_key']=bin2hex(random_bytes(16));
 check(request('POST','appointments',$conflict)->get_status()===409, 'Overlapping booking rejected');
 check(request('POST','appointments/'.$token.'/cancel',[])->get_status()===200, 'Guest token cancellation works');
 wp_set_current_user($admin[0]->ID);
 $dayoff=request('POST',"staff/$id/days-off",['start_datetime'=>$date.' 00:00:00','end_datetime'=>$date.' 23:59:59','all_day'=>1,'reason'=>'Test leave'])->get_data();
 check(!empty($dayoff['id']) && count(rest_do_request($slot_request)->get_data())===0, 'Staff days off block availability');
 check(rest_do_request($month_request)->get_data()['dates'][$date]===0,'Calendar disables staff days off');
 check(request('DELETE','staff-days-off/'.$dayoff['id'])->get_status()===200,'Staff day off can be removed');
 $newstart=$date.' 14:00:00';
 check(request('PATCH','appointments/'.$booking['appointment']['id'],['start'=>$newstart])->get_status()===200,'Admin rescheduling works');
 check(request('POST','appointments/bulk-delete',['ids'=>[$booking['appointment']['id']]])->get_status()===200 && !Appointments::hydrate($booking['appointment']['id']), 'Bulk appointment deletion removes booking');
 check(count(R::all('payments',['customer_appointment_id'=>$booking['customer_appointment']['id']]))===0,'Appointment deletion removes related payment');

 $bad_webhook=Payments::handle_stripe_webhook('{}','t=1,v1=bad');
 check(is_wp_error($bad_webhook),'Stripe rejects forged webhooks');

 $rendered = do_shortcode('[px-appointment location_id="12" service_id="34" staff_id="56"]');
 check(str_contains($rendered,'px-appointment-booking-app') && str_contains($rendered,'data-service-id="34"'), 'Shortcode renders booking presets');
 check(WP_Block_Type_Registry::get_instance()->is_registered('px-appointment/booking'), 'Booking block registered');
 Settings::save(['stripe_enabled'=>true,'stripe_secret'=>'sk_test_mock','stripe_webhook_secret'=>'whsec_mock','paypal_enabled'=>true,'paypal_client_id'=>'mock','paypal_secret'=>'mock','paypal_webhook_id'=>'mock']);
 // Provider calls are mocked: no card is charged and no network request is made.
 $mock_id=0;
 $mock_http = function($pre,$args,$url) use (&$mock_id) {
  $body=null;
  if(str_contains($url,'api.stripe.com/v1/payment_intents')) $body=['id'=>'pi_test','client_secret'=>'pi_test_secret'];
  if(str_contains($url,'/v1/oauth2/token')) $body=['access_token'=>'mock'];
  if(str_contains($url,'/v1/notifications/verify-webhook-signature')) $body=['verification_status'=>'SUCCESS'];
  if(str_ends_with($url,'/v2/checkout/orders')) $body=['id'=>'ORDER_TEST','status'=>'CREATED'];
  if(str_contains($url,'/ORDER_TEST/capture')) $body=['id'=>'ORDER_TEST','status'=>'COMPLETED','purchase_units'=>[['custom_id'=>(string)$mock_id,'payments'=>['captures'=>[['status'=>'COMPLETED','custom_id'=>(string)$mock_id]]]]]];
  return $body===null ? $pre : ['headers'=>[],'body'=>wp_json_encode($body),'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]];
 };
 add_filter('pre_http_request',$mock_http,10,3);
 wp_set_current_user(0);
 $payload['idempotency_key']=bin2hex(random_bytes(16));$payload['payment_method']='stripe';
 $pending=request('POST','appointments',$payload)->get_data();
 check(($pending['appointment']['status'] ?? '')==='pending','Online booking remains pending before payment');
 $payment_args=['appointment_id'=>$pending['appointment']['id'],'token'=>$pending['customer_appointment']['token'],'amount'=>0];
 $intent=request('POST','payments/stripe/intent',$payment_args)->get_data();
 check(($intent['id'] ?? '')==='pi_test','Stripe intent endpoint uses provider response');
 $event=wp_json_encode(['type'=>'payment_intent.succeeded','data'=>['object'=>['id'=>'pi_test','metadata'=>['appointment_id'=>$pending['appointment']['id']],'amount'=>4500,'currency'=>'usd']]]);
 $timestamp=time();$signature='t='.$timestamp.',v1='.hash_hmac('sha256',$timestamp.'.'.$event,'whsec_mock');
 check(!is_wp_error(Payments::handle_stripe_webhook($event,$signature)),'Signed Stripe callback accepted');
 $settled=Appointments::hydrate($pending['appointment']['id']);
 check($settled['appointment']['status']==='approved' && $settled['payments'][0]['status']==='completed','Verified Stripe callback completes payment and booking');
 Payments::handle_stripe_webhook($event,$signature);
 check(count(Appointments::hydrate($pending['appointment']['id'])['payments'])===1,'Repeated Stripe callback does not duplicate payment');
 $payload['idempotency_key']=bin2hex(random_bytes(16));$payload['payment_method']='paypal';$payload['start']=$slots[2]['start'];
 $pending=request('POST','appointments',$payload)->get_data();
 $mock_id=(int)$pending['appointment']['id'];
 $payment_args=['appointment_id'=>$mock_id,'token'=>$pending['customer_appointment']['token'],'amount'=>0];
 check((request('POST','payments/paypal/order',$payment_args)->get_data()['id'] ?? '')==='ORDER_TEST','PayPal order endpoint works');
 check(request('POST','payments/paypal/capture',$payment_args+['order_id'=>'OTHER_ORDER'])->get_status()===400,'PayPal capture rejects unrelated order');
 check(request('POST','payments/paypal/capture',$payment_args+['order_id'=>'ORDER_TEST'])->get_status()===200,'PayPal capture completes recorded order');
 check(Appointments::hydrate($mock_id)['payments'][0]['status']==='completed','PayPal completed capture records payment');
 remove_filter('pre_http_request',$mock_http,10);
 wp_set_current_user($admin[0]->ID);
 check(request('POST','admin/staff/bulk-delete',['ids'=>[$id]])->get_status()===200 && !R::find('staff',$id), 'Admin bulk deletion works');
 $payment_before=count(R::all('payments'));
 $deny_admin=function($capabilities){$capabilities['manage_options']=false;return $capabilities;};
 add_filter('user_has_cap',$deny_admin);
 check(request('POST','payments/bulk-delete',['ids'=>[1]])->get_status()===403,'User without admin capability cannot delete payments');
 remove_filter('user_has_cap',$deny_admin);
 foreach([[],['ids'=>[]],['ids'=>'all'],['ids'=>[-1]],['ids'=>[true]],['ids'=>['1 OR 1=1']]] as $invalid)check(request('POST','payments/bulk-delete',$invalid)->get_status()===400,'Payment bulk deletion rejects invalid selection');
 check(count(R::all('payments'))===$payment_before,'Invalid payment selections preserve all records');
 $stripe_payment=$settled['payments'][0]['id'];$paypal_payment=Appointments::hydrate($mock_id)['payments'][0]['id'];
 $stripe_appointment=Appointments::hydrate($settled['appointment']['id'])['appointment'];$paypal_appointment=Appointments::hydrate($mock_id)['appointment'];
 $deleted=request('POST','payments/bulk-delete',['ids'=>[$stripe_payment,$paypal_payment,$stripe_payment]])->get_data();
 check(($deleted['deleted'] ?? 0)===2 && count(R::all('payments'))===$payment_before-2,'Payment bulk deletion removes only distinct selected records');
 check(!R::find('payments',(int)$stripe_payment) && !R::find('payments',(int)$paypal_payment),'Selected payment records removed');
 check(Appointments::hydrate($settled['appointment']['id'])['appointment']===$stripe_appointment && Appointments::hydrate($mock_id)['appointment']===$paypal_appointment,'Payment deletion preserves associated appointments');
 check(request('POST','payments/bulk-delete',['ids'=>[$stripe_payment]])->get_data()['deleted']===0,'Repeating payment deletion safely reports zero deleted');
 $templates=count(R::all('notifications'));
 Demo::reset();
 check(!R::all('appointments') && count(R::all('notifications'))===$templates,'Database reset preserves notification templates');
 echo "All integration checks passed.\n";
} catch (Throwable $e) {
 fwrite(STDERR, $e->getMessage() . PHP_EOL);
 $failed = true;
} finally {
 foreach(Transfer::tables() as $table) {
  $name=Schema::table($table);
  if (!str_starts_with($name,$test_prefix.'px_appointment_')) { throw new RuntimeException('Unsafe test cleanup'); }
  $wpdb->query("DROP TABLE IF EXISTS `$name`");
 }
 $wpdb->prefix=$original_prefix;
 foreach(['px_appointment_settings'=>$original_settings,'px_appointment_mail_error'=>$original_error,'px_appointment_db_version'=>$original_version] as $key=>$value) { $value===null ? delete_option($key) : update_option($key,$value); }
 wp_set_current_user($original_user);
}


if (!empty($failed)) { exit(1); }

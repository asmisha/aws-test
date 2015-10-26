#!/usr/bin/php -q
<?php
	require 'vendor/autoload.php';
	
	$client = new Aws\CloudWatch\CloudWatchClient(array(
		'profile' => 'default',
		'region'  => 'us-east-1',
		'version' => 'latest',
	));
	
	$from = new DateTime('-30 minutes', new DateTimeZone('Europe/London'));
	$to = new DateTime('now', new DateTimeZone('Europe/London'));
	
	try{
		$cpuStatistics = $client->getMetricStatistics(array(
		    'Namespace'  => 'AWS/EC2',
		    'MetricName' => 'CPUUtilization',
		    'Dimensions' => array(                        array('Name' => 'InstanceId', 'Value' => 'i-3b331def')),
		    'StartTime'  => $from->format('c'),
		    'EndTime'    => $to->format('c'),
		    'Period'     => 30*60,
		    'Statistics' => array('Average', 'Maximum', 'Minimum'),
		))->get('Datapoints');
	}catch(\Exception $e){
		fprintf(stderr, 'Failed to retrieve Cpu Usage statistics: %s', $e->getMessage());
		$cpuStatistics = array();
	}
	
	var_dump($cpuStatistics);
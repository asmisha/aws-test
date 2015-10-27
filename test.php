#!/usr/bin/php -q
<?php

require 'vendor/autoload.php';

class AwsTest
{
    private $config;

    function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @param string $instanceId
     */
    public function getCpuUsageInfo($from, $to, $instanceId)
    {
        $cloudWatchClient = new Aws\CloudWatch\CloudWatchClient($this->config);
        return $cloudWatchClient->getMetricStatistics(array(
            'Namespace' => 'AWS/EC2',
            'MetricName' => 'CPUUtilization',
            'Dimensions' => array(array('Name' => 'InstanceId', 'Value' => $instanceId)),
            'StartTime' => $from->format('c'),
            'EndTime' => $to->format('c'),
            'Period' => 30 * 60,
            'Statistics' => array('Average', 'Maximum', 'Minimum'),
        ))->get('Datapoints');
    }

    public function getDevices($instanceId)
    {
        $ec2Client = new \Aws\Ec2\Ec2Client($this->config);
        return $ec2Client->DescribeInstances(array(
            'Filters' => array(
                array('Name' => 'instance-id', 'Values' => array($instanceId))
            )
        ))->get('Reservations')[0]['Instances'][0]['BlockDeviceMappings'];
    }

    public function getVolumesInfo($instanceId)
    {
        $devices = $this->getDevices($instanceId);
        $volumeIds = array();
        foreach ($devices as $d) {
            $volumeIds[] = $d['Ebs']['VolumeId'];
        }

        $ec2Client = new \Aws\Ec2\Ec2Client($this->config);
        return $ec2Client->DescribeVolumes(array(
            'Filters' => array(
                array('Name' => 'volume-id', 'Values' => $volumeIds)
            )
        ))->get('Volumes');
    }

    public function startInstance($instanceId)
    {
        $ec2Client = new \Aws\Ec2\Ec2Client($this->config);

        fprintf(STDERR, "Stopping instance\n");
        $ec2Client->stopInstances(array(
            'Force' => true,
            'InstanceIds' => array($instanceId)
        ));
        $ec2Client->waitUntil('InstanceStopped', array(
            'InstanceIds' => array($instanceId),
        ));
        fprintf(STDERR, "Stopped\n");

        $start = microtime(true);

        fprintf(STDERR, "Starting instance\n");
        $ec2Client->startInstances(array(
            'InstanceIds' => array($instanceId)
        ));
        $ec2Client->waitUntil('InstanceRunning', array(
            'InstanceIds' => array($instanceId),
        ));
        fprintf(STDERR, "Started\n");

        return array(
            'timeElapsed' => microtime(true) - $start,
        );
    }
}

$test = new AwsTest(array(
    'profile' => 'default',
    'region' => 'us-east-1',
    'version' => 'latest',
));
$instanceId = 'i-3b331def';

$from = new DateTime('-30 minutes', new DateTimeZone('Europe/London'));
$to = new DateTime('now', new DateTimeZone('Europe/London'));

$data = array(
    'launchTimeElapsed' => $test->startInstance($instanceId),
    'cpuUsage' => $test->getCpuUsageInfo($from, $to, $instanceId),
    'volumeInfo' => $test->getVolumesInfo($instanceId),
);
var_dump($data);

try {
    // it took too long for me to create new LDAP object class on the server, but if there is one - then we'll put some data there that way:
    $ds = ldap_connect("54.164.44.114");

    if ($ds) {
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        $r = ldap_bind($ds, 'cn=admin,dc=ec2,dc=internal', 'test123qwe');

        $info = array(
            'objectClass' => 'instanceInfo',
            'data' => json_encode($data),
        );
        @ldap_delete($ds, "cn=instanceInfo,ou=Test,dc=ec2,dc=internal");

        if (!@ldap_add($ds, "cn=instanceInfo,ou=Test,dc=ec2,dc=internal", $info)) {
            throw new Exception('Failed to save data to LDAP');
        }

        ldap_close($ds);
    } else {
        throw new Exception('Failed to connect to LDAP');
    }
}catch(Exception $e){
    fprintf(STDERR, "%s\n", $e->getMessage());
}
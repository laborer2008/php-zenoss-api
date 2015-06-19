<?php

/**
 * Zenoss XMLRPC API PHP Library
 *
 * This is an easy interface for interacting with your Zenoss Network Monitoring solution.
 * Note, the Zenoss API has much more functionality than what is implemented below. 
 *
 * @category   PHP Libraries
 * @package    Zenoss
 * @author     Benton Snyder <introspectr3@gmail.com>
 * @copyright  2013 Noumenal Designs
 * @license    GPLv3
 * @website    <http://www.noumenaldesigns.com>
 */
 
class Zenoss
{
    // TODO: make it constant
    // TODO: Documentation unclear about TreeRouter, Network6Router, TriggersRouter.
    private static $ROUTERS = array (
        'MessagingRouter' => 'messaging',
        'DetailNavRouter' => 'detailnav',
        'EventsRouter' => 'evconsole',
        'ProcessRouter' => 'process',
        'ServiceRouter' => 'service',
        'SettingsRouter' => 'settings',
        'DeviceRouter' => 'device',
        'NetworkRouter' => 'messaging',
        'TemplateRouter' => 'template',
        'DetailNavRouter' => 'detailnav',
        'ReportRouter' => 'report',
        'MibRouter' => 'mib',
        'ZenPackRouter' => 'zenpack'
    );

    private $tmp;
    private $protocol;
    private $address;
    private $port;
    private $username;
    private $password;
    private $cookie;

    /**
    * Public constructor
    *
    * @access       public
    * @param        string $address
    * @param        string $username
    * @param        string $password
    * @param        string $port
    * @param        string $tmp
    * @param        string $protocol
    * @return
    */
    function __construct($address,$username,$password,$port='8080',$tmp='/tmp/',$protocol='http')
    {
        if(!is_writable($tmp))
                throw new Exception('Specified cookie file directory does not exist or is not writable.');
        
        $this->address = $address;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
        $this->tmp = $tmp;
        $this->protocol = $protocol;
        $this->cookie = $tmp."zenoss_cookie.txt";
    }

    /**
     * Queries Zenoss for requested data
     *
     * @access      private
     * @param       array $data
     * @param       string $uri
     * @return      json array
     */
    private function zQuery($router, $method, array $data, $deviceURI)
    {
        if(!array_key_exists($router, Zenoss::$ROUTERS))
            throw new Exception('Router "' + $router + '" is not available.');

        // inject common variables to data container
        $data['tid'] = 1;
        $data['type'] = "rpc";
        $data['action'] = $router;
        $data['method'] = $method;

        // fetch authorization cookie
        $ch = curl_init("{$this->protocol}://{$this->address}:{$this->port}/zport/acl_users/cookieAuthHelper/login");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        $result = curl_exec($ch);

        // error handling
        if($result===false)
            throw new Exception('Curl error: ' . curl_error($ch));

        $request_url = $deviceURI . '/' . Zenoss::$ROUTERS[$router];

        // execute xmlrpc action
        curl_setopt($ch, CURLOPT_URL, "{$this->protocol}://{$this->address}:{$this->port}{$request_url}_router");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);

        // error handling
        if($result===false)
            throw new Exception('Curl error: ' . curl_error($ch));

        // cleanup
        curl_close($ch);
        return $result;
    }

    /**
     * Retrieves a listing of Zenoss Device Collectors
     *
     * @access      public
     * @param       string $deviceURI
     * @return      json array
     */
    public function getDeviceCollectors($deviceURI)
    {
        $json_data = array();
        $json_main = array();

        $json_main['data'] = $json_data;

        return $this->zQuery('DeviceRouter', 'getCollectors', $json_main, $deviceURI);
    }

    /**
     * Retrieves listing of Zenoss events for specified device
     *
     * @access      public
     * @param       string $deviceURI
     * @param       int $start
     * @param       int $limit
     * @param       string $sort
     * @param       string $dir
     * @return      json array
     */
    public function getDeviceEvents($deviceURI, $start=0, $limit=100, $sort="severity", $dir="DESC")
    {
        $json_params = array();
        $json_data = array();
        $json_main = array();

        $json_params['severity'] = array();
        $json_params['eventState'] = array();

        $json_data['start'] = $start;
        $json_data['limit'] = $limit;
        $json_data['dir'] = $dir;
        $json_data['sort'] = $sort;
        $json_data['params'] = $json_params;

        $json_main['data'] = array($json_data);

        return $this->zQuery('EventsRouter', 'query', $json_main, $deviceURI);
    }

    /**
     * Retrieves listing of components for specified Zenoss Device
     *
     * @access      public
     * @param       string $deviceURI
     * @param       int $start
     * @param       int $limit
     * @return      json array
     */
    public function getDeviceComponents($deviceURI, $start=0, $limit=50)
    {
        $json_keys = array();
        $json_data = array();
        $json_main = array();

        $json_data['start'] = $start;
        $json_data['limit'] = $limit;
        $json_data['uid'] = $deviceURI;
        $json_data['meta_type'] = "IpInterface";
        $json_data['keys'] = $json_keys;

        $json_main['data'] = array($json_data);

        return $this->zQuery('DeviceRouter', 'getComponents', $json_main, $deviceURI);
    }


    /**
     * Retrieves Zenoss device details
     *
     * @access      public
     * @param       string $deviceURI
     * @return      json array
     */
    public function getDeviceInfo($deviceURI)
    {
        $json_keys = array();
        $json_data = array();
        $json_main = array();

        $json_keys = array("uptime", "firstSeen", "lastChanged", "lastCollected", "locking", "memory", "name", "productionState", "priority",
                        "tagNumber", "serialNumber", "rackSlot", "collector","hwManufacturer","hwModel","osManufacturer","osModel","systems",
                        "groups","location","links","comments","snmpSysName","snmpLocation","snmpContact","snmpDescr","snmpCommunity","snmpVersion");

        $json_data['keys'] = $json_keys;
        $json_data['uid'] = $deviceURI;

        $json_main['data'] = array($json_data);

        return $this->zQuery('DeviceRouter', 'getInfo', $json_main, $deviceURI);
    }

    /**
     * Retrieves listing of Zenoss Devices
     *
     * @access      public
     * @param       int $start
     * @param       int $limit
     * @param       string $sort
     * @param       string $dir
     * @return      json array
     */
    public function getDevices($start=0, $limit=100, $sort="name", $dir="ASC")
    {
        $json_params = array();
        $json_data = array();
        $json_main = array();

        $json_data['dir'] = $dir;
        $json_data['limit'] = $limit;
        $json_data['sort'] = $sort;
        $json_data['start'] = $start;
        $json_data['params'] = $json_params;

        $json_main['data'] = $json_data;

        return $this->zQuery('DeviceRouter', 'getDevices', $json_main, '/zport/dmd/Devices/getSubDevices');
    }

    /**
     * Retrieves URL's for Zenoss Device Interface RRD graphs
     *
     * @access      public
     * @param       string $deviceURI
     * @param       string $interface
     * @param       int $drange
     * @return      json array
     */
    public function getDeviceInterfaceRRD($deviceURI, $drange=129600)
    {
        $json_data = array();
        $json_main = array();

        $json_data['uid'] = $deviceURI;
        $json_data['drange'] = $drange;

        $json_main['data'] = array($json_data);

        return $this->zQuery('DeviceRouter', 'getGraphDefs', $json_main, $deviceURI);
    }

    /**
     * Retrieves details on specified Zenoss Device Interface
     *
     * @access      public
     * @param       string $deviceURI
     * @param       string $interface
     * @return      json array
     */
    public function getDeviceInterfaceDetails($deviceURI)
    {
        $json_data = array();
        $json_main = array();

        $json_data['uid'] = $deviceURI;

        $json_main['data'] = array($json_data);

        return $this->zQuery('DeviceRouter', 'getForm', $json_main, $deviceURI);
    }

    /**
     * Returns the tree structure of an organizer hierarchy. Default tree root is MIBs.
     *
     * @access      public
     * @param       string $deviceURI
     * @return      json array
     */
    public function getMibTree($deviceURI, $id='/zport/dmd/Mibs')
    {
        $json_data = array();
        $json_main = array();

        $json_data['id'] = $id;

        $json_main['data'] = array($json_data);

        return $this->zQuery('MibRouter', 'getTree', $json_main, $id);
    }

    /**
     * Get the properties of a MIB
     *
     * @access      public
     * @param       string $deviceURI
     * @return      json array
     */
    public function getMibInfo($deviceURI, $useFieldSets=true)
    {
        $json_data = array();
        $json_main = array();

        $json_data['uid'] = $deviceURI;
        $json_data['useFieldSets'] = $useFieldSets;

        $json_main['data'] = array($json_data);

        return $this->zQuery('MibRouter', 'getInfo', $json_main, $deviceURI);
    }

    /**
     * Get OID mappings
     *
     * @access      public
     * @param       string $deviceURI
     * @return      json array
     */
    public function getMibOidMappings($deviceURI, $dir='ASC', $sort='name', $start=0, $page='None', $limit=256)
    {
        $json_data = array();
        $json_main = array();

        $json_data['uid'] = $deviceURI;
        $json_data['dir'] = $dir;
        $json_data['sort'] = $sort;
        $json_data['start'] = $start;
        $json_data['page'] = $page;
        $json_data['limit'] = $limit;

        $json_main['data'] = array($json_data);

        return $this->zQuery('MibRouter', 'getOidMappings', $json_main, $deviceURI);
    }
}

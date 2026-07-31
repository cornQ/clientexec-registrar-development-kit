<?php

require_once 'modules/admin/models/RegistrarPlugin.php';

class PluginName extends RegistrarPlugin
{
    public $features = [
        'nameSuggest' => true,
        'importDomains' => true,
        'importPrices' => false,
    ];

    private $types = ['A', 'MXE', 'MX', 'CNAME', 'TXT'];

    function getVariables()
    {
        $variables = array(
            lang('Plugin Name') => array (
                                'type'          =>'hidden',
                                'description'   =>lang('How CE sees this plugin (not to be confused with the Signup Name)'),
                                'value'         =>lang('Name.com')
                               ),
            lang('Use testing server') => array(
                                'type'          =>'yesno',
                                'description'   =>lang('Select yes to use the testing envrionment.'),
                                'value'         =>0
                               ),
            lang('Username') => array(
                                'type'          =>'text',
                                'description'   =>lang('Enter your name.com username.'),
                                'value'         =>''
                               ),
            lang('API Token')  => array(
                                'type'          =>'password',
                                'description'   =>lang('Enter your name.com API token.'),
                                'value'         =>'',
                                ),
            lang('Supported Features')  => array(
                                'type'          => 'label',
                                'description'   => '* '.lang('TLD Lookup').'<br>* '.lang('Domain Registration'). ' <br>* '.lang('Existing Domain Importing').' <br>* '.lang('Get / Set Auto Renew Status').' <br>* '.lang('Get / Set Nameserver Records').' <br>* '.lang('Get / Set Contact Information').' <br>* '.lang('Get / Set Registrar Lock').' <br>* '.lang('Initiate Domain Transfer').' <br>* '.lang('Automatically Renew Domain').' <br>* '.lang('Retrieve EPP Code'),
                                'value'         => ''
                                ),
            lang('Actions') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain isn\'t registered)'),
                                'value'         => 'Register'
                                ),
            lang('Registered Actions') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain is registered)'),
                                'value'         => 'Renew (Renew Domain),DomainTransferWithPopup (Initiate Transfer),Cancel',
                                ),
            lang('Registered Actions For Customer') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain is registered)'),
                                'value'         => '',
            )
        );

        return $variables;
    }

    function checkDomain($params)
    {
        $domains = [];
        if (isset($params['namesuggest'])) {
            $response = $this->makePostRequest('v4/domains:search', [
                'keyword' => $params['sld'],
                'tldFilter' => $params['namesuggest']
            ]);

            foreach ($response->results as $result) {
                if ($result->premium == true) {
                    continue;
                }
                $domains[] = [
                    'tld' => $result->tld,
                    'domain' => $result->sld,
                    'status' => ($result->purchasable == '1' ? 0 : 1)
                ];
            }
        } else {
            $response = $this->makePostRequest('v4/domains:checkAvailability', ['domainNames' => [$params['sld'] . '.' . $params['tld']]]);

            $status = 1;
            if ($response->results[0]->purchasable == '1' && $response->results[0]->premium != true) {
                $status = 0;
            }

            $domains[] = [
                'tld' => $response->results[0]->tld,
                'domain' => $response->results[0]->sld,
                'status' => $status
            ];
        }

        return ['result' => $domains];
    }

    /**
     * Initiate a domain transfer
     *
     * @param array $params
     */
    function doDomainTransferWithPopup($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $transferid = $this->initiateTransfer($this->buildTransferParams($userPackage, $params));
        $userPackage->setCustomField("Registrar Order Id", $userPackage->getCustomField("Registrar").'-'.$transferid);
        $userPackage->setCustomField('Transfer Status', $transferid);
        return "Transfer of has been initiated.";
    }

    /**
     * Register domain name
     *
     * @param array $params
     */
    function doRegister($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->registerDomain($this->buildRegisterParams($userPackage, $params));
        $userPackage->setCustomField("Registrar Order Id", $userPackage->getCustomField("Registrar").'-'.$orderid);
        return $userPackage->getCustomField('Domain Name') . ' has been registered.';
    }

    /**
     * Renew domain name
     *
     * @param array $params
     */
    function doRenew($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->renewDomain($this->buildRenewParams($userPackage, $params));
        $userPackage->setCustomField("Registrar Order Id", $userPackage->getCustomField("Registrar").'-'.$orderid);
        return $userPackage->getCustomField('Domain Name') . ' has been renewed.';
    }

    function getTransferStatus($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response = $this->makeGetRequest('v4/transfers/{$domainName}', '');

        if ($response->status == 'Completed') {
            $userPackage->setCustomField('Transfer Status', 'Completed');
        }

        return $response->status;
    }

    function initiateTransfer($params)
    {
        $response = $this->makePostRequest('v4/transfers', [
            'domainName' => $params['sld'] . '.' . $params['tld'],
             'authCode' => $params['eppCode']
        ]);

        return $response->order;
    }

    function renewDomain($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response = $this->makePostRequest("v4/domains/{$domainName}:renew", ['years' => $params['NumYears']]);
        return $response->order;
    }

    function registerDomain($params)
    {
        $nameservers = [];
        if (isset($params['NS1'])) {
            for ($i = 1; $i <= 12; $i++) {
                if (isset($params["NS$i"])) {
                    $nameservers[] = $params["NS$i"]['hostname'];
                } else {
                    break;
                }
            }
        }

        $response = $this->makePostRequest('v4/domains', [
            'domain' => [
                'domainName' => $params['sld'] . '.' . $params['tld'],
                'nameservers' => $nameservers,
                'contacts' => [
                    'registrant' => [
                        'firstName' => $params['RegistrantFirstName'],
                        'lastName' => $params['RegistrantLastName'],
                        'companyName' => $params['RegistrantOrganizationName'],
                        'address1' => $params['RegistrantAddress1'],
                        'address2' => '',
                        'city' => $params['RegistrantCity'],
                        'state' => $params['RegistrantStateProvince'],
                        'zip' => $params['RegistrantPostalCode'],
                        'country' => $params['RegistrantCountry'],
                        'phone' => $this->validatePhone($params['RegistrantPhone'], $params['RegistrantCountry']),
                        'fax' => '',
                        'email' => $params['RegistrantEmailAddress']
                    ]
                ],
                'privacyEnabled' => false,
                'locked' => true,
                'autorenewEnabled' => $params['renewname']

            ],
            'years' => $params['NumYears']
        ]);

        return $response->order;
    }

    private function validatePhone($phone, $country)
    {
        // strip all non numerical values
        $phone = preg_replace('/[^\d]/', '', $phone);

        if ($phone == '') {
            return $phone;
        }

        $query = "SELECT phone_code FROM country WHERE iso=? AND phone_code != ''";
        $result = $this->db->query($query, $country);
        if (!$row = $result->fetch()) {
            return $phone;
        }

        // check if code is already there
        $code = $row['phone_code'];
        $phone = preg_replace("/^($code)(\\d+)/", '+\1.\2', $phone);
        if ($phone[0] == '+') {
            return $phone;
        }

        // if not, prepend it
        return "+$code.$phone";
    }

    function getContactInformation($params)
    {
        $response = $this->makeGetRequest('v4/domains', $params['sld'] . '.' .$params['tld']);
        $info = [];

        foreach (array('registrant', 'billing', 'admin', 'tech') as $type) {
            switch ($type) {
                case 'registrant':
                    $internalType = 'Registrant';
                    break;

                case 'billing':
                    $internalType = 'AuxBilling';
                    break;

                case 'admin':
                    $internalType = 'Admin';
                    break;

                case 'tech':
                    $internalType = 'Tech';
                    break;
            }

            if (isset($response->contacts->$type)) {
                $info[$internalType]['Company'] = array($this->user->lang('Organization'), isset($response->contacts->$type->companyName) ? $response->contacts->$type->companyName : '');
                $info[$internalType]['First Name'] = array($this->user->lang('First Name'), $response->contacts->$type->firstName);
                $info[$internalType]['Last Name']  = array($this->user->lang('Last Name'), $response->contacts->$type->lastName);
                $info[$internalType]['Address 1']  = array($this->user->lang('Address').' 1', $response->contacts->$type->address1);
                $info[$internalType]['Address 2']  = array($this->user->lang('Address').' 2', isset($response->contacts->$type->address2) ? $response->contacts->$type->address2 : '');
                $info[$internalType]['City']      = array($this->user->lang('City'), $response->contacts->$type->city);
                $info[$internalType]['State / Province']  = array($this->user->lang('Province').'/'.$this->user->lang('State'), $response->contacts->$type->state);
                $info[$internalType]['Country']   = array($this->user->lang('Country'), $response->contacts->$type->country);
                $info[$internalType]['Postal Code']  = array($this->user->lang('Postal Code'), $response->contacts->$type->zip);
                $info[$internalType]['EmailAddress']     = array($this->user->lang('E-mail'), $response->contacts->$type->email);
                $info[$internalType]['Phone']  = array($this->user->lang('Phone'), $response->contacts->$type->phone);
                $info[$internalType]['Fax']       = array($this->user->lang('Fax'), isset($response->contacts->$type->fax) ? $response->contacts->$type->fax : '');
            } else {
                $info[$internalType] = array(
                    'Company' => array($this->user->lang('Organization'), ''),
                    'First Name' => array($this->user->lang('First Name'), ''),
                    'Last Name' => array($this->user->lang('Last Name'), ''),
                    'Address 1' => array($this->user->lang('Address').' 1', ''),
                    'Address 2' => array($this->user->lang('Address').' 2', ''),
                    'City' => array($this->user->lang('City'), ''),
                    'State / Province' => array($this->user->lang('Province').'/'.$this->user->lang('State'), ''),
                    'Country' => array($this->user->lang('Country'), ''),
                    'Postal Code' => array($this->user->lang('Postal Code'), ''),
                    'Email Address' => array($this->user->lang('E-mail'), ''),
                    'Phone' => array($this->user->lang('Phone'), ''),
                    'Fax' => array($this->user->lang('Fax'), ''),
                );
            }
        }
        return $info;
    }

    function setContactInformation($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $arguments['contacts']['registrant']['companyName'] = $params['Registrant_Company'];
        $arguments['contacts']['registrant']['firstName'] = $params['Registrant_First_Name'];
        $arguments['contacts']['registrant']['lastName'] = $params['Registrant_Last_Name'];
        $arguments['contacts']['registrant']['address1'] = $params['Registrant_Address_1'];
        $arguments['contacts']['registrant']['address2'] = $params['Registrant_Address_2'];
        $arguments['contacts']['registrant']['email'] = $params['Registrant_Email_Address'];
        $arguments['contacts']['registrant']['city'] = $params['Registrant_City'];
        $arguments['contacts']['registrant']['state'] = $params['Registrant_State_/_Province'];
        $arguments['contacts']['registrant']['country'] = $params['Registrant_Country'];
        $arguments['contacts']['registrant']['zip'] = $params['Registrant_Postal_Code'];
        $arguments['contacts']['registrant']['phone'] = $this->validatePhone($params['Registrant_Phone'], $params['Registrant_Country']);
        $arguments['contacts']['registrant']['fax']   = $this->validatePhone($params['Registrant_Fax'], $params['Registrant_Country']);

        $response = $this->makePostRequest("v4/domains/{$domainName}:setContacts", $arguments);
        return $this->user->lang('Contact Information updated successfully.');
    }

    function getNameServers($params)
    {
        $response = $this->makeGetRequest('v4/domains', $params['sld'] . '.' .$params['tld']);
        $info = [];
        $info['usesDefault'] = false;
        $info['hasDefault'] = false;

        foreach ($response->nameservers as $ns) {
            $info[] = $ns;
        }
        return $info;
    }

    function setNameServers($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        foreach ($params['ns'] as $key => $value) {
            $arguments['nameservers'][] = $value;
        }
        $response = $this->makePostRequest("v4/domains/{$domainName}:setNameservers", $arguments);
    }

    function getGeneralInfo($params)
    {
        $response = $this->makeGetRequest('v4/domains', $params['sld'] . '.' .$params['tld']);

        $data = [];

        $data['domain'] = $response->domainName;
        $data['expiration'] = date('m/d/Y', strtotime($response->expireDate));
        $data['registrationstatus'] = $this->user->lang('N/A');
        $data['purchasestatus'] = $this->user->lang('N/A');
        $data['autorenew'] = $response->autorenewEnabled;
        // $data['is_registered'] = ( $response['status'][0]['#']['registrationstatus'][0]['#'] == 'Registered') ? true : false;
        // $data['is_expired'] = ( $response['status'][0]['#']['registrationstatus'][0]['#'] == 'Expired') ? true : false;

        return $data;
    }

    function fetchDomains($params)
    {
        $response = $this->makeGetRequest('v4/domains', '');

        $domainsList = [];
        $count = 1;
        foreach ($response->domains as $domain) {
            list($sld, $tld) = DomainNameGateway::splitDomain($domain->domainName);

            $data = [];
            $data['id'] = $count;
            $data['sld'] = $sld;
            $data['tld'] = $tld;
            $data['exp'] = $domain->expireDate;
            $domains[] = $data;
            $count++;
        }
        $metaData = [];
        return [$domains, $metaData];
    }

    function setAutorenew($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $command = 'disableAutorenew';
        if ($params['autorenew'] == 1) {
            $command = 'enableAutorenew';
        }

        $response = $this->makePostRequest("v4/domains/{$domainName}:{command}", $arguments);
        return "Domain updated successfully";
    }

    function getRegistrarLock($params)
    {
        $response = $this->makeGetRequest('v4/domains', $params['sld'] . '.' .$params['tld']);
        return $response->locked;
    }

    function doSetRegistrarLock($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $this->setRegistrarLock($this->buildLockParams($userPackage, $params));
        return "Updated Registrar Lock.";
    }

    function setRegistrarLock($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response = $this->makeGetRequest('v4/domains', $domainName);
        if ($response->locked == true) {
            $command = 'unlock';
        } else {
            $command = 'lock';
        }
        $response = $this->makePostRequest("v4/domains/{$domainName}:{$command}", []);
    }

    function getEPPCode($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response = $this->makeGetRequest("v4/domains/{$domainName}:getAuthCode", '');
        if (!empty($response->authCode)) {
            return $response->authCode;
        }
        return '';
    }

    function sendTransferKey($params)
    {
    }

    function getDNS($params)
    {
    }

    function setDNS($params)
    {
    }

    private function makePostRequest($resource, $request)
    {
        $username = $this->settings->get('plugin_name_Username');
        $password = $this->settings->get('plugin_name_API Token');
        $url = 'https://api.name.com/';
        if ($this->settings->get('plugin_name_Use testing server')) {
            $url = 'https://api.dev.name.com/';
        }
        $url = $url . $resource;

        CE_Lib::log(4, "cURL Request to: " . $url);
        CE_Lib::log(4, $request);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            curl_setopt($ch, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, $caPathOrFile);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            CE_Lib::log(4, "({$errno}): {$error_message}");
        }
        curl_close($ch);

        $response = json_decode($response);
        CE_Lib::log(4, "Response: ");
        CE_Lib::log(4, $response);

        if ($info['http_code'] != 200) {
            if ($response->details != '') {
                throw new CE_Exception($response->details);
            } elseif ($response->message != '') {
                throw new CE_Exception($response->message);
            }
        }

        return $response;
    }

    private function makeGetRequest($resource, $request)
    {
        $username = $this->settings->get('plugin_name_Username');
        $password = $this->settings->get('plugin_name_API Token');
        $url = 'https://api.name.com/';
        if ($this->settings->get('plugin_name_Use testing server')) {
            $url = 'https://api.dev.name.com/';
        }
        $url = $url . $resource . '/' . $request;

        CE_Lib::log(4, "cURL Request to: " . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            curl_setopt($ch, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, $caPathOrFile);
        }

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            CE_Lib::log(4, "({$errno}): {$error_message}");
        }
        curl_close($ch);

        $response = json_decode($response);
        CE_Lib::log(4, "Response: ");
        CE_Lib::log(4, $response);

        if ($info['http_code'] != 200) {
            if ($response->details != '') {
                throw new CE_Exception($response->details);
            } elseif ($response->message != '') {
                throw new CE_Exception($response->message);
            }
        }

        return $response;
    }
}

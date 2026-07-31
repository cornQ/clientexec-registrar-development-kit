<?php

require_once 'modules/admin/models/RegistrarPlugin.php';

class PluginSample extends RegistrarPlugin
{
    public $features = [
        'nameSuggest' => true,
        'importDomains' => true,
        'importPrices' => true,
    ];

    public function getVariables()
    {
        $variables = [
            'Plugin Name' => [
                'type' => 'hidden',
                'description' => 'How CE sees this plugin (not to be confused with the Signup Name)',
                'value' => 'Sample'
            ],
            'Text Field' => [
                'type' => 'text',
                'description' => 'Text Field Description',
                'value' => 'Default Value',
            ],
            'Encrypted Text Field' => [
                'type' => 'text',
                'description' => 'Encrypted Text Field Description',
                'value' => '',
                'encryptable' => true
            ],
            'Password Text Field' => [
                'type' => 'password',
                'description' => 'Encrypted Password Field Description',
                'value' => '',
                'encryptable' => true
            ],
            'Text Area' => [
                'type' => 'textarea',
                'description' => 'Text Area Description',
                'value' => 'Default Value',
            ],
            'Yes / No' => [
                'type' => 'yesno',
                'description' => 'Yes / No Description',
                'value' => '1',
            ],
            'Supported Features' => [
                'type' => 'label',
                'description' => '* '.lang('TLD Lookup').'<br>* '.lang('Domain Registration').' <br>* '.lang('Domain Registration with ID Protect').' <br>* '.lang('Existing Domain Importing').' <br>* '.lang('Get / Set Auto Renew Status').' <br>* '.lang('Get / Set DNS Records').' <br>* '.lang('Get / Set Nameserver Records').' <br>* '.lang('Get / Set Contact Information').' <br>* '.lang('Get / Set Registrar Lock').' <br>* '.lang('Initiate Domain Transfer').' <br>* '.lang('Automatically Renew Domain').' <br>* '.lang('Send Transfer Key') . '<br>* '.lang('NameSpinner'),
                'value' => ''
            ],
            'Actions' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin (when a domain isn\'t registered)',
                'value' => 'Register'
            ],
            'Registered Actions' =>[
                'type'  => 'hidden',
                'description' => 'Current actions that are active for this plugin (when a domain is registered)',
                'value'       => 'Renew (Renew Domain),DomainTransferWithPopup (Initiate Transfer),SendTransferKey (Send Auth Info),Cancel',
            ],
            'Registered Actions For Customer' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin (when a domain is registered)',
                'value' => 'SendTransferKey (Send Auth Info)',
            ]
        ];
        return $variables;
    }

    public function getTLDsAndPrices($params)
    {

        $tlds = [];
        // loop over all TLDs/Pricing
        $tlds[$tld]['pricing']['register'] = $registerPrice;
        $tlds[$tld]['pricing']['transfer'] = $tranferPrice;
        $tlds[$tld]['pricing']['renew'] = $renewPrice;

        return $tlds;
    }


    public function checkDomain($params)
    {

        /* Status:
            0 = Domain is available
            1 = Domain already registered
            2 = Domain Extension not recognized or supported by registrar
            3 = Invalid domain
            5 = Connection issue to registrar
        */

        $result = [
            'tld'    => $tld,
            'domain' => $domain,
            'status' => $status
        ];
        $domains[] = $result;

        return ['result' => $domains];
    }

    public function doDomainTransferWithPopup($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $transferId = $this->initiateTransfer($this->buildTransferParams($userPackage, $params));
        $userPackage->setCustomField(
            'Registrar Order Id',
            $userPackage->getCustomField('Registrar') . '-' . $transferId
        );
        $userPackage->setCustomField('Transfer Status', $transferId);
        return "Transfer has been initiated.";
    }

    public function doRegister($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderId = $this->registerDomain($this->buildRegisterParams($userPackage, $params));
        $userPackage->setCustomField(
            'Registrar Order Id',
            $userPackage->getCustomField('Registrar') . '-' . $orderId
        );
        return $userPackage->getCustomField('Domain Name') . ' has been registered.';
    }

    public function doRenew($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderId = $this->renewDomain($this->buildRenewParams($userPackage, $params));
        $userPackage->setCustomField(
            'Registrar Order Id',
            $userPackage->getCustomField('Registrar') . '-' . $orderId
        );
        return $userPackage->getCustomField('Domain Name') . ' has been renewed.';
    }

    public function getTransferStatus($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $transferId = $userPackage->getCustomField('Transfer Status');

        // check status at registrar
        // if transfer has completed:
        $userPackage->setCustomField('Transfer Status', 'Completed');

        // return status string from registrar to show in UI
        return $status;
    }

    public function initiateTransfer($params)
    {
        // start transfer

        // return transfer ID to store to check status later.
        return $transferId;
    }

    public function renewDomain($params)
    {
        // renew domain

        // return order ID
        return $orderId;
    }

    public function registerDomain($params)
    {
        // register domain

        // return order id
        return $orderId;
    }

    public function getContactInformation($params)
    {
        $info = [];
        // only Registrant is supported in UI, but we return all for future releases
        foreach (array('Registrant', 'AuxBilling', 'Admin', 'Tech') as $type) {
            if (is_array($data)) {
                $info[$type]['OrganizationName'] = [
                    $this->user->lang('Organization'),
                    $data[$type.'OrganizationName'][0]['#']
                ];

                $info[$type]['JobTitle'] = [
                    $this->user->lang('Job Title'),
                    $data[$type.'JobTitle'][0]['#']
                ];

                $info[$type]['FirstName'] = [
                    $this->user->lang('First Name'),
                    $data[$type.'FirstName'][0]['#']
                ];

                $info[$type]['LastName'] = [
                    $this->user->lang('Last Name'),
                    $data[$type.'LastName'][0]['#']
                ];

                $info[$type]['Address1'] = [
                    $this->user->lang('Address').' 1',
                    $data[$type.'Address1'][0]['#']
                ];

                $info[$type]['Address2'] = [
                    $this->user->lang('Address').' 2',
                    $data[$type.'Address2'][0]['#']
                ];

                $info[$type]['City'] = [
                    $this->user->lang('City'),
                    $data[$type.'City'][0]['#']
                ];

                $info[$type]['StateProvChoice'] = [
                    $this->user->lang('State or Province'),
                    $data[$type.'StateProvinceChoice'][0]['#']
                ];

                $info[$type]['StateProvince'] = [
                    $this->user->lang('Province').'/'.$this->user->lang('State'),
                    $data[$type.'StateProvince'][0]['#']
                ];

                $info[$type]['Country'] = [
                    $this->user->lang('Country'),
                    $data[$type.'Country'][0]['#']
                ];

                $info[$type]['PostalCode'] = [
                    $this->user->lang('Postal Code'),
                    $data[$type.'PostalCode'][0]['#']
                ];

                $info[$type]['EmailAddress'] = [
                    $this->user->lang('E-mail'),
                    $data[$type.'EmailAddress'][0]['#']
                ];

                $info[$type]['Phone'] = [
                    $this->user->lang('Phone'),
                    $data[$type.'Phone'][0]['#']
                ];

                $info[$type]['PhoneExt'] = [
                    $this->user->lang('Phone Ext'),
                    $data[$type.'PhoneExt'][0]['#']
                ];

                $info[$type]['Fax'] = [
                    $this->user->lang('Fax'),
                    $data[$type.'Fax'][0]['#']
                ];
            } else {
                $info[$type] = [
                    'OrganizationName' => [
                        $this->user->lang('Organization'),
                        ''
                    ],
                    'JobTitle' => [
                        $this->user->lang('Job Title'),
                        ''
                    ],
                    'FirstName' => [
                        $this->user->lang('First Name'),
                        ''
                    ],
                    'LastName'         => [
                        $this->user->lang('Last Name'),
                        ''
                    ],
                    'Address1' => [
                        $this->user->lang('Address').' 1',
                        ''
                    ],
                    'Address2' => [
                        $this->user->lang('Address').' 2',
                        ''
                    ],
                    'City' => [
                        $this->user->lang('City'),
                        ''
                    ],
                    'StateProvChoice' => [
                        $this->user->lang('State or Province'),
                        ''
                    ],
                    'StateProvince' => [
                        $this->user->lang('Province').'/'.$this->user->lang('State'),
                        ''
                    ],
                    'Country' => [
                        $this->user->lang('Country'),
                        ''
                    ],
                    'PostalCode' => [
                        $this->user->lang('Postal Code'),
                        ''
                    ],
                    'EmailAddress' => [
                        $this->user->lang('E-mail'),
                        ''
                    ],
                    'Phone' => [
                        $this->user->lang('Phone'),
                        ''
                    ],
                    'PhoneExt' => [
                        $this->user->lang('Phone Ext'),
                        ''
                    ],
                    'Fax' => [
                        $this->user->lang('Fax'),
                        ''
                    ]
                ];
            }
        }
        return $info;
    }

    public function setContactInformation($params)
    {
        // update/set contact info at registrar

        return $this->user->lang('Contact Information updated successfully.');
    }

    public function getNameServers($params)
    {
        $info = [];

        // if the registrar supports their own "default" name servers
        $info['hasDefault'] = true;
        // if the domain is using the registrars default domain servers
        $info['usesDefault'] = true;

        // get name servers at registar
        $info[] = 'ns1.sample.com';
        $info[] = 'ns2.sample.com';

        return $info;
    }

    public function setNameServers($params)
    {
        $nameServers = [];
        foreach ($params['ns'] as $key => $value) {
            $nameServers[] = $value;
        }
    }

    public function getGeneralInfo($params)
    {
        // if a connection error occurs, throw the proper exception and error code:
        // throw new CE_Exception('Failed to communicate with registrar.', EXCEPTION_CODE_CONNECTION_ISSUE);
        $data = [];

        $data['id'] = $domainId;
        $data['domain'] = $domainName;
        $data['expiration'] = $expirationDate;
        $data['is_registered'] = true;
        $data['is_expired'] = false;

        // if auto renew is enabled at registrar
        $data['autorenew'] = true;

        // not required
        $data['registrationstatus'] = $registrationStatus;
        // not required
        $data['purchasestatus'] = $purchaseStatus;

        return $data;
    }

    public function fetchDomains($params)
    {
        $domainsList = [];

        // loop over data
        $data['id'] = $domainId;
        $data['sld'] = $domain;
        $data['tld'] = $tld;
        $data['exp'] = $expirationDate;
        $domainsList[] = $data;
        // end loop

        $metaData = [];
        $metaData['total'] = $totalDomainsInAccount;
        return [
            $domainsList,
            $metaData
        ];
    }

    public function disablePrivateRegistration($params)
    {
        // disable ID Protect at registrar

        return "Domain updated successfully";
    }

    public function setAutorenew($params)
    {
        // update auto renew at registrar
        return "Domain updated successfully";
    }

    public function getRegistrarLock($params)
    {
        // get registrar lock

        // if enabled
        return true;
    }

    public function doSetRegistrarLock($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $this->setRegistrarLock($this->buildLockParams($userPackage, $params));
        return "Updated Registrar Lock.";
    }

    public function setRegistrarLock($params)
    {
        // set registrar lock
        if ($params['lock'] == 1) {
            // lock domain at registrar
        }
    }

    public function doSendTransferKey($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $this->sendTransferKey($this->buildRegisterParams($userPackage, $params));
        return 'Successfully sent auth info for ' . $userPackage->getCustomField('Domain Name');
    }

    public function sendTransferKey($params)
    {
        // send transfer key to registrant
    }

    public function getDNS($params)
    {
        // get host records
        $record = [
            'id'       => $recordId,
            'hostname' => $hostname,
            'address'  => $address,
            'type'     => $type
        ];
        $records[] = $record;

        $types = [
            'A',
            'MXE',
            'MX',
            'CNAME',
            'URL',
            'FRAME',
            'TXT'
        ];

        return [
            'records' => $records,
            'types'   => $types,
            'default' => true
        ];
    }

    public function setDNS($params)
    {
        // set host records
        foreach ($params['records'] as $index => $record) {
            // $record['hostname'];
            // $record['type'];
            // $record['address'];
        }
        return $this->user->lang("Host information updated successfully");
    }


    public function getEPPCode($params)
    {
        // return EPP Code to show on client / admin UI
        $result = $this->api->SyncFromRegistry($params['sld'] . '.' . $params['tld']);
    }
}

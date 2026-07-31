<?php

require_once 'modules/admin/models/RegistrarPlugin.php';

class PluginConnectreseller extends RegistrarPlugin
{
    public $features = [
        'nameSuggest' => true,
        'importDomains' => true,
        'importPrices' => true,
    ];

    private $liveUrl = 'https://api.connectreseller.com/ConnectReseller/ESHOP/';
    private $recordTypes = array('A', 'AAAA', 'MX', 'CNAME', 'NS', 'TXT', 'SRV', 'SOA');

    public function getVariables()
    {
        $variables = array(
            lang('Plugin Name') => array (
                'type'          => 'hidden',
                'description'   => lang('How CE sees this plugin (not to be confused with the Signup Name)'),
                'value'         => lang('Connect Reseller'),
            ),
            lang('API Key')  => array(
                'type'          => 'text',
                'description'   => lang('Enter the API Key for your Connect Reseller account.'),
                'value'         => '',
            ),
            lang('Coupon Code') => array(
                'type'          => 'text',
                'description'   => lang('Enter your CouponCode for your Connect Reseller account.'),
                'value'         => ''
            ),
            lang('Supported Features')  => array(
                'type'          => 'label',
                'description'   => '* ' . lang('TLD Lookup') . '<br>* ' . lang('Domain Registration') . ' <br>* ' . lang('Get / Set DNS Records') . ' <br>* ' . lang('Get / Set Nameserver Records') . ' <br>* ' . lang('Get / Set Contact Information') . ' <br>* ' . lang('Get / Set Registrar Lock') . ' <br>* ' . lang('Initiate Domain Transfer'),
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

    public function doRegister($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->registerDomain($this->buildRegisterParams($userPackage, $params));
        if ($orderid['result'] == 'success') {
            $userPackage->setCustomField("Registrar Order Id", $userPackage->getCustomField("Registrar") . '-' . $params['userPackageId']);
            return $userPackage->getCustomField('Domain Name') . ' has been registered.';
        } else {
            return $userPackage->getCustomField('Domain Name') . ' not registered.';
        }
    }

    public function registerDomain($params)
    {
              $tld = $params["tld"];
              $sld = $params["sld"];
              $websitename = $sld . '.' . $tld;
              $regperiod = $params["NumYears"];
              $nameserver1 = ($params["ns1"] ? $params["ns1"] : 'dns1.managedns.org');
              $nameserver2 = ($params["ns2"] ? $params["ns2"] : 'dns2.managedns.org');
              $nameserver3 = ($params["ns3"] ? $params["ns3"] : 'dns3.managedns.org');
              $nameserver4 = ($params["ns4"] ? $params["ns4"] : 'dns4.managedns.org');
              $IsWhoisProtectionFalse = "false";
              $CouponCode = $params["Coupon Code"];
              $IsWhoisProtection = $params["idprotection"] == 1 ? true : $IsWhoisProtectionFalse;
              $RegistrantEmailAddress = $params["RegistrantEmailAddress"];

              $ViewClient = [
                  'APIKey' => $params['API Key'],
                  'UserName' => $RegistrantEmailAddress
              ];
              $res = $this->makeRequest('ViewClient', $ViewClient);

              $msgResult = array_key_exists("responseMsg", $res);
              if ($msgResult) {
              //Since user not present register the user
                  if ($res['responseMsg']['statusCode'] != '200') {
                      $UserName = $params["RegistrantEmailAddress"];
                      $str = "cr123456";
                      $Password = str_shuffle($str);
                      $companyname = $params["RegistrantOrganizationName"];
                      $firstname = $params["RegistrantFirstName"];
                      $lastname = $params["RegistrantLastName"];
                      $address1 = $params["RegistrantAddress1"];
                      $countryname = $params["RegistrantCountry"];
                      $state = $params["RegistrantStateProvince"];
                      $city = $params["RegistrantCity"];
                      $postcode = $params["RegistrantPostalCode"];
                      $phonecc = $this->countryCodePhone($params['RegistrantCountry']);
                      $phonenumber = $params['RegistrantPhone'];

                      $AddClient = [
                          'APIKey' => urlencode($params['API Key']),
                          'UserName' => urlencode($RegistrantEmailAddress),
                          'Password' => urlencode($Password),
                          'CompanyName' => urlencode($companyname),
                          'FirstName' => urlencode($firstname),
                          'Address1' => urlencode($address1),
                          'City' => urlencode($city),
                          'StateName' => $state,
                          'CountryName' => $countryname,
                          'Zip' => $postcode,
                          'PhoneNo_cc' => $phonecc,
                          'PhoneNo' => $phonenumber,
                      ];
                      $addClientRes = $this->makeRequest('AddClient', $AddClient);
                      if ($addClientRes['responseMsg']['statusCode'] != 200) {
                          $values["error"] = "Domain Registration Failure: Unable to add client.";
                      } else {
                          //$res = json_decode($addClientResponse);
                          $UserName = $addClientRes['responseData']['userName'];
                          $CustomerID = $addClientRes['responseData']['clientId'];

                          $DefaultRegistrantContact = [
                              'APIKey' => $params['API Key'],
                              'Id' => $CustomerID,
                          ];
                          $defaultRegistrantRes = $this->makeRequest('DefaultRegistrantContact', $DefaultRegistrantContact);

                          if ($defaultRegistrantRes['responseMsg']['statusCode'] != 200) {
                              $values["error"] = $defaultRegistrantRes['responseMsg']['statusCode'] . " - " . $defaultRegistrantRes['responseMsg']['message'];
                          } else {
                              $ContactId = $defaultRegistrantRes['responseData']['registrantContactId'];
                          }

                          $regperiod = $params["NumYears"];
                          $websitename = $sld . '.' . $tld;

                          $domainorder = [
                              'APIKey' => $params['API Key'],
                              'Id' => $CustomerID,
                              'ProductType' => (int)"1",
                              'Websitename' => $websitename,
                              'Duration' => $regperiod,
                              'IsWhoisProtection' => $IsWhoisProtection,
                          ];

                          if ($tld == "us") {
                              $domainorder['appPurpose'] = "P2";
                              $domainorder['nexusCategory'] = "C31/CC";
                              $domainorder['isUs'] = true;
                          }

                          if ($nameserver1 != "") {
                              $domainorder['ns1'] = $nameserver1;
                          }
                          if ($nameserver2 != "") {
                              $domainorder['ns2'] = $nameserver2;
                          }
                          if ($nameserver3 != "") {
                              $domainorder['ns3'] = $nameserver3;
                          }
                          if ($nameserver4 != "") {
                              $domainorder['ns4'] = $nameserver4;
                          }

                          $premiumEnabled = (bool) $params['premiumEnabled'] == true ? 1 : 0;
                          $domainorder['isEnablePremium'] = $premiumEnabled;

                          if (!(!isset($CouponCode) || trim($CouponCode) === '')) {
                              $domainorder['couponCode'] = $CouponCode;
                          }
                            $orderRes = $this->makeRequest('domainorder', $domainorder);

                          if ($orderRes['responseMsg']['statusCode'] != 200) {
                              $values["error"] = $orderRes['responseMsg']['statusCode'] . " - " . $orderRes['responseMsg']['message'];
                          } else {
                              $values["result"] = "success";
                          }
                      }
                  } else {
                      $UserName = $res['responseData']['userName'];
                      $CustomerID = $res['responseData']['clientId'];
                      $regperiod = $params["NumYears"];
                      $websitename = $sld . '.' . $tld;
                      $domainorder = [
                          'APIKey' => $params['API Key'],
                          'Id' => $CustomerID,
                          'ProductType' => (int)1,
                          'Websitename' => $websitename,
                          'Duration' => $regperiod,
                          'IsWhoisProtection' => $IsWhoisProtection,
                      ];

                      if ($tld == "us") {
                          $domainorder['appPurpose'] = "P1";
                          $domainorder['nexusCategory'] = "C31/CC";
                          $domainorder['isUs'] = true;
                      }

                      if ($nameserver1 != "") {
                          $domainorder['ns1'] = $nameserver1;
                      }
                      if ($nameserver2 != "") {
                          $domainorder['ns2'] = $nameserver2;
                      }
                      if ($nameserver3 != "") {
                          $domainorder['ns3'] = $nameserver3;
                      }
                      if ($nameserver4 != "") {
                          $domainorder['ns4'] = $nameserver4;
                      }

                      $premiumEnabled = (bool) $params['premiumEnabled'] == true ? 1 : 0;
                      $domainorder['isEnablePremium'] = $premiumEnabled;

                      if (!(!isset($CouponCode) || trim($CouponCode) === '')) {
                          $domainorder['couponCode'] = $CouponCode;
                      }

                      $orderRes = $this->makeRequest('domainorder', $domainorder);
                      ;
                      if ($orderRes['responseMsg']['statusCode'] != 200) {
                          $values["error"] = $orderRes['responseMsg']['statusCode'] . " - " . $orderRes['responseMsg']['message'];
                      } else {
                          $values["result"] = "success";
                      }
                  }
              } else {
                  $values["error"] = $res['statusCode'] . " - Domain Registration Failure - " . $res['responseText'];
              }
              return $values;
    }

    private function countryCodePhone($country)
    {
        $query = "SELECT phone_code FROM country WHERE iso=? AND phone_code != ''";
        $result = $this->db->query($query, $country);
        $row = $result->fetch();
        if ($row['phone_code']) {
            $phoneCode = $row['phone_code'];
        } else {
            $query = "SELECT phone_code FROM country WHERE name=? AND phone_code != ''";
            $result = $this->db->query($query, $country);
            $row = $result->fetch();
            if ($row['phone_code']) {
                $phoneCode = $row['phone_code'];
            } else {
                $phoneCode = "";
            }
        }
        return $phoneCode;
    }

    public function doRenew($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->renewDomain($this->buildRenewParams($userPackage, $params));
        if ($orderid['result'] == 'success') {
            $userPackage->setCustomField("Registrar Order Id", $userPackage->getCustomField("Registrar") . '-' . $params['userPackageId']);
            return $userPackage->getCustomField('Domain Name') . ' has been renewed.';
        } else {
            return $userPackage->getCustomField('Domain Name') . ' not renewed.';
        }
    }

    public function renewDomain($params)
    {
        $CouponCode = $params["Coupon Code"];
        $IsWhoisProtectionFalse = "false";
        $IsWhoisProtection = $params["idprotection" ] == 1 ? true : $IsWhoisProtectionFalse;
        $res = $this->getDomainInfoDetails($params);
        $msgResult = array_key_exists("responseMsg", $res);
        if ($msgResult) {
            if ($res["responseMsg"]['statusCode'] != '200') {
                $values["error"] = $res["responseMsg"]['message'];
            } else {
                $CustomerId = $res["responseData"]['customerId'];
                $renewalorder = [
                  'APIKey' => $params['API Key'],
                  'Websitename' => $params["sld"] . '.' . $params["tld"],
                  'OrderType' => 2,
                  'Duration' => $params['NumYears'],
                  'Id' => $CustomerId,
                  'IsWhoisProtection' => $IsWhoisProtection,
                ];
                $premiumEnabled = (bool) $params['premiumEnabled'] == true ? 1 : 0;
                $renewalorder['isEnablePremium'] = $premiumEnabled;
                if (!(!isset($CouponCode) || trim($CouponCode) === '')) {
                    $renewalorder['couponCode'] = $CouponCode;
                }
                $res1 = $this->makeRequest('renewalorder', $renewalorder);
                if ($res1["responseMsg"]['statusCode'] != '200') {
                    $values["error"] = $res1["responseMsg"]['statusCode'] . " - " . $res1["responseMsg"]['message'];
                } else {
                    if ($res1["responseData"]['statusCode'] != 1000) {
                        $values["error"] = $res1["responseData"]['statusCode'] . " - " . $res1["responseData"]['message'];
                    } else {
                        $values["result"] = "success";
                    }
                }
            }
        } else {
            $values["error"] = $res['statusCode'] . " - Domain Renewal Failure - " . $res['responseText'];
        }
        return $values;
    }

    public function doDomainTransferWithPopup($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $transferid = $this->initiateTransfer($this->buildTransferParams($userPackage, $params));
        if ($transferid['result'] == 'success') {
            $userPackage->setCustomField("Registrar Order Id", $userPackage->getCustomField("Registrar") . '-' . $params['userPackageId']);
            $userPackage->setCustomField('Transfer Status - Success');
            return $userPackage->getCustomField('Domain Name') . ' Transfer of has been initiated.';
        } else {
            return $userPackage->getCustomField('Domain Name') . ' not transfered.';
        }
    }

    public function initiateTransfer($params)
    {
        $tld = $params["tld"];
        $sld = $params["sld"];
        $websitename = $sld . '.' . $tld;
        $regperiod = $params["NumYears"];
        $CouponCode = $params["Coupon Code"];
        $nameserver1 = ($params["ns1"] ? $params["ns1"] : 'dns1.managedns.org');
        $nameserver2 = ($params["ns2"] ? $params["ns2"] : 'dns2.managedns.org');
        $nameserver3 = ($params["ns3"] ? $params["ns3"] : 'dns3.managedns.org');
        $nameserver4 = ($params["ns4"] ? $params["ns4"] : 'dns4.managedns.org');
        $IsWhoisProtectionFalse = "false";
        $IsWhoisProtection = $params["idprotection"] == 1 ? true : $IsWhoisProtectionFalse;
        $RegistrantEmailAddress = $params["RegistrantEmailAddress"];
        $authCode = $params["eppCode"];

        $ViewClient = [
        'APIKey' => $params['API Key'],
        'UserName' => $RegistrantEmailAddress
        ];
        $res = $this->makeRequest('ViewClient', $ViewClient);

        $msgResult = array_key_exists("responseMsg", $res);
        if ($msgResult) {
            if ($res["responseMsg"]['statusCode'] != '200') {
                $UserName = $params["RegistrantEmailAddress"];
                $str = "cr123456";
                $Password = str_shuffle($str);
                $companyname = $params["RegistrantOrganizationName"];
                $firstname = $params["RegistrantFirstName"];
                $lastname = $params["RegistrantLastName"];
                $address1 = $params["RegistrantAddress1"];
                $countryname = $params["RegistrantCountry"];
                $state = $params["RegistrantStateProvince"];
                $city = $params["RegistrantCity"];
                $postcode = $params["RegistrantPostalCode"];
                $phonecc = $this->countryCodePhone($params['RegistrantCountry']);
                $phonenumber = $params['RegistrantPhone'];

                $AddClient['APIKey'] = urlencode($params['API Key']);
                if ($tld == "us") {
                    $AddClient['appPurpose'] = "P2";
                    $AddClient['nexusCategory'] = "C31/CC"; //C32/CC
                    $AddClient['isUs'] = true;
                }
                $AddClient['UserName'] = urlencode($RegistrantEmailAddress);
                $AddClient['Password'] = urlencode($Password);
                $AddClient['CompanyName'] = urlencode($companyname);
                $AddClient['FirstName'] = urlencode($firstname);
                $AddClient['Address1'] = urlencode($address1);
                $AddClient['City'] = urlencode($city);
                $AddClient['StateName'] = $state;
                $AddClient['CountryName'] = $countryname;
                $AddClient['Zip'] = $postcode;
                $AddClient['PhoneNo_cc'] = $phonecc;
                $AddClient['PhoneNo'] = $phonenumber;

                $addClientRes = $this->makeRequest('AddClient', $AddClient);

                if ($addClientRes['responseMsg']['statusCode'] != 200) {
                    $values["error"] = "Domain Transfer Failure: Unable to add client.";
                } else {
                    $UserName = $addClientRes['responseData']['userName'];
                    $CustomerID = $addClientRes['responseData']['clientId'];
                    $TransferOrder = array(
                        'Id' => intval($CustomerID),
                        'OrderType' => 4,
                        'APIKey' => $params['API Key'],
                        'Websitename' => $websitename,
                        'AuthCode' => $authCode,
                        'IsWhoisProtection' => $IsWhoisProtection
                    );

                    if (!(!isset($CouponCode) || trim($CouponCode) === '')) {
                        $TransferOrder['couponCode'] = $CouponCode;
                    }

                    $orderRes = $this->makeRequest('TransferOrder', $TransferOrder);

                    if ($orderRes['responseMsg']['statusCode'] != 200) {
                        $values["error"] = $orderRes['responseMsg']['statusCode'] . " - " . $orderRes['responseMsg']['message'];
                    } else {
                        if ($orderRes['responseData']['statusCode'] != 200) {
                            $values["error"] = $orderRes['responseData']['statusCode'] . " - " . $orderRes['responseData']['message'];
                        } else {
                            $values["result"] = "success";
                        }
                    }
                }
            } else {
                $UserName = $res['responseData']['userName'];
                $CustomerID = $res['responseData']['clientId'];
                $TransferOrder = array(
                    'Id' => intval($CustomerID),
                    'OrderType' => 4,
                    'APIKey' => $params['API Key'],
                    'Websitename' => $websitename,
                    'AuthCode' => $authCode,
                    'IsWhoisProtection' => $IsWhoisProtection
                );

                if (!(!isset($CouponCode) || trim($CouponCode) === '')) {
                    $TransferOrder['couponCode'] = $CouponCode;
                }

                if ($tld == "us") {
                    $TransferOrder['nexusCategory'] = "C31/CC";
                    $TransferOrder['appPurpose'] = "P2";
                    $TransferOrder['isUs'] = true;
                }

                $orderRes = $this->makeRequest('TransferOrder', $TransferOrder);

                if ($orderRes['responseMsg']['statusCode'] != 200) {
                    $values["error"] = $orderRes['responseMsg']['statusCode'] . " - " . $orderRes['responseMsg']['message'];
                } else {
                    if ($orderRes['responseData']['statusCode'] != 200) {
                        $values["error"] = $orderRes['responseData']['statusCode'] . " - " . $orderRes['responseData']['message'];
                    } else {
                        $values["result"] = "success";
                    }
                }
            }
        } else {
            $values["error"] = $res['statusCode'] . " - Domain Transfer Failure - " . $res['responseText'];
        }
        return $values;
    }

    public function getRegistrarLock($params)
    {
        $res = $this->getDomainInfoDetails($params)["responseData"]['isDomainLocked'];
        return (( $res == "True" || $res == "true" ) ? true : false);
    }

    public function doSetRegistrarLock($params)
    {
                $userPackage = new UserPackage($params['userPackageId']);
                $this->setRegistrarLock($this->buildLockParams($userPackage, $params));
                return "Updated Registrar Lock.";
    }

    public function setRegistrarLock($params)
    {
        $DomainLockStatus = ($this->getRegistrarLock($params) ? 'false' : 'true');
        $res = $this->getDomainInfoDetails($params);
        $domainNameId = $res["responseData"]['domainNameId'];

        $ManageDomainLock = [
        'APIKey' => $params['API Key'],
        'websiteName' => $params["sld"] . '.' . $params["tld"],
        'domainNameId' => $domainNameId,
        'isDomainLocked' => $DomainLockStatus,
        ];
        $manageRes = $this->makeRequest('ManageDomainLock', $ManageDomainLock);
        if ($manageRes["responseMsg"]['statusCode'] != '200') {
            $values["error"] = $manageRes["responseMsg"]['statusCode'] . " - " . $manageRes["responseMsg"]['message'];
        }
        return $values;
    }

    public function doSendTransferKey($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $this->sendTransferKey($this->buildRegisterParams($userPackage, $params));
        return 'Successfully sent auth info for ' . $userPackage->getCustomField('Domain Name');
    }

    public function sendTransferKey($params)
    {
    }

    public function getDNS($params)
    {
        $res = $this->getDomainInfoDetails($params);
        $domianId = $res["responseData"]['domainNameId'];
        $websiteId = $res["responseData"]['websiteId'];
        if ($res["responseData"]['dnszoneStatus'] == "1") {
            $ViewDNSRecord = [
                'APIKey' => $params['API Key'],
                'WebsiteId' => $websiteId
            ];
            $viewDnsRes = $this->makeRequest('ViewDNSRecord', $ViewDNSRecord);
            if ($viewDnsRes["responseMsg"]['statusCode'] != '200') {
                $values["error"] = $viewDnsRes["responseMsg"]['statusCode'] . " - " . $viewDnsRes["responseMsg"]['message'];
            } else {
                $host = $viewDnsRes['responseData'];
                foreach ($host as $v) {
                    if (($v['recordType'] == 'SRV') || ($v['recordType'] == 'SOA')  || ($v['recordType'] == 'NS')) {
                    } else {
                        $values[] = array(
                            'id' => $v['dnszoneRecordID'],
                            'hostname' => $v['recordName'],
                            'address'  => $v['recordContent'],
                            'type'     => $v['recordType'],
                        );
                    }
                }
            }
        } else {
            $ManageDNSRecords = [
            'APIKey' => $params['API Key'],
            'WebsiteId' => $websiteId
                ];
                $manageDnsRes = $this->makeRequest('ManageDNSRecords', $ManageDNSRecords);
            if ($manageDnsRes["responseMsg"]['statusCode'] != '200') {
                $values["error"] = $manageDnsRes["responseMsg"]['statusCode'] . " - " . $manageDnsRes["responseMsg"]['message'];
            } else {
                $ViewDNSRecord = [
                'APIKey' => $params['API Key'],
                'WebsiteId' => $websiteId
                ];
                $viewDnsRes = $this->makeRequest('ViewDNSRecord', $ViewDNSRecord);
                if ($viewDnsRes["responseMsg"]['statusCode'] != '200') {
                    $values["error"] = $viewDnsRes["responseMsg"]['statusCode'] . " - " . $viewDnsRes["responseMsg"]['message'];
                } else {
                    $host = $viewDnsRes['responseData'];
                    foreach ($host as $v) {
                        if (($v['recordType'] == 'SRV') || ($v['recordType'] == 'SOA')  || ($v['recordType'] == 'NS')) {
                            $values[] = array(
                                'id' => $v['dnszoneRecordID'],
                                'hostname' => $v['recordName'],
                                'address'  => $v['recordContent'],
                                'type'     => $v['recordType'],
                            );
                        }
                    }
                }
            }
        }

            return array('records' => $values, 'types' => $this->recordTypes, 'default' => true);
    }

    public function setDNS($params)
    {
        $sld = $params['sld'];
        $tld =  $params['tld'];
        $websitename = $sld . '.' . $tld;
      # Put your code to get the lock status here
        $res = $this->getDomainInfoDetails($params);
        $domianId = $res["responseData"]['domainNameId'];
        $websiteId = $res["responseData"]['websiteId'];
        if ($res["responseData"]['dnszoneStatus'] == "1") {
            $DNSZoneId = $res["responseData"]['dnszoneId'];

            $ViewDNSRecord = [
                'APIKey' => $params['API Key'],
                'WebsiteId' => $websiteId
            ];
            $viewDnsRes = $this->makeRequest('ViewDNSRecord', $ViewDNSRecord);

            if ($viewDnsRes["responseMsg"]['statusCode'] != '200') {
                $values["error"] = $viewDnsRes["responseMsg"]['statusCode'] . " - " . $viewDnsRes["responseMsg"]['message'];
            } else {
                $host = $viewDnsRes['responseData'];
                foreach ($params['records'] as $k => $v) {
                    if (!empty($v['hostname'])  && !empty($v['address'])) {
                        if ($v['id'] != "" && $v['id'] != null) {
                            $key = array_search($v['id'], array_column($host, 'dnszoneRecordID'));
                            if ($key != -1) {
                                $checkHost = $host[$key];

                                if (($v['hostname'] != $checkHost['recordName'] ) || ($v['type'] != $checkHost['recordType']) || ($v['address'] != $checkHost['recordContent'])) {
                                    $hostName = $v['hostname'];
                                    if ($hostName == "@") {
                                        $hostName = $websitename;
                                    } elseif ($hostName == "*") {
                                        $hostName = "*." . $websitename;
                                    } elseif (strpos($hostName, $websitename) === false) {
                                        $hostName = $hostName . "." . $websitename;
                                    }

                                        $ModifyDNSRecord = [
                                            'APIKey' => $params['API Key'],
                                            'WebsiteId' => $websiteId,
                                            'DNSZoneID' => $DNSZoneId,
                                            'DNSZoneRecordID' => $v['id'],
                                            'RecordName' => $hostName,
                                            'RecordType' => $v['type'],
                                            'RecordValue' => $v['address'],
                                            'RecordTTL' => 43200,
                                        ];
                                        $modifyDnsRes = $this->makeRequest('ModifyDNSRecord', $ModifyDNSRecord);
                                        if ($modifyDnsRes["responseMsg"]['statusCode'] != '200') {
                                          //  $values["error"] = $modifyDnsRes["responseMsg"]['statusCode'] . " - " . $modifyDnsRes["responseMsg"]['message'];
                                            $status =  true;
                                            $hostName = $v['hostname'];
                                            if ($hostName == "@") {
                                                $hostName = $websitename;
                                            } elseif ($hostName == "*") {
                                                $hostName = "*." . $websitename;
                                            } elseif (strpos($hostName, $websitename) === false) {
                                                $hostName = $hostName . "." . $websitename;
                                            }
                                            $key1 = array_search($hostName, array_column($host, 'recordName'));
                                            if ($key1 != -1) {
                                                $checkHost1 = $host[$key1];
                                                if ($v['address'] == $checkHost1['recordContent']) {
                                                    $status = false;
                                                }
                                            }
                                            if ($status) {
                                                $AddDNSRecord = [
                                                'APIKey' => $params['API Key'],
                                                'WebsiteId' => $websiteId,
                                                'DNSZoneID' => $DNSZoneId,
                                                'RecordName' => $hostName,
                                                'RecordType' => $v['type'],
                                                'RecordValue' => $v['address'],
                                                'RecordTTL' => 43200,
                                                ];
                                                $addDnsRes = $this->makeRequest('AddDNSRecord', $AddDNSRecord);
                                                if ($addDnsRes["responseMsg"]['statusCode'] != '200') {
                                                    $values["error"] = $addDnsRes["responseMsg"]['statusCode'] . " - " . $addDnsRes["responseMsg"]['message'];
                                                }
                                            }
                                        }
                                }
                            }
                        }
                    } else {
                        if (empty($v['hostname'])  && empty($v['address'])) {
                            $DeleteDNSRecord = [
                             'APIKey' => $params['API Key'],
                             'DNSZoneID' => $DNSZoneId,
                             'DNSZoneRecordID' => $v['id'],
                            ];
                            $delDnsRes = $this->makeRequest('DeleteDNSRecord', $DeleteDNSRecord);
                            if ($delDnsRes["responseMsg"]['statusCode'] != '200') {
                                $values["error"] = $delDnsRes["responseMsg"]['statusCode'] . " - " . $delDnsRes["responseMsg"]['message'];
                            }
                        }
                    }
                } //foreach end
            } //view record end
        } //dns D
    }

    public function getNameServers($params)
    {
              $res = $this->getDomainInfoDetails($params);
              $msgResult = array_key_exists("responseMsg", $res);
        if ($msgResult) {
            if ($res["responseMsg"]['statusCode'] == '200') {
                $info = [];

                for ($i = 1; $i <= 13; $i++) {
                    if (isset($res["responseData"]["nameserver$i"])) {
                        $info[] = $res["responseData"]["nameserver$i"];
                    } else {
                        break;
                    }
                }
            }
        } else {
            $info = [];
        }
              return $info;
    }

    public function setNameServers($params)
    {
        $res = $this->getDomainInfoDetails($params);
        if ($res["responseData"]['isDomainLocked'] != 'True') {
            $DomainNameID = $res["responseData"]['domainNameId'];
            $Nameservers = implode(',', $params['ns']);
            $Nameservers_explide = explode(',', $Nameservers);
            $UpdateNameServer['APIKey'] = $params['API Key'];
            $UpdateNameServer['websiteName'] = $params["sld"] . '.' . $params["tld"];
            $UpdateNameServer['domainNameId'] = $DomainNameID;

            if ($Nameservers_explide[0]) {
                $UpdateNameServer['nameServer1'] = $Nameservers_explide[0];
            }
            if ($Nameservers_explide[1]) {
                $UpdateNameServer['nameServer2'] = $Nameservers_explide[1];
            }
            if ($Nameservers_explide[2]) {
                $UpdateNameServer['nameServer3'] = $Nameservers_explide[2];
            }
            if ($Nameservers_explide[3]) {
                $UpdateNameServer['nameServer4'] = $Nameservers_explide[3];
            }
            if ($Nameservers_explide[4]) {
                $UpdateNameServer['nameServer5'] = $Nameservers_explide[4];
            }
            if ($Nameservers_explide[5]) {
                $UpdateNameServer['nameServer6'] = $Nameservers_explide[5];
            }
            if ($Nameservers_explide[6]) {
                $UpdateNameServer['nameServer7'] = $Nameservers_explide[6];
            }
            if ($Nameservers_explide[7]) {
                $UpdateNameServer['nameServer8'] = $Nameservers_explide[7];
            }
            if ($Nameservers_explide[8]) {
                $UpdateNameServer['nameServer9'] = $Nameservers_explide[8];
            }
            if ($Nameservers_explide[9]) {
                $UpdateNameServer['nameServer10'] = $Nameservers_explide[9];
            }
            if ($Nameservers_explide[10]) {
                $UpdateNameServer['nameServer11'] = $Nameservers_explide[10];
            }
            if ($Nameservers_explide[11]) {
                $UpdateNameServer['nameServer12'] = $Nameservers_explide[11];
            }
            if ($Nameservers_explide[12]) {
                $UpdateNameServer['nameServer13'] = $Nameservers_explide[12];
            }

            $updateRes = $this->makeRequest('UpdateNameServer', $UpdateNameServer);
            if ($updateRes["responseMsg"]['statusCode'] != '200') {
                $valueserror = "Domain Updation Failed (Invalid NameServers)";
            }
        } else {
            $valueserror = "Disable The Status of Lock";
        }
        return $valueserror;
    }

    public function setAutorenew($params)
    {
        //throw new Exception('This function is not supported');
    }

    public function getDomainInfoDetails($params)
    {
        $ViewDomain = [
          'APIKey' => $params['API Key'],
          'websiteName' => $params["sld"] . '.' . $params["tld"]
        ];
        return $this->makeRequest('ViewDomain', $ViewDomain);
    }

    public function getEPPCode($params)
    {
        $res = $this->getDomainInfoDetails($params);
        if ($res["responseMsg"]['authCode']) {
            return $res["responseData"]["authCode"];
        }
    }

    public function getContactInformation($params)
    {
        $res = $this->getDomainInfoDetails($params);
        $RegistrantContactId = substr($res["responseData"]['registrantContactId'], 3);  //
        $AdminContactId = substr($res["responseData"]['adminContactId'], 3);  //
        $BillingContactId = substr($res["responseData"]['billingContactId'], 3);  //
        $TechnicalContactId = substr($res["responseData"]['technicalContactId'], 3);

        $ViewRegistrant = [
            'APIKey' => $params['API Key'],
            'RegistrantContactId' => $RegistrantContactId
        ];
        $contactDetailsRes = $this->makeRequest('ViewRegistrant', $ViewRegistrant);

        $values['Registrant']['OrganizationName']  = array($this->user->lang('Organization'), $contactDetailsRes["responseData"]['companyName']);
        $values['Registrant']['FirstName'] = array($this->user->lang('First Name'), $contactDetailsRes["responseData"]['name']);
        //$values['Registrant']['LastName']  = array($this->user->lang('Last Name'), (string)$contact->LastName);
        $values['Registrant']['Address1']  = array($this->user->lang('Address') . ' 1', $contactDetailsRes["responseData"]['address1'] . $contactDetailsRes["responseData"]['address2'] . $contactDetailsRes["responseData"]['address3']);
        $values['Registrant']['City']      = array($this->user->lang('City'), $contactDetailsRes["responseData"]['city']);
        $values['Registrant']['StateProvince']  = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contactDetailsRes["responseData"]['stateName']);
        $values['Registrant']['Country']   = array($this->user->lang('Country'), $contactDetailsRes["responseData"]['countryName']);
        $values['Registrant']['PostalCode']  = array($this->user->lang('Postal Code'), $contactDetailsRes["responseData"]['postalCode']);
        $values['Registrant']['EmailAddress']     = array($this->user->lang('Email'), $contactDetailsRes["responseData"]['emailAddress']);
        $values['Registrant']['Phone']  = array($this->user->lang('Phone'), $contactDetailsRes["responseData"]['phoneCode'] . substr($contactDetailsRes["responseData"]['phoneNo'], 0, 10));
        if ($RegistrantContactId === $TechnicalContactId) {
            $values['Technical']['OrganizationName']  = array($this->user->lang('Organization'), $contactDetailsRes["responseData"]['companyName']);
            $values['Technical']['FirstName'] = array($this->user->lang('First Name'), $contactDetailsRes["responseData"]['name']);
          //$values['Technical']['LastName']  = array($this->user->lang('Last Name'), (string)$contact->LastName);
            $values['Technical']['Address1']  = array($this->user->lang('Address') . ' 1', $contactDetailsRes["responseData"]['address1'] . $contactDetailsRes["responseData"]['address2'] . $contactDetailsRes["responseData"]['address3']);
            $values['Technical']['City']      = array($this->user->lang('City'), $contactDetailsRes["responseData"]['city']);
            $values['Technical']['StateProvince']  = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contactDetailsRes["responseData"]['stateName']);
            $values['Technical']['Country']   = array($this->user->lang('Country'), $contactDetailsRes["responseData"]['countryName']);
            $values['Technical']['PostalCode']  = array($this->user->lang('Postal Code'), $contactDetailsRes["responseData"]['postalCode']);
            $values['Technical']['EmailAddress']     = array($this->user->lang('Email'), $contactDetailsRes["responseData"]['emailAddress']);
            $values['Technical']['Phone']  = array($this->user->lang('Phone'), $contactDetailsRes["responseData"]['phoneCode'] . substr($contactDetailsRes["responseData"]['phoneNo'], 0, 10));
        } else {
            $ViewRegistrantTech = [
                'APIKey' => $params['API Key'],
                'RegistrantContactId' => $TechnicalContactId
            ];
            $contactDetailsRes1 = $this->makeRequest('ViewRegistrant', $ViewRegistrantTech);

            $values['Technical']['OrganizationName']  = array($this->user->lang('Organization'), $contactDetailsRes1["responseData"]['companyName']);
            $values['Technical']['FirstName'] = array($this->user->lang('First Name'), $contactDetailsRes1["responseData"]['name']);
            //$values['Technical']['LastName']  = array($this->user->lang('Last Name'), (string)$contact->LastName);
            $values['Technical']['Address1']  = array($this->user->lang('Address') . ' 1', $contactDetailsRes1["responseData"]['address1'] . $contactDetailsRes1["responseData"]['address2'] . $contactDetailsRes1["responseData"]['address3']);
            $values['Technical']['City']      = array($this->user->lang('City'), $contactDetailsRes1["responseData"]['city']);
            $values['Technical']['StateProvince']  = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contactDetailsRes1["responseData"]['stateName']);
            $values['Technical']['Country']   = array($this->user->lang('Country'), $contactDetailsRes1["responseData"]['countryName']);
            $values['Technical']['PostalCode']  = array($this->user->lang('Postal Code'), $contactDetailsRes1["responseData"]['postalCode']);
            $values['Technical']['EmailAddress']     = array($this->user->lang('Email'), $contactDetailsRes1["responseData"]['emailAddress']);
            $values['Technical']['Phone']  = array($this->user->lang('Phone'), $contactDetailsRes1["responseData"]['phoneCode'] . substr($contactDetailsRes1["responseData"]['phoneNo'], 0, 10));
        }

        if ($RegistrantContactId === $BillingContactId) {
            $values['Billing']['OrganizationName']  = array($this->user->lang('Organization'), $contactDetailsRes["responseData"]['companyName']);
            $values['Billing']['FirstName'] = array($this->user->lang('First Name'), $contactDetailsRes["responseData"]['name']);
          //$values['Billing']['LastName']  = array($this->user->lang('Last Name'), (string)$contact->LastName);
            $values['Billing']['Address1']  = array($this->user->lang('Address') . ' 1', $contactDetailsRes["responseData"]['address1'] . $contactDetailsRes["responseData"]['address2'] . $contactDetailsRes["responseData"]['address3']);
            $values['Billing']['City']      = array($this->user->lang('City'), $contactDetailsRes["responseData"]['city']);
            $values['Billing']['StateProvince']  = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contactDetailsRes["responseData"]['stateName']);
            $values['Billing']['Country']   = array($this->user->lang('Country'), $contactDetailsRes["responseData"]['countryName']);
            $values['Billing']['PostalCode']  = array($this->user->lang('Postal Code'), $contactDetailsRes["responseData"]['postalCode']);
            $values['Billing']['EmailAddress']     = array($this->user->lang('Email'), $contactDetailsRes["responseData"]['emailAddress']);
            $values['Billing']['Phone']  = array($this->user->lang('Phone'), $contactDetailsRes["responseData"]['phoneCode'] . substr($contactDetailsRes["responseData"]['phoneNo'], 0, 10));
        } else {
            $ViewRegistrantBilling = [
              'APIKey' => $params['API Key'],
              'RegistrantContactId' => $BillingContactId
            ];
            $contactDetailsRes2 = $this->makeRequest('ViewRegistrant', $ViewRegistrantBilling);

            $values['Billing']['OrganizationName']  = array($this->user->lang('Organization'), $contactDetailsRes2["responseData"]['companyName']);
            $values['Billing']['FirstName'] = array($this->user->lang('First Name'), $contactDetailsRes2["responseData"]['name']);
            //$values['Billing']['LastName']  = array($this->user->lang('Last Name'), (string)$contact->LastName);
            $values['Billing']['Address1']  = array($this->user->lang('Address') . ' 1', $contactDetailsRes2["responseData"]['address1'] . $contactDetailsRes2["responseData"]['address2'] . $contactDetailsRes2["responseData"]['address3']);
            $values['Billing']['City']      = array($this->user->lang('City'), $contactDetailsRes2["responseData"]['city']);
            $values['Billing']['StateProvince']  = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contactDetailsRes2["responseData"]['stateName']);
            $values['Billing']['Country']   = array($this->user->lang('Country'), $contactDetailsRes2["responseData"]['countryName']);
            $values['Billing']['PostalCode']  = array($this->user->lang('Postal Code'), $contactDetailsRes2["responseData"]['postalCode']);
            $values['Billing']['EmailAddress']     = array($this->user->lang('Email'), $contactDetailsRes2["responseData"]['emailAddress']);
            $values['Billing']['Phone']  = array($this->user->lang('Phone'), $contactDetailsRes2["responseData"]['phoneCode'] . substr($contactDetailsRes2["responseData"]['phoneNo'], 0, 10));
        }

        if ($RegistrantContactId === $AdminContactId) {
            $values['Admin']['OrganizationName']  = array($this->user->lang('Organization'), $contactDetailsRes["responseData"]['companyName']);
            $values['Admin']['FirstName'] = array($this->user->lang('First Name'), $contactDetailsRes["responseData"]['name']);
          //$values['Admin']['LastName']  = array($this->user->lang('Last Name'), (string)$contact->LastName);
            $values['Admin']['Address1']  = array($this->user->lang('Address') . ' 1', $contactDetailsRes["responseData"]['address1'] . $contactDetailsRes["responseData"]['address2'] . $contactDetailsRes["responseData"]['address3']);
            $values['Admin']['City']      = array($this->user->lang('City'), $contactDetailsRes["responseData"]['city']);
            $values['Admin']['StateProvince']  = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contactDetailsRes["responseData"]['stateName']);
            $values['Admin']['Country']   = array($this->user->lang('Country'), $contactDetailsRes["responseData"]['countryName']);
            $values['Admin']['PostalCode']  = array($this->user->lang('Postal Code'), $contactDetailsRes["responseData"]['postalCode']);
            $values['Admin']['EmailAddress']     = array($this->user->lang('Email'), $contactDetailsRes["responseData"]['emailAddress']);
            $values['Admin']['Phone']  = array($this->user->lang('Phone'), $contactDetailsRes["responseData"]['phoneCode'] . substr($contactDetailsRes["responseData"]['phoneNo'], 0, 10));
        } else {
            $ViewRegistrantAdmin = [
                'APIKey' => $params['API Key'],
                'RegistrantContactId' => $AdminContactId
            ];
            $contactDetailsRes3 = $this->makeRequest('ViewRegistrant', $ViewRegistrantAdmin);

            $values['Admin']['OrganizationName']  = array($this->user->lang('Organization'), $contactDetailsRes3["responseData"]['companyName']);
            $values['Admin']['FirstName'] = array($this->user->lang('First Name'), $contactDetailsRes3["responseData"]['name']);
            //$values['Admin']['LastName']  = array($this->user->lang('Last Name'), (string)$contact->LastName);
            $values['Admin']['Address1']  = array($this->user->lang('Address') . ' 1', $contactDetailsRes3["responseData"]['address1'] . $contactDetailsRes3["responseData"]['address2'] . $contactDetailsRes3["responseData"]['address3']);
            $values['Admin']['City']      = array($this->user->lang('City'), $contactDetailsRes3["responseData"]['city']);
            $values['Admin']['StateProvince']  = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contactDetailsRes3["responseData"]['stateName']);
            $values['Admin']['Country']   = array($this->user->lang('Country'), $contactDetailsRes3["responseData"]['countryName']);
            $values['Admin']['PostalCode']  = array($this->user->lang('Postal Code'), $contactDetailsRes3["responseData"]['postalCode']);
            $values['Admin']['EmailAddress']     = array($this->user->lang('Email'), $contactDetailsRes3["responseData"]['emailAddress']);
            $values['Admin']['Phone']  = array($this->user->lang('Phone'), $contactDetailsRes3["responseData"]['phoneCode'] . substr($contactDetailsRes3["responseData"]['phoneNo'], 0, 10));
        }
        return $values;
    }

    public function setContactInformation($params)
    {
          $res = $this->getDomainInfoDetails($params);
          $msgResult = array_key_exists("responseMsg", $res);
        if ($msgResult) {
            $countryCode = $this->countryCodePhone($params['Registrant_Country']);
            $Phone = explode($countryCode, $params['Registrant_Phone']);
            $PHone_NO = (($Phone[1]) ? $Phone[1] : $params['Registrant_Phone']);
            $argument = [
            'APIKey' => $params['API Key'],
            'PhoneNo_cc' => $countryCode,
            'PhoneNo' => $PHone_NO,
            'Id' => $res["responseData"]['customerId'],
            'domainId' => $res["responseData"]['domainNameId'],
            'EmailAddress' => $params['Registrant_EmailAddress'],
            'Name' => $params["Registrant_FirstName"],
            'Address1' => $params["Registrant_Address1"],
            'City' => $params['Registrant_City'],
            'StateName' => $params["Registrant_StateProvince"],
            'CountryName' => $params["Registrant_Country"],
            'Zip' => $params["Registrant_PostalCode"],
            'CompanyName' => $params["Registrant_OrganizationName"],
            ];
            $updateRes = $this->makeRequest('ModifyRegistrantContact_whmcs', $argument);
            if ($updateRes["responseMsg"]['statusCode'] != '200') {
                $errorData = $updateRes["responseMsg"]['message'];
            }
        } else {
            if ($updateRes["responseMsg"]['statusCode'] != '200') {
                $errorData = $updateRes["responseMsg"]['message'];
            }
        }
          return $errorData;
    }

    public function getTransferStatus($params)
    {
        $arguments = [
          'APIKey' => $params['API Key'],
          'domainName' => $params["sld"] . '.' . $params["tld"]
        ];
        $res = $this->makeRequest('syncTransfer', $arguments);
        if ($res["responseMsg"]['statusCode'] != '200') {
            $resultmsg = $res["responseMsg"]['message'];
        } else {
            if ($res["responseData"]['status'] == 'completed') {
                $resultmsg = $date = date('Y-m-d', intval($res["responseData"]['expiryDate'] / 1000));
                $userPackage = new UserPackage($params['userPackageId']);
                $userPackage->setCustomField('Transfer Status', 'Completed');
            } elseif ($res["responseData"]['status'] == 'pending') {
                $resultmsg = "Transfer pending";
            } else {
                $resultmsg = $res["responseData"]['reason'];
            }
        }
        return $resultmsg;
    }

    public function getTLDsAndPrices($params)
    {
        $arguments['APIKey'] = $params['API Key'];
        $response = $this->makeRequest('tldsync', $arguments);
              $tlds = [];
        foreach ($response as $extension) {
            $tld = substr($extension['tld'], 1);
            $tlds[$tld]['pricing']['register'] = $extension['registrationPrice'];
            $tlds[$tld]['pricing']['transfer'] = $extension['transferPrice'];
            $tlds[$tld]['pricing']['renew'] = $extension['renewalPrice'];
        }
        return $tlds;
    }

    public function checkDomain($params)
    {
        $arguments = [
          'APIKey' => $params['API Key'],
          'websiteName' => $params["sld"] . '.' . $params["tld"]
        ];
        $res = $this->makeRequest('checkDomain', $arguments);
        if ($res["responseMsg"]['statusCode'] == '200') { //Domain Available for registration
            $domains[] = array('tld' => $params['tld'], 'domain' => $params['sld'], 'status' => 0);
        } else {
            $domains[] = array('tld' => $params['tld'], 'domain' => $params['sld'], 'status' => 1);
        }
        return array("result" => $domains);
    }

    public function getGeneralInfo($params)
    {
        $res = $this->getDomainInfoDetails($params);
        if ($res['responseMsg']['statusCode'] == '200') {
            $data = [];
            $data['id'] = (int)$res["responseData"]['domainNameId'];
            $data['domain'] = (string)$res["responseData"]['websiteName'];
            $data['expiration'] = ($res["responseData"]['expirationDate'] ? $res["responseData"]['expirationDate'] : 'N/A');
            $data['registrationstatus'] = $res["responseData"]['Status'];
            $data['purchasestatus'] = $res["responseData"]['Status'];
            $data['autorenew'] = false;
            $data['eppCode'] = $res["responseData"]['authCode'];
            return $data;
        }
    }

    public function makeRequest($command, $arguments)
    {
        $url = $this->liveUrl;
        $url .= $command;
        $url .= '/?' . http_build_query($arguments);
        $response = NE_Network::curlRequest($this->settings, $url);

        if ($response instanceof CE_Error) {
            throw new CE_Exception($response);
        }

        $response = json_decode($response, true);
        CE_Lib::log(4, $response);
        return $response;
    }
}

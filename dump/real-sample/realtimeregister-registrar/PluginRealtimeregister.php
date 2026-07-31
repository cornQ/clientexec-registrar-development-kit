<?php

require_once 'modules/admin/models/RegistrarPlugin.php';

/**
 * Realtime Register Registrar Plugin for Clientexec
 *
 * Uses the Realtime Register REST API (https://dm.realtimeregister.com/docs/api/)
 * Production endpoint: https://api.yoursrs.com/
 * Test (OT&E) endpoint: https://api.yoursrs-ote.com/
 *
 * Authentication: API key sent as "Authorization: ApiKey <key>" header.
 *
 * Key concepts:
 *  - Domains are referenced by their full name (e.g. "example.com")
 *  - Contacts are managed as handles that must exist before registering a domain.
 *    This plugin creates/updates a contact handle derived from the customer handle
 *    and the registrant email address.
 *  - The "customer" field in every domain request must be your RTR customer handle
 *    (configured in the plugin settings).
 */
class PluginRealtimeregister extends RegistrarPlugin
{
    public $features = [
        'nameSuggest'   => false,
        'importDomains' => true,
        'importPrices'  => true,
    ];

    // -------------------------------------------------------------------------
    // Plugin configuration variables shown in the Clientexec admin panel
    // -------------------------------------------------------------------------

    public function getVariables()
    {
        $variables = array(
            lang('Plugin Name') => array(
                'type'        => 'hidden',
                'description' => lang('How CE sees this plugin (not to be confused with the Signup Name)'),
                'value'       => lang('Realtime Register')
            ),
            lang('Use OT&E (Test) Server') => array(
                'type'        => 'yesno',
                'description' => lang('Select Yes to use the Realtime Register OT&E test environment. Transactions will not be real.'),
                'value'       => 0
            ),
            lang('API Key') => array(
                'type'        => 'password',
                'description' => lang('Enter your Realtime Register API key. Generate one in the RTR portal under your profile page.'),
                'value'       => ''
            ),
            lang('Customer Handle') => array(
                'type'        => 'text',
                'description' => lang('Enter your Realtime Register customer handle (e.g. RTR-12345).'),
                'value'       => ''
            ),
            lang('Supported Features') => array(
                'type'        => 'label',
                'description' => '* ' . lang('TLD Lookup') . '<br>* ' . lang('Domain Registration') . '<br>* ' . lang('Domain Registration with Privacy Protect') . '<br>* ' . lang('Existing Domain Importing') . '<br>* ' . lang('Get / Set Auto Renew Status') . '<br>* ' . lang('Get / Set Nameserver Records') . '<br>* ' . lang('Get / Set Contact Information') . '<br>* ' . lang('Get / Set Registrar Lock') . '<br>* ' . lang('Initiate Domain Transfer') . '<br>* ' . lang('Automatically Renew Domain') . '<br>* ' . lang('Get EPP / Auth Code'),
                'value'       => ''
            ),
            lang('Actions') => array(
                'type'        => 'hidden',
                'description' => lang('Current actions that are active for this plugin (when a domain isn\'t registered)'),
                'value'       => 'Register'
            ),
            lang('Registered Actions') => array(
                'type'        => 'hidden',
                'description' => lang('Current actions that are active for this plugin (when a domain is registered)'),
                'value'       => 'Renew (Renew Domain),DomainTransferWithPopup (Initiate Transfer),Cancel',
            ),
            lang('Registered Actions For Customer') => array(
                'type'        => 'hidden',
                'description' => lang('Current actions that are active for this plugin (when a domain is registered)'),
                'value'       => '',
            )
        );

        return $variables;
    }

    // -------------------------------------------------------------------------
    // Price / TLD import
    // -------------------------------------------------------------------------

    /**
     * Fetch TLDs and pricing from the customer's RTR pricelist.
     * RTR endpoint: GET /v2/customers/{customer}/pricelist
     */
    public function getTLDsAndPrices($params)
    {
        $customer = $params['Customer Handle'];
        $response = $this->makeRequest($params, 'GET', "v2/customers/{$customer}/pricelist");

        $tlds = [];

        if (!isset($response['prices']) || !is_array($response['prices'])) {
            return $tlds;
        }

        foreach ($response['prices'] as $row) {
            $product = strtolower(trim($row['product'] ?? ''));
            $action  = strtoupper(trim($row['action'] ?? ''));
            $rawPrice = $row['price'] ?? null;

            if ($product === '' || $rawPrice === null) {
                continue;
            }

            // Only domain products
            if (strpos($product, 'domain_') !== 0) {
                continue;
            }

            // Only real pricing actions
            if (!in_array($action, ['CREATE', 'RENEW', 'TRANSFER'], true)) {
                continue;
            }

            // Extract raw tld string
            $raw = substr($product, 7);

            // Remove suffixes
            $raw = preg_replace('/_(legacy|sld|fsld|rsld)$/', '', $raw);

            // Skip IDN
            if (strpos($raw, 'xn--') === 0) {
                continue;
            }

            $tld = $this->normalizeTld($raw);

            if ($tld === '') {
                continue;
            }

            // Convert minor units → actual price
            $price = round(((float)$rawPrice) / 100, 2);

            if (!isset($tlds[$tld])) {
                $tlds[$tld] = ['pricing' => []];
            }

            switch ($action) {
                case 'CREATE':
                    $tlds[$tld]['pricing']['register'] = $price;
                    break;
                case 'RENEW':
                    $tlds[$tld]['pricing']['renew'] = $price;
                    break;
                case 'TRANSFER':
                    $tlds[$tld]['pricing']['transfer'] = $price;
                    break;
            }
        }

        // Only keep TLDs that can actually be registered
        foreach ($tlds as $tld => $data) {
            if (!isset($data['pricing']['register'])) {
                unset($tlds[$tld]);
            }
        }

        return $tlds;
    }

    private function normalizeTld($raw)
    {
        // CentralNic products: domain_centralnic_cn_com → cn.com
        if (strpos($raw, 'centralnic_') === 0) {
            $parts = explode('_', $raw);
            array_shift($parts); // remove "centralnic"
            return implode('.', $parts);
        }

        // Default
        return $raw;
    }

    // -------------------------------------------------------------------------
    // Domain availability check
    // -------------------------------------------------------------------------

    /**
     * Check domain availability.
     *
     * Return codes:
     *  0 = available
     *  1 = registered / unavailable
     *  2 = unsupported TLD
     *  3 = invalid domain
     *  5 = could not contact registry
     */
    public function checkDomain($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        try {
            $response = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}/check");
        } catch (Exception $e) {
            return array(5, $e->getMessage());
        }

        $domains = [];

        if (!isset($response['results']) || !is_array($response['results'])) {
            // Single-domain response format
            $available = isset($response['available']) ? (bool)$response['available'] : null;
            if ($available === null) {
                return array(5, 'Unexpected response from Realtime Register');
            }
            $aDomain = DomainNameGateway::splitDomain($domainName);
            $domains[] = array(
                'tld'    => $aDomain[1],
                'domain' => $aDomain[0],
                'status' => $available ? 0 : 1
            );
        } else {
            foreach ($response['results'] as $result) {
                $name      = $result['domainName'] ?? ($result['domain'] ?? '');
                $available = isset($result['available']) ? (bool)$result['available'] : false;
                $reason    = strtolower($result['reason'] ?? '');

                if ($name === '') {
                    continue;
                }

                if ($reason === 'invalid_tld' || $reason === 'unsupported') {
                    $status = 2;
                } else {
                    $status = $available ? 0 : 1;
                }

                $aDomain = DomainNameGateway::splitDomain($name);
                $domains[] = array(
                    'tld'    => $aDomain[1],
                    'domain' => $aDomain[0],
                    'status' => $status
                );
            }
        }

        return array('result' => $domains);
    }

    // -------------------------------------------------------------------------
    // Domain registration
    // -------------------------------------------------------------------------

    public function doRegister($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->registerDomain($this->buildRegisterParams($userPackage, $params));
        $userPackage->setCustomField(
            'Registrar Order Id',
            $userPackage->getCustomField('Registrar') . '-' . $orderid
        );
        return $userPackage->getCustomField('Domain Name') . ' has been registered.';
    }

    public function registerDomain($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        // Ensure the contact handle exists in RTR, creating/updating as needed
        $contactHandle = $this->ensureContact($params);

        $body = array(
            'customer'  => $params['Customer Handle'],
            'registrant' => $contactHandle,
            'period'    => (int)$params['NumYears'] * 12,  // RTR uses months
            'autoRenew' => false,
        );

        // Privacy protect / ID protect
        if (isset($params['package_addons']['IDPROTECT']) && $params['package_addons']['IDPROTECT'] == 1) {
            $body['privacyProtect'] = true;
        }

        // Nameservers
        $ns = [];
        for ($i = 1; $i <= 13; $i++) {
            if (!empty($params["NS{$i}"]['hostname'])) {
                $ns[] = $params["NS{$i}"]['hostname'];
            }
        }
        if (!empty($ns)) {
            $body['ns'] = $ns;
        }

        $response = $this->makeRequest($params, 'POST', "v2/domains/{$domainName}", $body);

        // RTR returns 201 on success; domain name is the identifier
        return $domainName;
    }

    // -------------------------------------------------------------------------
    // Domain renewal
    // -------------------------------------------------------------------------

    public function doRenew($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->renewDomain($this->buildRenewParams($userPackage, $params));
        $userPackage->setCustomField(
            'Registrar Order Id',
            $userPackage->getCustomField('Registrar') . '-' . $orderid
        );
        return $userPackage->getCustomField('Domain Name') . ' has been renewed.';
    }

    public function renewDomain($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        $body = array(
            'period' => (int)$params['NumYears'] * 12,
        );

        $this->makeRequest($params, 'POST', "v2/domains/{$domainName}/renew", $body);

        return $domainName;
    }

    // -------------------------------------------------------------------------
    // Domain transfer
    // -------------------------------------------------------------------------

    public function doDomainTransferWithPopup($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $transferId  = $this->initiateTransfer($this->buildTransferParams($userPackage, $params));
        $userPackage->setCustomField(
            'Registrar Order Id',
            $userPackage->getCustomField('Registrar') . '-' . $transferId
        );
        $userPackage->setCustomField('Transfer Status', $transferId);
        return 'Transfer of ' . $userPackage->getCustomField('Domain Name') . ' has been initiated.';
    }

    public function initiateTransfer($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        $contactHandle = $this->ensureContact($params);

        $body = array(
            'customer'   => $params['Customer Handle'],
            'registrant' => $contactHandle,
            'autoRenew'  => true,
        );

        if (!empty($params['eppCode'])) {
            $body['authcode'] = $params['eppCode'];
        }

        $ns = [];
        for ($i = 1; $i <= 13; $i++) {
            if (!empty($params["NS{$i}"]['hostname'])) {
                $ns[] = $params["NS{$i}"]['hostname'];
            }
        }
        if (!empty($ns)) {
            $body['ns'] = $ns;
        }

        $this->makeRequest($params, 'POST', "v2/domains/{$domainName}/transfer", $body);

        return $domainName;
    }

    public function getTransferStatus($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $domainName  = $userPackage->getCustomField('Domain Name');

        $response = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");

        $status = $response['status'] ?? 'unknown';

        if (strtolower($status) === 'active' || strtolower($status) === 'inactive') {
            $userPackage->setCustomField('Transfer Status', 'Completed');
            return 'Transfer completed successfully';
        }

        return ucfirst(strtolower(str_replace('_', ' ', $status)));
    }

    // -------------------------------------------------------------------------
    // EPP / auth code retrieval
    // -------------------------------------------------------------------------

    /**
     * Fetch the EPP/auth code for a domain directly from the RTR registry.
     * RTR surfaces the authcode in the domain GET response.
     */
    public function getEPPCode($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response   = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");

        if (!empty($response['authcode'])) {
            return $response['authcode'];
        }

        throw new CE_Exception('Realtime Register Plugin: No auth code returned for ' . $domainName);
    }

    // -------------------------------------------------------------------------
    // Registrar lock
    // -------------------------------------------------------------------------

    public function getRegistrarLock($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response   = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");

        $statuses = $response['status'] ?? [];

        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        $lockStatuses = [
            'CLIENT_TRANSFER_PROHIBITED',
            'REGISTRAR_TRANSFER_PROHIBITED',
            'SERVER_TRANSFER_PROHIBITED',
            'IRTPC_TRANSFER_PROHIBITED',
        ];

        foreach ($statuses as $status) {
            if (in_array($status, $lockStatuses, true)) {
                return 1;
            }
        }

        return 0;
    }

    public function doSetRegistrarLock($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $this->setRegistrarLock($this->buildLockParams($userPackage, $params));
        return 'Updated Registrar Lock.';
    }

    public function setRegistrarLock($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        $domain = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");
        $statuses = $domain['statuses'] ?? [];

        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        $statuses = array_values(array_unique(array_filter($statuses)));

        if ($params['lock']) {
            if (!in_array('CLIENT_TRANSFER_PROHIBITED', $statuses, true)) {
                $statuses[] = 'CLIENT_TRANSFER_PROHIBITED';
            }
        } else {
            $statuses = array_values(array_filter(
                $statuses,
                fn($s) => $s !== 'CLIENT_TRANSFER_PROHIBITED'
            ));
        }

        $this->makeRequest(
            $params,
            'POST',
            "v2/domains/{$domainName}/update",
            [
                'status' => $statuses
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Auto-renew
    // -------------------------------------------------------------------------

    public function setAutorenew($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        $body = array(
            'autoRenew' => (bool)$params['autorenew'],
        );

        $this->makeRequest($params, 'POST', "v2/domains/{$domainName}", $body);
        return 'Domain updated successfully';
    }

    // -------------------------------------------------------------------------
    // Nameservers
    // -------------------------------------------------------------------------

    public function getNameServers($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response   = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");

        $info            = array();
        $info['hasDefault']  = false;
        $info['usesDefault'] = false;

        $ns = $response['ns'] ?? [];
        foreach ($ns as $server) {
            $info[] = $server;
        }

        return $info;
    }

    public function setNameServers($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        $ns = [];
        if (!$params['default']) {
            foreach ($params['ns'] as $server) {
                if (!empty($server)) {
                    $ns[] = $server;
                }
            }
        }

        $body = array('ns' => $ns);

        $this->makeRequest($params, 'POST', "v2/domains/{$domainName}/update", $body);
        return 'Name servers updated successfully.';
    }

    // -------------------------------------------------------------------------
    // DNS Records (via RTR DNS Zones API)
    // -------------------------------------------------------------------------

    public function getDNS($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        try {
            $response = $this->makeRequest($params, 'GET', "v2/dns/{$domainName}");
        } catch (Exception $e) {
            return array(
                'records' => [],
                'types'   => $this->getSupportedDNSTypes(),
                'default' => true
            );
        }

        $records = [];
        $allRecords = $response['records'] ?? [];
        foreach ($allRecords as $record) {
            $records[] = array(
                'id'       => $record['name'] . '_' . $record['type'],
                'hostname' => $record['name'],
                'address'  => $record['content'],
                'type'     => strtoupper($record['type']),
                'ttl'      => $record['ttl'] ?? 3600,
            );
        }

        return array(
            'records' => $records,
            'types'   => $this->getSupportedDNSTypes(),
            'default' => false,
        );
    }

    public function setDNS($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];

        $records = [];
        foreach ($params['records'] as $record) {
            $records[] = array(
                'name'    => $record['hostname'],
                'type'    => strtolower($record['type']),
                'content' => $record['address'],
                'ttl'     => $record['ttl'] ?? 3600,
            );
        }

        $body = array('records' => $records);

        // RTR DNS Zone update: POST /v2/dns/{domainName} replaces all records
        $this->makeRequest($params, 'POST', "v2/dns/{$domainName}", $body);

        return $this->user->lang('Host information updated successfully');
    }

    private function getSupportedDNSTypes()
    {
        return array('A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SRV', 'CAA');
    }

    // -------------------------------------------------------------------------
    // Contact information
    // -------------------------------------------------------------------------

    public function getContactInformation($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $customer = $params['Customer Handle'];

        $domain = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");

        $roleHandles = array(
            'Registrant' => $domain['registrant'] ?? null,
            'Admin' => null,
            'Tech' => null,
            'AuxBilling' => null,
        );

        $domainContacts = $domain['contacts'] ?? array();
        if (!is_array($domainContacts)) {
            $domainContacts = array();
        }

        foreach ($domainContacts as $contactRole) {
            $role = strtoupper((string)($contactRole['role'] ?? ''));
            $handle = $contactRole['handle'] ?? null;

            if (!$handle) {
                continue;
            }

            if ($role === 'ADMIN') {
                $roleHandles['Admin'] = $handle;
            } elseif ($role === 'TECH') {
                $roleHandles['Tech'] = $handle;
            } elseif ($role === 'BILLING') {
                $roleHandles['AuxBilling'] = $handle;
            }
        }

        if ($roleHandles['Admin'] === null) {
            $roleHandles['Admin'] = $roleHandles['Registrant'];
        }
        if ($roleHandles['Tech'] === null) {
            $roleHandles['Tech'] = $roleHandles['Registrant'];
        }
        if ($roleHandles['AuxBilling'] === null) {
            $roleHandles['AuxBilling'] = $roleHandles['Registrant'];
        }

        $info = array();

        foreach ($roleHandles as $type => $handle) {
            if ($handle === null) {
                $info[$type] = $this->emptyContactInfo($type);
                continue;
            }

            try {
                $contact = $this->makeRequest(
                    $params,
                    'GET',
                    "v2/customers/{$customer}/contacts/{$handle}"
                );
            } catch (Exception $e) {
                $info[$type] = $this->emptyContactInfo($type);
                continue;
            }

            $address = $contact['addressLine'] ?? array();
            if (!is_array($address)) {
                $address = array();
            }

            $fullName = trim((string)($contact['name'] ?? ''));
            $firstName = '';
            $lastName = '';

            if ($fullName !== '') {
                $nameParts = preg_split('/\s+/', $fullName, 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
            }

            $info[$type]['OrganizationName'] = array($this->user->lang('Organization'), $contact['organization'] ?? '');
            $info[$type]['FirstName'] = array($this->user->lang('First Name'), $firstName);
            $info[$type]['LastName'] = array($this->user->lang('Last Name'), $lastName);
            $info[$type]['Address1'] = array($this->user->lang('Address') . ' 1', $address[0] ?? '');
            $info[$type]['Address2'] = array($this->user->lang('Address') . ' 2', $address[1] ?? '');
            $info[$type]['City'] = array($this->user->lang('City'), $contact['city'] ?? '');
            $info[$type]['StateProvince'] = array($this->user->lang('Province') . '/' . $this->user->lang('State'), $contact['state'] ?? '');
            $info[$type]['Country'] = array($this->user->lang('Country'), $contact['country'] ?? '');
            $info[$type]['PostalCode'] = array($this->user->lang('Postal Code'), $contact['postalCode'] ?? '');
            $info[$type]['EmailAddress'] = array($this->user->lang('E-mail'), $contact['email'] ?? '');
            $info[$type]['Phone'] = array($this->user->lang('Phone'), $contact['voice'] ?? '');
            $info[$type]['Fax'] = array($this->user->lang('Fax'), $contact['fax'] ?? '');
        }

        return $info;
    }

    public function setContactInformation($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $customer   = $params['Customer Handle'];
        $type       = $params['type'];

        $prefix = $type . '_';

        $name = trim(
            ($params[$prefix . 'FirstName'] ?? '') . ' ' .
            ($params[$prefix . 'LastName'] ?? '')
        );

        if ($name === '') {
            $name = 'Contact';
        }

        $addressLine = array_values(array_filter([
            trim((string)($params[$prefix . 'Address1'] ?? '')),
            trim((string)($params[$prefix . 'Address2'] ?? '')),
            trim((string)($params[$prefix . 'Address3'] ?? '')),
        ]));

        if (count($addressLine) === 0) {
            $addressLine[] = '-';
        }

        $body = [
            'name'        => $name,
            'addressLine' => $addressLine,
            'city'        => trim((string)($params[$prefix . 'City'] ?? '')),
            'postalCode'  => trim((string)($params[$prefix . 'PostalCode'] ?? '')),
            'country'     => strtoupper(trim((string)($params[$prefix . 'Country'] ?? ''))),
            'email'       => trim((string)($params[$prefix . 'EmailAddress'] ?? '')),
            'voice'       => $this->formatPhone(
                $params[$prefix . 'Phone'] ?? '',
                $params[$prefix . 'Country'] ?? ''
            ),
        ];

        $organization = trim((string)($params[$prefix . 'OrganizationName'] ?? ''));
        if ($organization !== '') {
            $body['organization'] = $organization;
        }

        $state = trim((string)($params[$prefix . 'StateProvince'] ?? ''));
        if ($state !== '') {
            $body['state'] = $state;
        }

        $fax = trim((string)($params[$prefix . 'Fax'] ?? ''));
        if ($fax !== '') {
            $body['fax'] = $fax;
        }

        // Get correct handle based on role
        $domain = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");

        $handle = null;

        if ($type === 'Registrant') {
            $handle = $domain['registrant'] ?? null;
        } else {
            $contacts = $domain['contacts'] ?? [];
            if (!is_array($contacts)) {
                $contacts = [];
            }

            foreach ($contacts as $contact) {
                $role = strtoupper($contact['role'] ?? '');
                if (
                    ($type === 'Admin' && $role === 'ADMIN') ||
                    ($type === 'Tech' && $role === 'TECH') ||
                    ($type === 'AuxBilling' && $role === 'BILLING')
                ) {
                    $handle = $contact['handle'] ?? null;
                    break;
                }
            }

            if ($handle === null) {
                $handle = $domain['registrant'] ?? null;
            }
        }

        if ($handle) {
            $this->makeRequest(
                $params,
                'POST',
                "v2/customers/{$customer}/contacts/{$handle}/update",
                $body
            );
        }

        return $this->user->lang('Contact Information updated successfully.');
    }

    // -------------------------------------------------------------------------
    // General domain info (used by CE to populate domain details page)
    // -------------------------------------------------------------------------

    public function getGeneralInfo($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $response   = $this->makeRequest($params, 'GET', "v2/domains/{$domainName}");

        $data = array();
        $data['id']                 = $response['domainName'] ?? $domainName;
        $data['domain']             = $response['domainName'] ?? $domainName;
        $data['expiration']         = isset($response['expiryDate']) ? date('m/d/Y', strtotime($response['expiryDate'])) : '';

        $status = $response['status'] ?? '';
        if (is_array($status)) {
            $status = $status[0] ?? '';
        }

        $data['is_registered']      = in_array(strtoupper((string)$status), ['ACTIVE', 'INACTIVE']);
        $data['is_expired']         = strtoupper((string)$status) === 'EXPIRED';
        $data['autorenew']          = isset($response['autoRenew']) ? (int)(bool)$response['autoRenew'] : 0;

        return $data;
    }

    // -------------------------------------------------------------------------
    // Domain list import
    // -------------------------------------------------------------------------

    public function fetchDomains($params)
    {
        $customer = $params['Customer Handle'];
        $page     = max(1, (int)($params['next'] ?? 1));
        $perPage  = 25;
        $offset   = ($page - 1) * $perPage;

        $response = $this->makeRequest(
            $params,
            'GET',
            "v2/domains?customer={$customer}&limit={$perPage}&offset={$offset}"
        );

        $domainsList = array();
        $entities    = $response['entities'] ?? [];

        foreach ($entities as $domain) {
            $fullName = $domain['domainName'] ?? '';
            if (!$fullName) {
                continue;
            }
            $parts = DomainNameGateway::splitDomain($fullName);
            $data  = array(
                'id'  => $fullName,
                'sld' => $parts[0],
                'tld' => $parts[1],
                'exp' => isset($domain['expiryDate']) ? date('m/d/Y', strtotime($domain['expiryDate'])) : 'n/a',
            );
            $domainsList[] = $data;
        }

        $total    = $response['pagination']['total'] ?? count($domainsList);
        $metaData = array(
            'total'      => $total,
            'next'       => $page + 1,
            'start'      => $offset + 1,
            'end'        => min($offset + $perPage, $total),
            'numPerPage' => $perPage,
        );

        return array($domainsList, $metaData);
    }

    // -------------------------------------------------------------------------
    // Privacy / ID-protect (maps to RTR privacyProtect)
    // -------------------------------------------------------------------------

    public function enablePrivateRegistration($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $this->makeRequest($params, 'POST', "v2/domains/{$domainName}", array('privacyProtect' => true));
        return 'Privacy protect enabled.';
    }

    public function disablePrivateRegistration($params)
    {
        $domainName = $params['sld'] . '.' . $params['tld'];
        $this->makeRequest($params, 'POST', "v2/domains/{$domainName}", array('privacyProtect' => false));
        return 'Privacy protect disabled.';
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Ensure a RTR contact handle exists for the registrant data in $params.
     * Creates or updates a contact keyed by a handle derived from the customer
     * handle and the registrant's email address.
     *
     * @return string The contact handle
     */
    private function ensureContact($params)
    {
        $customer = $params['Customer Handle'];

        $email = strtolower(trim($params['RegistrantEmailAddress'] ?? 'contact'));
        $rawHandle = preg_replace('/[^a-z0-9\-_@.]/i', '', $email);
        $handle = substr(strtolower($customer . '-' . $rawHandle), 0, 40);

        $name = trim(($params['RegistrantFirstName'] ?? '') . ' ' . ($params['RegistrantLastName'] ?? ''));
        if ($name === '') {
            $name = 'Contact';
        }

        $addressLine = array_values(array_filter([
            trim((string)($params['RegistrantAddress1'] ?? '')),
            trim((string)($params['RegistrantAddress2'] ?? '')),
            trim((string)($params['RegistrantAddress3'] ?? '')),
        ]));

        if (count($addressLine) === 0) {
            $addressLine[] = '-';
        }

        $body = [
            'name'        => $name,
            'addressLine' => $addressLine,
            'postalCode'  => trim((string)($params['RegistrantPostalCode'] ?? '')),
            'city'        => trim((string)($params['RegistrantCity'] ?? '')),
            'country'     => strtoupper(trim((string)($params['RegistrantCountry'] ?? ''))),
            'email'       => trim((string)($params['RegistrantEmailAddress'] ?? '')),
            'voice'       => $this->formatPhone(
                $params['RegistrantPhone'] ?? '',
                $params['RegistrantCountry'] ?? ''
            ),
        ];

        $organization = trim((string)($params['RegistrantOrganizationName'] ?? ''));
        if ($organization !== '') {
            $body['organization'] = $organization;
        }

        $state = trim((string)($params['RegistrantStateProvince'] ?? ''));
        if ($state !== '') {
            $body['state'] = $state;
        }

        try {
            $this->makeRequest(
                $params,
                'POST',
                "v2/customers/{$customer}/contacts/{$handle}",
                $body
            );
        } catch (CE_Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                $this->makeRequest(
                    $params,
                    'POST',
                    "v2/customers/{$customer}/contacts/{$handle}/update",
                    $body
                );
            } else {
                throw $e;
            }
        }

        return $handle;
    }

    /**
     * Return empty contact info structure for a given type.
     */
    private function emptyContactInfo($type)
    {
        return array(
            'OrganizationName' => array($this->user->lang('Organization'), ''),
            'FirstName'        => array($this->user->lang('First Name'),   ''),
            'LastName'         => array($this->user->lang('Last Name'),    ''),
            'Address1'         => array($this->user->lang('Address') . ' 1', ''),
            'Address2'         => array($this->user->lang('Address') . ' 2', ''),
            'City'             => array($this->user->lang('City'),         ''),
            'StateProvince'    => array($this->user->lang('Province') . '/' . $this->user->lang('State'), ''),
            'Country'          => array($this->user->lang('Country'),      ''),
            'PostalCode'       => array($this->user->lang('Postal Code'),  ''),
            'EmailAddress'     => array($this->user->lang('E-mail'),       ''),
            'Phone'            => array($this->user->lang('Phone'),        ''),
            'Fax'              => array($this->user->lang('Fax'),          ''),
        );
    }

    /**
     * Format a phone number to E.164 format (+CC.Number) as expected by RTR.
     */
    private function formatPhone($phone, $country)
    {
        // Strip everything except digits
        $phone = preg_replace('/[^\d]/', '', $phone);

        if ($phone === '') {
            return '';
        }

        $query  = "SELECT phone_code FROM country WHERE iso=? AND phone_code != ''";
        $result = $this->db->query($query, $country);
        if (!$row = $result->fetch()) {
            return '+' . $phone;
        }

        $code  = $row['phone_code'];
        $phone = preg_replace("/^($code)(\\d+)/", '+\1.\2', $phone);
        if ($phone[0] === '+') {
            return $phone;
        }

        return "+{$code}.{$phone}";
    }

    public function sendTransferKey($params)
    {
    }

    // -------------------------------------------------------------------------
    // HTTP transport
    // -------------------------------------------------------------------------

    /**
     * Make an authenticated REST request to the Realtime Register API.
     *
     * @param array  $params   Plugin params (must include 'API Key' and 'Customer Handle')
     * @param string $method   HTTP method: GET | POST | DELETE
     * @param string $endpoint API path, e.g. "v2/domains/example.com"
     * @param array  $body     Optional JSON body for POST requests
     *
     * @return array Decoded JSON response
     * @throws CE_Exception on HTTP or API errors
     */
    public function makeRequest($params, $method, $endpoint, $body = null)
    {
        $useOte = (bool)@$this->settings->get('plugin_realtimeregister_Use OT&E (Test) Server');

        $baseUrl = $useOte
            ? 'https://api.yoursrs-ote.com/'
            : 'https://api.yoursrs.com/';

        $url    = $baseUrl . ltrim($endpoint, '/');
        $apiKey = $params['API Key'] ?? '';

        $headers = [
            'Authorization: ApiKey ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        CE_Lib::log(4, 'Realtime Register Request: ' . strtoupper($method) . ' ' . $url);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            curl_setopt($ch, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, $caPathOrFile);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($body !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $jsonBody = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            CE_Lib::log(4, 'Realtime Register Body: ' . $jsonBody);
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new CE_Exception(
                'Realtime Register Plugin: cURL error - ' . $error,
                EXCEPTION_CODE_CONNECTION_ISSUE
            );
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        CE_Lib::log(4, 'Realtime Register HTTP Code: ' . $httpCode);
        CE_Lib::log(4, 'Realtime Register Response: ' . $response);

        // Handle empty response (valid for 204 etc)
        if (trim($response) === '') {
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['http_code' => $httpCode];
            }

            throw new CE_Exception(
                'Realtime Register Plugin: Empty response (HTTP ' . $httpCode . ')',
                EXCEPTION_CODE_CONNECTION_ISSUE
            );
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CE_Exception(
                'Realtime Register Plugin: Invalid JSON response - ' . $response
            );
        }

        // Use HTTP code as source of truth
        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? 'Unknown API error';
            throw new CE_Exception('Realtime Register Plugin Error: ' . $msg);
        }

        return $decoded;
    }
}

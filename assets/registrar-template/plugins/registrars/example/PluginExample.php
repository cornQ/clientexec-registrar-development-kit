<?php
/**
 * Fail-closed Clientexec registrar module template.
 *
 * Rename:
 *   plugins/registrars/example/ -> plugins/registrars/{registrar}/
 *   PluginExample.php           -> Plugin{Registrar}.php
 *   PluginExample               -> Plugin{Registrar}
 *
 * Keep capabilities disabled until their API operations and error paths are
 * implemented and tested.
 */

require_once 'modules/admin/models/RegistrarPlugin.php';

class PluginExample extends RegistrarPlugin
{
    /** Replace with verified official endpoints while implementing the module. */
    private const PRODUCTION_API_URL = '';
    private const SANDBOX_API_URL = '';

    /**
     * Optional capabilities documented by Clientexec.
     *
     * @var array<string, bool>
     */
    public $features = [
        'nameSuggest' => false,
        'importDomains' => false,
        'importPrices' => false,
    ];

    /**
     * Define administrator configuration and visible actions.
     *
     * Actions are intentionally empty. Add an action only after its do{Action}
     * handler and registrar API operation are complete.
     *
     * @return array
     */
    public function getVariables()
    {
        return [
            'Plugin Name' => [
                'type' => 'hidden',
                'description' => 'Used by Clientexec to identify this plugin',
                'value' => 'Example',
            ],
            'Description' => [
                'type' => 'hidden',
                'description' => 'Description shown to administrators',
                'value' => 'Example registrar integration',
            ],
            'API URL' => [
                'type' => 'hidden',
                'description' => 'Internal registrar API base URL',
                'value' => self::PRODUCTION_API_URL,
            ],
            'API Key' => [
                'type' => 'password',
                'description' => 'Registrar API credential',
                'value' => '',
                'encryptable' => true,
            ],
            'Use testing server' => [
                'type' => 'yesno',
                'description' => 'Use the registrar sandbox',
                'value' => '1',
            ],
            'Actions' => [
                'type' => 'hidden',
                'description' => 'Administrator actions before registration',
                'value' => '',
            ],
            'Registered Actions' => [
                'type' => 'hidden',
                'description' => 'Administrator actions after registration',
                'value' => '',
            ],
            'Registered Actions For Customer' => [
                'type' => 'hidden',
                'description' => 'Customer actions after registration',
                'value' => '',
            ],
        ];
    }

    /**
     * @param array $params
     * @return array
     * @throws CE_Exception
     */
    public function checkDomain($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params Parameters built by buildRegisterParams().
     * @return string Registrar order or domain identifier.
     * @throws CE_Exception
     */
    public function registerDomain($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return array
     * @throws CE_Exception
     */
    public function getContactInformation($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return string
     * @throws CE_Exception
     */
    public function setContactInformation($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return array
     * @throws CE_Exception
     */
    public function getNameServers($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return void
     * @throws CE_Exception
     */
    public function setNameServers($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return array
     * @throws CE_Exception
     */
    public function getGeneralInfo($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return string
     * @throws CE_Exception
     */
    public function setAutorenew($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return bool
     * @throws CE_Exception
     */
    public function getRegistrarLock($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * @param array $params
     * @return void
     * @throws CE_Exception
     */
    public function setRegistrarLock($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * Ask the registrar to deliver the transfer key securely.
     *
     * @param array $params
     * @return void
     * @throws CE_Exception
     */
    public function sendTransferKey($params)
    {
        $this->unsupported(__FUNCTION__);
    }

    /**
     * Replace this helper with a registrar API client after choosing the
     * supported capabilities. Never pass secrets or contact data into the
     * exception message.
     *
     * @param string $operation
     * @return void
     * @throws CE_Exception
     */
    private function unsupported($operation)
    {
        throw new CE_Exception(
            sprintf('%s is not implemented for this registrar module.', $operation)
        );
    }
}

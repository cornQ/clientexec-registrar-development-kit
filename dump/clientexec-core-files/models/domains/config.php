<?php
// index=> array(permission, dependency, customer_allowed, description)

$config = array(
    'mandatory'     => true,
    'recommended'   => true,
    'description'   => 'Domain management module.',
    'navButtonLabel'=> lang('Domains'),
    'dependencies'  => array(),
    'hasSchemaFile' => true,
    'hasInitialData'=> true,
    'hasUninstallSQLScript' => false,
    'hasUninstallPHPScript' => false,
    'order'         => 3,
    'settingTypes'  => array(),
    'hooks'         => array(
        'Menu'                              =>  'Domains_menu'
    ),
    'permissions' => array(
        1    => array('domains_view',                        0, true,  lang('View Domain Overview')),
        2    => array('domains_manage_domains',              1, true, lang('Manage Domain')),
        3    => array('domains_viewcontactinfo',             2, true, lang('View Domain Contact Information')),
        17   => array('domains_updatecontactinfo',           3, true,  lang('Edit Domain Contact Information')),
        4    => array('domains_dnssettings',                 2, true,  lang('View Domain DNS Settings')),
        16   => array('domains_updatedns',                   4, true,  lang('Update DNS Host Information')),
        5    => array('domains_transfer',                    2, true,  lang('Manage Domain Transfer Settings')),
        12   => array('domains_lock',                        5, true,  lang('Toggle Domain Registrar Lock')),
        13   => array('domains_transfer_key',                5, true,  lang('View/Send Transfer Key')),
        6    => array('domains_nameservers',                 2, true,  lang('View Domain Nameservers')),
        //7    => array('domains_registerns',                  6, true,  lang('Register Nameservers With Registrar')),
        8    => array('domains_editns',                      6, true,  lang('Edit Nameservers For A Domain')),
        //9    => array('domains_deletens',                    6, true,  lang('Delete Nameservers From Registrar')),
        //10   => array('domains_updatens',                    6, true,  lang('Update Nameservers With Registrar')),
        //11   => array('domains_autorenew',                   1, true,  lang('Toggle Domain Autorenew')),
        14   => array('domains_fetch',                       1, false,  lang('Fetch Domains From Registrar')),
        15   => array('domains_delete',                      1, false,  lang('Delete Domain Name')),
    ),
    'hreftarget' => '#'
);

// language entries referred in this module, but that need to be loaded always
// (e.g. menu item labels)
$lang = [];

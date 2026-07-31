<?php
/**
 * Read-only Clientexec registrar runtime inventory.
 *
 * Usage:
 *   php inspect-clientexec-runtime.php /absolute/path/to/clientexec
 *
 * Select a PHP binary that loads the ionCube Loader required by the target
 * Clientexec installation.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from the command line.\n");
    exit(1);
}

if (!isset($argv[1]) || trim($argv[1]) === '') {
    fwrite(STDERR, "Usage: php inspect-clientexec-runtime.php /path/to/clientexec\n");
    exit(1);
}

$clientexecRoot = realpath($argv[1]);
if ($clientexecRoot === false || !is_dir($clientexecRoot)) {
    fwrite(STDERR, "Clientexec directory not found.\n");
    exit(1);
}

$requiredFiles = [
    'library/CE/NE_Model.php',
    'library/CE/NE_Plugin.php',
    'modules/admin/models/RegistrarPlugin.php',
    'modules/clients/models/DomainNameGateway.php',
    'modules/domains/models/ICanImportDomains.php',
];

foreach ($requiredFiles as $relativePath) {
    $fullPath = $clientexecRoot . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($fullPath)) {
        fwrite(STDERR, "Required file not found: {$relativePath}\n");
        exit(1);
    }
}

chdir($clientexecRoot);

require_once 'library/CE/NE_Model.php';
require_once 'library/CE/NE_Plugin.php';
require_once 'modules/admin/models/RegistrarPlugin.php';
require_once 'modules/clients/models/DomainNameGateway.php';
require_once 'modules/domains/models/ICanImportDomains.php';

$symbols = [
    'RegistrarPlugin',
    'NE_Plugin',
    'NE_Model',
    'DomainNameGateway',
    'ICanImportDomains',
];

$inventory = [
    'php_version' => PHP_VERSION,
    'symbols' => [],
];

foreach ($symbols as $symbol) {
    if (!class_exists($symbol, false) && !interface_exists($symbol, false)) {
        $inventory['symbols'][$symbol] = [
            'available' => false,
        ];
        continue;
    }

    $reflection = new ReflectionClass($symbol);
    $parent = $reflection->getParentClass();
    $methods = [];

    foreach ($reflection->getMethods() as $method) {
        if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
            continue;
        }

        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => $parameter->hasType()
                    ? (string) $parameter->getType()
                    : null,
                'by_reference' => $parameter->isPassedByReference(),
                'variadic' => $parameter->isVariadic(),
            ];
        }

        $methods[] = [
            'name' => $method->getName(),
            'visibility' => $method->isPublic()
                ? 'public'
                : ($method->isProtected() ? 'protected' : 'private'),
            'abstract' => $method->isAbstract(),
            'final' => $method->isFinal(),
            'static' => $method->isStatic(),
            'parameters' => $parameters,
            'return_type' => $method->hasReturnType()
                ? (string) $method->getReturnType()
                : null,
        ];
    }

    $inventory['symbols'][$symbol] = [
        'available' => true,
        'kind' => $reflection->isInterface() ? 'interface' : 'class',
        'parent' => $parent ? $parent->getName() : null,
        'methods' => $methods,
    ];
}

$json = json_encode(
    $inventory,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    fwrite(STDERR, "Unable to encode the runtime inventory as JSON.\n");
    exit(1);
}

echo $json, PHP_EOL;

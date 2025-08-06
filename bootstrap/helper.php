<?php
/**
 * Argora Foundry
 *
 * A modular PHP boilerplate for building SaaS applications, admin panels, and control systems.
 *
 * @package    App
 * @author     Taras Kondratyuk <help@argora.org>
 * @copyright  Copyright (c) 2025 Argora
 * @license    MIT License
 * @link       https://github.com/getargora/foundry
 */

use Pinga\Auth\Auth;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Guid\Guid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;
use ZxcvbnPhp\Zxcvbn;
use Pinga\Tembo\EppRegistryFactory;
use Utopia\Domains\Domain as uDomain;

/**
 * @return mixed|string|string[]
 */
function routePath() {
    if (isset($_SERVER['REQUEST_URI'])) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $uri = (string) parse_url('http://a' . $_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (stripos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
            return $_SERVER['SCRIPT_NAME'];
        }
        if ($scriptDir !== '/' && stripos($uri, $scriptDir) === 0) {
            return $scriptDir;
        }
    }
    return '';
}

/**
 * @param $key
 * @param null $default
 * @return mixed|null
 */
function config($key, $default=null){
    return \App\Lib\Config::get($key, $default);
}
/**
 * @param $var
 * @return mixed
 */
function envi($var, $default=null)
{
    if(isset($_ENV[$var])){
        return $_ENV[$var];
    }
    return $default;
}

/**
 * Start session
 */
function startSession(){
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * @param $var
 * @return mixed
 */
function session($var){
    if (isset($_SESSION[$var])) {
        return $_SESSION[$var];
    }
}

/**
 * Global PDO connection
 * @return \DI\|mixed|PDO
 * @throws \DI\DependencyException
 * @throws \DI\NotFoundException
 */
function pdo(){
    global $container;
    return $container->get('pdo');
}

/**
 * @return Auth
 */
function auth(){
    $db = pdo();
    $auth = new Auth($db);
    return $auth;
}

/**
 * @param $name
 * @param array $params1
 * @param array $params2
 * @return mixed
 * @throws \DI\DependencyException
 * @throws \DI\NotFoundException
 */
function route($name, $params1 =[], $params2=[]){
    global $container;
    return $container->get('router')->urlFor($name,$params1,$params2);
}

/**
 * @param string $dir
 * @return string
 */
function baseUrl(){
    $root = "";
    $root .= !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $root .= '://' . $_SERVER['HTTP_HOST'];
    return $root;
}

/**
 * @param string|null $name
 * @return string
 */
function url($url=null, $params1 =[], $params2=[]){
    if($url){
        return baseUrl().route($url,$params1,$params2);
    }
    return baseUrl();
}

/**
 * @param $resp
 * @param $page
 * @param array $arr
 * @return mixed
 * @throws \DI\DependencyException
 * @throws \DI\NotFoundException
 */
function view($resp, $page, $arr=[]){
    global $container;
    return $container->get('view')->render($resp, $page, $arr);
}

/**
 * @param $type
 * @param $message
 * @return mixed
 * @throws \DI\DependencyException
 * @throws \DI\NotFoundException
 */
function flash($type, $message){
    global $container;
    return $container->get('flash')->addMessage($type, $message);
}

/**
 * @return \App\Lib\Redirect
 */
function redirect()
{
    return new \App\Lib\Redirect();
}

/**
 * @param $location
 * @return string
 */
function assets($location){
    return url().dirname($_SERVER["REQUEST_URI"]).'/'.$location;
}

/**
 * @param $data
 * @return mixed
 */
function toArray($data){
    return json_decode(json_encode($data), true);
}

function normalize_v4_address($v4) {
    // Remove leading zeros from the first octet
    $v4 = preg_replace('/^0+(\d)/', '$1', $v4);
    
    // Remove leading zeros from successive octets
    $v4 = preg_replace('/\.0+(\d)/', '.$1', $v4);

    return $v4;
}

function normalize_v6_address($v6) {
    // Upper case any alphabetics
    $v6 = strtoupper($v6);
    
    // Remove leading zeros from the first word
    $v6 = preg_replace('/^0+([\dA-F])/', '$1', $v6);
    
    // Remove leading zeros from successive words
    $v6 = preg_replace('/:0+([\dA-F])/', ':$1', $v6);
    
    // Introduce a :: if there isn't one already
    if (strpos($v6, '::') === false) {
        $v6 = preg_replace('/:0:0:/', '::', $v6);
    }

    // Remove initial zero word before a ::
    $v6 = preg_replace('/^0+::/', '::', $v6);
    
    // Remove other zero words before a ::
    $v6 = preg_replace('/(:0)+::/', '::', $v6);

    // Remove zero words following a ::
    $v6 = preg_replace('/:(:0)+/', ':', $v6);

    return $v6;
}

function createUuidFromId($id) {
    // Define a namespace UUID; this should be a UUID that is unique to your application
    $namespace = '123e4567-e89b-12d3-a456-426614174000';

    // Generate a UUIDv5 based on the namespace and a name (in this case, the $id)
    try {
        $uuid5 = Uuid::uuid5($namespace, (string)$id);
        return $uuid5->toString();
    } catch (UnsatisfiedDependencyException $e) {
        // Handle exception
        return null;
    }
}

// Function to get the client IP address
function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function get_client_location() {
    $PublicIP = get_client_ip();
    $json     = file_get_contents("http://ipinfo.io/$PublicIP/geo");
    $json     = json_decode($json, true);
    $country  = $json['country'];

    return $country;
}

function normalizePhoneNumber($number, $defaultRegion = 'US') {
    $phoneUtil = PhoneNumberUtil::getInstance();
    
    // Strip only empty spaces and dashes from the number.
    $number = str_replace([' ', '-'], '', $number);
    
    // Prepend '00' if the number does not start with '+' or '0'.
    if (strpos($number, '+') !== 0 && strpos($number, '0') !== 0) {
        $number = '00' . $number;
    }

    // Convert a leading '+' to '00' for international format compatibility.
    if (strpos($number, '+') === 0) {
        $number = '00' . substr($number, 1);
    }

    // Now, clean the number to ensure it consists only of digits.
    $cleanNumber = preg_replace('/\D/', '', $number);

    try {
        // Parse the clean, digit-only string, which may start with '00' for international format.
        $numberProto = $phoneUtil->parse($cleanNumber, $defaultRegion);

        // Format the number to E.164 to ensure it includes the correct country code.
        $formattedNumberE164 = $phoneUtil->format($numberProto, PhoneNumberFormat::E164);

        // Extract the country code and national number.
        $countryCode = $numberProto->getCountryCode();
        $nationalNumber = $numberProto->getNationalNumber();

        // Reconstruct the number in the desired EPP format: +CountryCode.NationalNumber
        $formattedNumber = '+' . $countryCode . '.' . $nationalNumber;
        return ['success' => $formattedNumber];
        
    } catch (NumberParseException $e) {
        return ['error' => 'Failed to parse and normalize phone number: ' . $e->getMessage()];
    }
}

function validateUniversalEmail($email) {
    // Normalize the email to NFC form to ensure consistency
    $email = \Normalizer::normalize($email, \Normalizer::FORM_C);

    // Remove any control characters
    $email = preg_replace('/[\p{C}]/u', '', $email);

    // Split email into local and domain parts
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return false; // Invalid email format
    }

    list($localPart, $domainPart) = $parts;

    // Convert the domain part to Punycode if it contains non-ASCII characters
    if (preg_match('/[^\x00-\x7F]/', $domainPart)) {
        $punycodeDomain = idn_to_ascii($domainPart, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($punycodeDomain === false) {
            return false; // Invalid domain part, failed conversion
        }
    } else {
        $punycodeDomain = $domainPart;
    }

    // Reconstruct the email with the Punycode domain part (if converted)
    $emailToValidate = $localPart . '@' . $punycodeDomain;

    // Updated regex for both ASCII and IDN email validation
    $emailPattern = '/^[\p{L}\p{N}\p{M}._%+-]+@([a-zA-Z0-9-]+|\bxn--[a-zA-Z0-9-]+)(\.([a-zA-Z0-9-]+|\bxn--[a-zA-Z0-9-]+))+$/u';

    // Validate using regex
    return preg_match($emailPattern, $emailToValidate);
}

function toPunycode($value) {
    // Convert to Punycode if it contains non-ASCII characters
    return preg_match('/[^\x00-\x7F]/', $value) ? idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $value;
}

function toUnicode($value) {
    // Convert from Punycode to UTF-8 if it's a valid IDN format
    return (strpos($value, 'xn--') === 0) ? idn_to_utf8($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $value;
}

function checkPasswordComplexity($password) {
    $zxcvbn = new Zxcvbn();

    // Use configured or default password strength requirement
    $requiredScore = envi('PASSWORD_STRENGTH') ?: 3; // Default to score 3 if ENV is not set

    $score = $zxcvbn->passwordStrength($password)['score'];

    if ($score < $requiredScore) { // Score ranges from 0 (weak) to 4 (strong)
        return false;
    }
    
    return true;
}

function checkPasswordRenewal($lastPasswordUpdateTimestamp) {
    // Check if expiration should be skipped for this user
    $skipList = envi('PASSWORD_EXPIRATION_SKIP_USERS');
    if ($skipList) {
        $skipUsers = array_map('trim', explode(',', $skipList));
        if (in_array($_SESSION['auth_username'], $skipUsers, true)) {
            return null;
        }
    }

    // Use configured or default password expiration days
    $passwordExpiryDays = envi('PASSWORD_EXPIRATION_DAYS') ?: 90; // Default to 90 days

    if (!$lastPasswordUpdateTimestamp) {
        return 'Your password is expired. Please change it.';
    }

    // Convert the timestamp string to a Unix timestamp
    $lastUpdatedUnix = strtotime($lastPasswordUpdateTimestamp);

    if (time() - $lastUpdatedUnix > $passwordExpiryDays * 86400) {
        return 'Your password is expired. Please change it.';
    }

    return null;
}

function hasRequiredRole(int $userRoles, int $requiredRole): bool {
    return ($userRoles & $requiredRole) !== 0;
}

function lacksRoles(int $userRoles, int ...$excludedRoles): bool {
    foreach ($excludedRoles as $role) {
        if (($userRoles & $role) !== 0) {
            return false; // User has at least one of the excluded roles
        }
    }
    return true; // User lacks all specified roles
}

function hasOnlyRole(int $userRoles, int $specificRole): bool {
    return $userRoles === $specificRole;
}

// Returns an array of ranges: each item is ['start' => int, 'end' => int]
function parseCharacterClass(string $class): array {
    $ranges = [];
    $len = mb_strlen($class, 'UTF-8');
    $i = 0;
    while ($i < $len) {
        $currentCode = null;
        // Look for an escape sequence like \x{0621}
        if (mb_substr($class, $i, 1, 'UTF-8') === '\\') {
            if (mb_substr($class, $i, 3, 'UTF-8') === '\\x{') {
                $closePos = mb_strpos($class, '}', $i);
                if ($closePos === false) {
                    throw new \RuntimeException("Unterminated escape sequence in character class.");
                }
                $hex = mb_substr($class, $i + 3, $closePos - ($i + 3), 'UTF-8');
                $currentCode = hexdec($hex);
                $i = $closePos + 1;
            } else {
                // For a simple escaped char (for example, \-)
                $i++;
                if ($i < $len) {
                    $char = mb_substr($class, $i, 1, 'UTF-8');
                    $currentCode = IntlChar::ord($char);
                    $i++;
                } else {
                    break;
                }
            }
        } else {
            $char = mb_substr($class, $i, 1, 'UTF-8');
            $currentCode = IntlChar::ord($char);
            $i++;
        }
        // Check if a dash follows and there is a token after it (forming a range)
        if ($i < $len && mb_substr($class, $i, 1, 'UTF-8') === '-' && ($i + 1) < $len) {
            // skip the dash
            $i++;
            $nextCode = null;
            if (mb_substr($class, $i, 1, 'UTF-8') === '\\') {
                if (mb_substr($class, $i, 3, 'UTF-8') === '\\x{') {
                    $closePos = mb_strpos($class, '}', $i);
                    if ($closePos === false) {
                        throw new \RuntimeException("Unterminated escape sequence in character class.");
                    }
                    $hex = mb_substr($class, $i + 3, $closePos - ($i + 3), 'UTF-8');
                    $nextCode = hexdec($hex);
                    $i = $closePos + 1;
                } else {
                    $i++;
                    if ($i < $len) {
                        $char = mb_substr($class, $i, 1, 'UTF-8');
                        $nextCode = IntlChar::ord($char);
                        $i++;
                    } else {
                        break;
                    }
                }
            } else {
                $char = mb_substr($class, $i, 1, 'UTF-8');
                $nextCode = IntlChar::ord($char);
                $i++;
            }
            $ranges[] = [
                'start' => min($currentCode, $nextCode),
                'end'   => max($currentCode, $nextCode)
            ];
        } else {
            // Not a range; add the single codepoint.
            $ranges[] = ['start' => $currentCode, 'end' => $currentCode];
        }
    }
    return $ranges;
}

// --- Helper: merge overlapping ranges (optional) ---
function mergeRanges(array $ranges): array {
    if (empty($ranges)) {
        return [];
    }
    // sort ranges by start value
    usort($ranges, fn($a, $b) => $a['start'] <=> $b['start']);
    $merged = [];
    $current = $ranges[0];
    foreach ($ranges as $r) {
        if ($r['start'] <= $current['end'] + 1) {
            // Extend the current range if overlapping or adjacent.
            $current['end'] = max($current['end'], $r['end']);
        } else {
            $merged[] = $current;
            $current = $r;
        }
    }
    $merged[] = $current;
    return $merged;
}

// --- Helper: get Unicode name (or fallback) ---
function getUnicodeName(int $codepoint): string {
    $name = IntlChar::charName($codepoint);
    return $name !== '' ? $name : 'UNKNOWN';
}

function isValidHostname($hostname) {
    $hostname = trim($hostname);

    // Convert IDN (Unicode) to ASCII if necessary
    if (mb_detect_encoding($hostname, 'ASCII', true) === false) {
        $hostname = idn_to_ascii($hostname, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        if ($hostname === false) {
            return false; // Invalid IDN conversion
        }
    }

    // Ensure there is at least **one dot** (to prevent single-segment hostnames)
    if (substr_count($hostname, '.') < 1) {
        return false;
    }

    // Regular expression for validating a hostname
    $pattern = '/^((xn--[a-zA-Z0-9-]{1,63}|[a-zA-Z0-9-]{1,63})\.)*([a-zA-Z0-9-]{1,63}|xn--[a-zA-Z0-9-]{2,63})$/';

    // Ensure it matches the hostname pattern
    if (!preg_match($pattern, $hostname)) {
        return false;
    }

    // Ensure no label exceeds 63 characters
    $labels = explode('.', $hostname);
    foreach ($labels as $label) {
        if (strlen($label) > 63) {
            return false;
        }
    }

    // Ensure full hostname is not longer than 255 characters
    if (strlen($hostname) > 255) {
        return false;
    }

    return true;
}

// HMAC Signature generator
function sign($ts, $method, $path, $body, $secret_key) {
    $stringToSign = $ts . strtoupper($method) . $path . $body;
    return hash_hmac('sha256', $stringToSign, $secret_key);
}

function connectToEpp(
    string $registry,
    string $host,
    int $port,
    string $cafile,
    string $local_cert,
    string $local_pk,
    string $passphrase,
    string $clID,
    string $pw
) {
    try {
        $epp = EppRegistryFactory::create($registry);

        $info = array(
            'host' => $host,
            'port' => $port,
            'timeout' => 30,
            'tls' => envi('TLS'),
            'bind' => filter_var(envi('BIND'), FILTER_VALIDATE_BOOLEAN),
            'bindip' => envi('BIND_IP'),
            'verify_peer' => filter_var(envi('VERIFY_PEER'), FILTER_VALIDATE_BOOLEAN),
            'verify_peer_name' => filter_var(envi('VERIFY_PEER_NAME'), FILTER_VALIDATE_BOOLEAN),
            'verify_host' => filter_var(envi('VERIFY_HOST'), FILTER_VALIDATE_BOOLEAN),
            'local_cert' => $local_cert,
            'local_pk' => $local_pk,
            'cafile' => $cafile ?? '',
            'passphrase' => $passphrase ?? '',
            'allow_self_signed' => filter_var(envi('SELF_SIGNED'), FILTER_VALIDATE_BOOLEAN),
        );

        $epp->connect($info);

        $login = $epp->login(array(
            'clID' => $clID,
            'pw' => $pw,
            'prefix' => 'plexepp'
        ));

        if (isset($login['error'])) {
            throw new \Exception('Login Error: ' . $login['error']);
        }

        return $epp;
    } catch (\Pinga\Tembo\Exception\EppException $e) {
        //throw new \Exception("Error: " . $e->getMessage());
    } catch (Throwable $e) {
        //throw new \Exception("Error: " . $e->getMessage());
    }
}

function getDomainConfig($domains, \Pinga\Db\PdoDatabase $db): array
{
    if (!is_array($domains)) {
        $domains = [$domains];
    }

    $results = [];

    foreach ($domains as $domainName) {
        $domainName = strtolower(trim($domainName));

        // Convert IDN domains to ASCII (Punycode)
        $asciiDomain = idn_to_ascii($domainName, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        
        // Skip invalid domains
        if ($asciiDomain === false) {
            continue;
        }

        $domain = new uDomain($asciiDomain);
        $tld = strtolower($domain->getTLD());

        // Get all active domain providers
        $providers = $db->select('SELECT * FROM providers WHERE type = \'domain\' AND status = \'active\'');

        $matchedProvider = null;
        foreach ($providers as $provider) {
            if (empty($provider['pricing'])) {
                continue;
            }

            $pricing = json_decode($provider['pricing'], true);
            if (!is_array($pricing)) {
                continue;
            }

            foreach (array_keys($pricing) as $tldKey) {
                $cleanTld = ltrim($tldKey, '.');
                if ($cleanTld === $tld) {
                    $matchedProvider = $provider;
                    break 2;
                }
            }
        }

        if ($matchedProvider === null) {
            continue;
        }

        // Parse endpoint into host/port
        $api = explode(':', $matchedProvider['api_endpoint']);
        $host = $api[0] ?? null;
        $port = isset($api[1]) ? (int) $api[1] : 700;

        // Parse credentials
        $credentials = json_decode($matchedProvider['credentials'], true);
        if (!$credentials || !isset($credentials['auth']['username'], $credentials['auth']['password'])) {
            continue;
        }

        $results[] = [
            'domain' => $domainName,
            'tld' => $tld,
            'host' => $host,
            'port' => $port,
            'ssl' => $credentials['ssl'] ?? true,
            'cert_file' => $credentials['cert_file'] ?? null,
            'key_file' => $credentials['key_file'] ?? null,
            'cafile' => $credentials['cafile'] ?? null,
            'passphrase' => $credentials['passphrase'] ?? null,
            'username' => $credentials['auth']['username'],
            'password' => $credentials['auth']['password'],
            'client_id' => $credentials['client_id'] ?? null,
            'provider_id' => $matchedProvider['id']
        ];
    }

    return $results;
}

function getRegistryExtensionByTld(string $tld): string
{
    static $tldMap = [
        'fr' => 'FR',
        'pm' => 'FR',
        're' => 'FR',
        'tf' => 'FR',
        'wf' => 'FR',
        'yt' => 'FR',
        'hr' => 'HR',
        'lt' => 'LT',
        'eu' => 'EU',
        'gr' => 'GR',
        'ελ' => 'GR',
        'cz' => 'FRED',
        'ua' => 'UA',
        'se' => 'SE',
        'nu' => 'SE',
        'hk' => 'HK',
        'pl' => 'PL',
        'mx' => 'MX',
        'lv' => 'LV',
        'no' => 'NO',
        'pt' => 'PT',
        'it' => 'IT',
        'fi' => 'FI',
        'com' => 'VRSN',
        'net' => 'VRSN'
    ];

    $tld = strtolower(ltrim($tld, '.'));

    return $tldMap[$tld] ?? 'generic';
}

function provisionService(\Pinga\Db\PdoDatabase $db, int $invoiceId, int $actorId): void {
    try {
        $db->beginTransaction();

        $order = $db->selectRow('SELECT id, user_id, service_type, service_data FROM orders WHERE invoice_id = ? AND status = ?', [$invoiceId, 'pending']);

        $serviceData = json_decode($order['service_data'], true);
        $serviceName = $serviceData['domain'] ?? $serviceData['server'] ?? 'unnamed-service';
        $service_type = strtok($order['service_type'], '.');
        $service_action = strtok('.');
        $years = isset($serviceData['years']) && $serviceData['years'] ? (int)$serviceData['years'] : 1;

        if ($service_type === 'domain') {
            if ($service_action === 'register') {
                $domains = [$serviceName] ?? [];

                $domainData = getDomainConfig($domains, $db);

                if (empty($domainData) || !isset($domainData[0]['tld'])) {
                    throw new \Exception('Error checking domain');
                }

                $registryType = getRegistryExtensionByTld('.'.$domainData[0]['tld']);

                $epp = connectToEpp(
                    $registryType,
                    $domainData[0]['host'],
                    $domainData[0]['port'],
                    $domainData[0]['cafile'] ?? '',
                    $domainData[0]['cert_file'],
                    $domainData[0]['key_file'],
                    $domainData[0]['passphrase'] ?? '',
                    $domainData[0]['username'],
                    $domainData[0]['password']
                );

                if (!$epp) {
                    throw new \Exception('Failed to connect to EPP server.');
                }

                $contactId = 'ct' . substr(md5(uniqid('', true)), 0, 8);

                $registrant = $serviceData['contacts']['registrant'] ?? [];
                $fullName = $registrant['name'] ?? 'John Doe';
                $nameParts = preg_split('/\s+/', trim($fullName));

                if (count($nameParts) === 1) {
                    $firstname = $nameParts[0];
                    $lastname = 'Doe';
                }
                elseif (count($nameParts) === 2) {
                    $firstname = $nameParts[0];
                    $lastname = $nameParts[1];
                }
                else {
                    // Split on the middle word (e.g., "Jean Paul Gotty" → "Jean Paul", "Gotty")
                    $middle = intdiv(count($nameParts), 2);
                    $firstname = implode(' ', array_slice($nameParts, 0, $middle));
                    $lastname = implode(' ', array_slice($nameParts, $middle));
                }

                $contactParams = [
                    'id' => $contactId,
                    'type' => 'int',
                    'firstname' => $firstname ?? 'John',
                    'lastname' => $lastname ?? 'Doe',
                    'companyname' => $registrant['org'] ?? 'Unknown Company',
                    'address1' => $registrant['street1'] ?? 'Unknown Street',
                    'address2' => $registrant['street2'] ?? '',
                    'city' => $registrant['city'] ?? 'Unknown City',
                    'state' => $registrant['sp'] ?? '',
                    'postcode' => $registrant['pc'] ?? '00000',
                    'country' => strtoupper($registrant['cc'] ?? 'XX'),
                    'fullphonenumber' => $registrant['voice'] ?? '+000.0000000',
                    'email' => $registrant['email'] ?? 'test@example.com',
                    'authInfoPw' => $serviceData['authInfo'] ?? 'AutoGenAuth123!',
                ];

                $contactCreate = $epp->contactCreate($contactParams);
                if (isset($contactCreate['error'])) {
                    throw new \Exception('ContactCreate Error: ' . $contactCreate['error']);
                }
                $contactId = $contactCreate['id'];

                $domainParams = [
                    'domainname' => $serviceData['domain'] ?? 'example.invalid',
                    'period' => $serviceData['years'] ?? 1,
                    'nss' => $serviceData['nameservers'] ?? ['ns1.default.com', 'ns2.default.com'],
                    'registrant' => $contactId,
                    'contacts' => [
                        'admin' => $contactId,
                        'tech' => $contactId,
                        'billing' => $contactId,
                    ],
                    'authInfoPw' => $serviceData['authInfo'] ?? 'AutoGenAuth123!',
                ];

                $domainCreate = $epp->domainCreate($domainParams);
                if (isset($domainCreate['error'])) {
                    throw new \Exception('DomainCreate Error: ' . $domainCreate['error']);
                }

                $epp->logout();

                $db->update('orders', ['status' => 'active'], ['id' => $order['id']]);

				$serviceData['authcode'] = $serviceData['authInfo'] ?? 'AutoGenAuth123!';
				$serviceData['status'] = $serviceData['status'] ?? ['ok'];

				foreach (['registrant', 'admin', 'tech', 'billing'] as $role) {
					if (isset($serviceData['contacts'][$role])) {
						$serviceData['contacts'][$role]['registry_id'] = $contactId;
					}
				}

				$order['service_data'] = json_encode($serviceData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $db->insert('services', [
                    'user_id' => $order['user_id'],
                    'provider_id' => $domainData[0]['provider_id'],
                    'order_id' => $order['id'],
                    'type' => $service_type,
                    'status' => 'active',
                    'config' => $order['service_data'],
                    'service_name' => $serviceName,
                    'registered_at' => date('Y-m-d H:i:s.v'),
                    'expires_at' => isset($domainCreate['exDate']) ? date('Y-m-d H:i:s.v', strtotime($domainCreate['exDate'])) : date('Y-m-d H:i:s.v', strtotime("+$years year")),
                    'updated_at' => date('Y-m-d H:i:s.v'),
                    'created_at' => isset($domainCreate['crDate']) ? date('Y-m-d H:i:s.v', strtotime($domainCreate['crDate'])) : date('Y-m-d H:i:s.v'),
                ]);

                $service_id = $db->getLastInsertId();
                $db->commit();
            }
            elseif ($service_action === 'renew') {
                $domains = [$serviceName] ?? [];

                $domainData = getDomainConfig($domains, $db);

                if (empty($domainData) || !isset($domainData[0]['tld'])) {
                    throw new \Exception('Error checking domain for renewal');
                }

                $registryType = getRegistryExtensionByTld('.' . $domainData[0]['tld']);

                $epp = connectToEpp(
                    $registryType,
                    $domainData[0]['host'],
                    $domainData[0]['port'],
                    $domainData[0]['cafile'] ?? '',
                    $domainData[0]['cert_file'],
                    $domainData[0]['key_file'],
                    $domainData[0]['passphrase'] ?? '',
                    $domainData[0]['username'],
                    $domainData[0]['password']
                );

                if (!$epp) {
                    throw new \Exception('Failed to connect to EPP server.');
                }

                $domainRenew = $epp->domainRenew([
                    'domainname' => $serviceData['domain'] ?? throw new \Exception('Domain name missing'),
                    'regperiod' => $serviceData['years'] ?? 1
                ]);

                if (isset($domainRenew['error'])) {
                    throw new \Exception('DomainRenew Error: ' . $domainRenew['error']);
                }

                $epp->logout();

                $db->update('orders', ['status' => 'active'], ['id' => $order['id']]);
                $db->commit();
            }
            else {
                // Handle transfer or others
            }
        }
        elseif ($service_type === 'server') {
            // Handle server actions
        }

    }
    catch (Exception $e) {
        $db->rollBack();
        $db->insert('service_logs', [
            'service_id' => $service_id ?? 0,
            'event' => 'order_activation_failed',
            'actor_type' => 'system',
            'actor_id' => $actorId,
            'details' => $e->getMessage(),
            'created_at' => date('Y-m-d H:i:s.v')
        ]);
        throw $e;
    }
}
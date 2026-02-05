<?php

namespace App\Controllers;

use App\Lib\Mail;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use League\ISO3166\ISO3166;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;
use Brick\Postcode\PostcodeFormatter;

class ContactsController extends Controller
{
    public function listContacts(Request $request, Response $response)
    {      
        return view($response,'admin/contacts/listContacts.twig');
    }

    public function createContact(Request $request, Response $response)
    {
        if ($request->getMethod() === 'POST') {
            // Retrieve POST data
            $data = $request->getParsedBody();
            $db = $this->container->get('db');
            $iso3166 = new ISO3166();
            $countries = $iso3166->all();
            $user_id = $data['user'] ?? null;

            $postalInfoIntName = $data['intName'] ?? null;
            $postalInfoIntOrg = $data['org'] ?? null;
            $postalInfoIntStreet1 = $data['street1'] ?? null;
            $postalInfoIntCity = $data['city'] ?? null;
            $postalInfoIntSp = $data['sp'] ?? null;
            $postalInfoIntPc = $data['pc'] ?? null;
            $postalInfoIntCc = $data['cc'] ?? null;
            
            $postalInfoLocName = $data['locName'] ?? null;
            $postalInfoLocOrg = $data['locOrg'] ?? null;
            $postalInfoLocStreet1 = $data['locStreet1'] ?? null;
            $postalInfoLocCity = $data['locCity'] ?? null;
            $postalInfoLocSp = $data['locSP'] ?? null;
            $postalInfoLocPc = $data['locPC'] ?? null;
            $postalInfoLocCc = $data['locCC'] ?? null;
            
            $voice = $data['voice'] ?? null;
            $email = strtolower($data['email']) ?? null;
            $contactID = generateAuthInfo();

            // Validation for contact ID
            $invalid_identifier = validate_identifier($contactID);
            if ($invalid_identifier) {
                $this->container->get('flash')->addMessage('error', 'Unable to create contact: ' . $invalid_identifier);
                return $response->withHeader('Location', '/contact/create')->withStatus(302);
            }
            
            $contact = $db->select('SELECT * FROM contact WHERE identifier = ?', [$contactID]);
            if ($contact) {
                $this->container->get('flash')->addMessage('error', 'Unable to create contact: Contact ID already exists');
                return $response->withHeader('Location', '/contact/create')->withStatus(302);
            }

            if ($_SESSION["auth_roles"] != 0) {
                $clid = $user_id;
            } else {
                $clid = $_SESSION['auth_user_id'];
            }

            if ($postalInfoIntName) {
                if (!$postalInfoIntName) {
                    $this->container->get('flash')->addMessage('error', 'Unable to create contact: Missing contact name');
                    return $response->withHeader('Location', '/contact/create')->withStatus(302);
                }

                if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntName) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntName)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid contact name');
                    return $response->withHeader('Location', '/contact/create')->withStatus(302);
                }

                if ($postalInfoIntOrg) {
                    if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntOrg) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntOrg)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid contact org');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }

                if ($postalInfoIntStreet1) {
                    if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntStreet1) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntStreet1)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid contact street');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }

                if (preg_match('/(^\-)|(^\.)|(\-\-)|(\.\.)|(\.\-)|(\-\.)|(\-$)|(\.$)/', $postalInfoIntCity) || !preg_match('/^[a-z][a-z\-\.\'\s]{2,}$/i', $postalInfoIntCity)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid contact city');
                    return $response->withHeader('Location', '/contact/create')->withStatus(302);
                }

                if ($postalInfoIntSp) {
                    if (preg_match('/(^\-)|(^\.)|(\-\-)|(\.\.)|(\.\-)|(\-\.)|(\-$)|(\.$)/', $postalInfoIntSp) || !preg_match('/^[A-Z][a-zA-Z\-\.\s]{1,}$/', $postalInfoIntSp)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid contact state/province');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }

                if ($postalInfoIntPc) {
                    if (preg_match('/(^\-)|(\-\-)|(\-$)/', $postalInfoIntPc) || !preg_match('/^[A-Z0-9\-\s]{3,}$/', $postalInfoIntPc)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid contact postal code');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }

            }

            if ($postalInfoLocName) {
                if (!validateLocField($postalInfoLocName, 3)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid loc contact name');
                    return $response->withHeader('Location', '/contact/create')->withStatus(302);
                }

                if ($postalInfoLocOrg) {
                    if (!validateLocField($postalInfoLocOrg, 3)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid loc contact org');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }

                if ($postalInfoLocStreet1) {
                    if (!validateLocField($postalInfoLocStreet1, 3)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid loc contact street');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }

                if (!validateLocField($postalInfoLocCity, 3)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid loc contact city');
                    return $response->withHeader('Location', '/contact/create')->withStatus(302);
                }

                if ($postalInfoLocSp) {
                    if (!validateLocField($postalInfoLocSp, 2)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid loc contact state/province');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }

                if ($postalInfoLocPc) {
                    if (!validateLocField($postalInfoLocPc, 3)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to create contact: Invalid loc contact postal code');
                        return $response->withHeader('Location', '/contact/create')->withStatus(302);
                    }
                }
            }

            $normalizedVoice = normalizePhoneNumber($voice, strtoupper($postalInfoIntCc));
            if (isset($normalizedVoice['error'])) {
                $this->container->get('flash')->addMessage('error', 'Unable to create contact: ' . $normalizedVoice['error']);
                return $response->withHeader('Location', '/contact/create')->withStatus(302);
            }
            $voice = $normalizedVoice['success'];

            // Validate length of $voice
            if (strlen($voice) > 17) {
                $this->container->get('flash')->addMessage('error', 'Unable to create contact: Phone number exceeds 17 characters');
                return $response->withHeader('Location', '/contact/create')->withStatus(302);
            }

            if (!validateUniversalEmail($email)) {
                $this->container->get('flash')->addMessage('error', 'Unable to create contact: Email address failed check');
                return $response->withHeader('Location', '/contact/create')->withStatus(302);
            }
            
            //$disclose_voice = isset($data['disclose_voice']) ? 1 : 0;

            if ($data['nin']) {
                $nin = $data['nin'];
                $nin_type = !empty(trim((string)$postalInfoIntOrg)) ? 'business' : 'personal';

                if (!preg_match('/\d/', $nin)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to create contact: NIN should contain one or more numbers');
                    return $response->withHeader('Location', '/contact/create')->withStatus(302);
                }
            }
            
            // Check if either postalInfoIntName or postalInfoLocName exists
            if (!$postalInfoIntName && !$postalInfoLocName) {
                $this->container->get('flash')->addMessage('error', 'Unable to create contact: At least one of the postal info types (INT or LOC) is required.');
                return $response->withHeader('Location', '/contact/create')->withStatus(302);
            }
            
            try {
                $db->beginTransaction();
                $currentDateTime = new \DateTime();
                $crdate = $currentDateTime->format('Y-m-d H:i:s.v');
                $db->insert(
                    'contact',
                    [
                        'identifier' => $contactID,
                        'voice' => $voice,
                        'email' => $email,
                        'nin' => $nin ?? null,
                        'nin_type' => $nin_type ?? null,
                        'clid' => $clid,
                        'crid' => $clid,
                        'status' => 'ok',
                        'crdate' => $crdate
                    ]
                );
                $contact_id = $db->getLastInsertId();
                
                if ($postalInfoIntName) {
                    $db->insert(
                        'contact_postalInfo',
                        [
                            'contact_id' => $contact_id,
                            'type' => 'int',
                            'name' => $postalInfoIntName ?? null,
                            'org' => $postalInfoIntOrg ?? null,
                            'street1' => $postalInfoIntStreet1 ?? null,
                            'city' => $postalInfoIntCity ?? null,
                            'sp' => $postalInfoIntSp ?? null,
                            'pc' => $postalInfoIntPc ?? null,
                            'cc' => $postalInfoIntCc ?? null
                        ]
                    );
                }

                if ($postalInfoLocName) {
                    $db->insert(
                        'contact_postalInfo',
                        [
                            'contact_id' => $contact_id,
                            'type' => 'loc',
                            'name' => $postalInfoLocName ?? null,
                            'org' => $postalInfoLocOrg ?? null,
                            'street1' => $postalInfoLocStreet1 ?? null,
                            'city' => $postalInfoLocCity ?? null,
                            'sp' => $postalInfoLocSp ?? null,
                            'pc' => $postalInfoLocPc ?? null,
                            'cc' => $postalInfoLocCc ?? null
                        ]
                    );
                }
                
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $this->container->get('flash')->addMessage('error', 'Database failure: ' . $e->getMessage());
                return $response->withHeader('Location', '/contact/create')->withStatus(302);
            }
            
            $crdate = $db->selectValue(
                "SELECT crdate FROM contact WHERE id = ? LIMIT 1",
                [$contact_id]
            );
            
            $this->container->get('flash')->addMessage('success', 'Contact ' . $contactID . ' has been created successfully on ' . $crdate);
            return $response->withHeader('Location', '/contacts')->withStatus(302);
        }

        $iso3166 = new ISO3166();
        $db = $this->container->get('db');
        $countries = $iso3166->all();
        $users = $db->select("SELECT id, username FROM users");
        if ($_SESSION["auth_roles"] != 0) {
            $user = true;
        } else {
            $user = null;
        }
        
        // Default view for GET requests or if POST data is not set
        return view($response,'admin/contacts/createContact.twig', [
            'users' => $users,
            'countries' => $countries,
            'user' => $user,
        ]);
    }
    
    public function viewContact(Request $request, Response $response, $args) 
    {
        if (envi('MINIMUM_DATA') === 'true') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        
        $db = $this->container->get('db');
        // Get the current URI
        $uri = $request->getUri()->getPath();

        if ($args) {
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid contact ID format');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
        
            $contact = $db->selectRow('SELECT id, identifier, voice, email, nin, nin_type, crdate, lastupdate, clid FROM contact WHERE identifier = ?',
            [ $args ]);

            if ($contact) {
                $registrars = $db->selectRow('SELECT id, clid, name FROM registrar WHERE id = ?', [$contact['clid']]);
                $iso3166 = new ISO3166();
                $countries = $iso3166->all();

                // Check if the user is not an admin (assuming role 0 is admin)
                if ($_SESSION["auth_roles"] != 0) {
                    $userRegistrars = $db->select('SELECT registrar_id FROM registrar_users WHERE user_id = ?', [$_SESSION['auth_user_id']]);

                    // Assuming $userRegistrars returns an array of arrays, each containing 'registrar_id'
                    $userRegistrarIds = array_column($userRegistrars, 'registrar_id');

                    // Check if the registrar's ID is in the user's list of registrar IDs
                    if (!in_array($registrars['id'], $userRegistrarIds)) {
                        // Redirect to the contacts view if the user is not authorized for this contact
                        return $response->withHeader('Location', '/contacts')->withStatus(302);
                    }
                }

                $contactLinked = $db->selectRow('SELECT domain_id, type FROM domain_contact_map WHERE contact_id = ?',
                [ $contact['id'] ]);
                $contactPostal = $db->select('SELECT * FROM contact_postalInfo WHERE contact_id = ?',
                [ $contact['id'] ]);
                
                $responseData = [
                    'contact' => $contact,
                    'contactLinked' => $contactLinked,
                    'contactPostal' => $contactPostal,
                    'registrars' => $registrars,
                    'currentUri' => $uri,
                    'countries' => $countries
                ];
                
                $verifyPhone = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPhone'");
                $verifyEmail = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyEmail'");
                $verifyPostal = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPostal'");
        
                if ($verifyPhone == 'on' || $verifyEmail == 'on' || $verifyPostal == 'on') {
                    $contact_validation = $db->selectRow('SELECT validation, validation_stamp, validation_log FROM contact WHERE identifier = ?', [ $args ]);
                    $responseData['contact_valid'] = $contact_validation['validation'];
                    $responseData['validation_enabled'] = true;
                }

                return view($response, 'admin/contacts/viewContact.twig', $responseData);
            } else {
                // Contact does not exist, redirect to the contacts view
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }

        } else {
            // Redirect to the contacts view
            return $response->withHeader('Location', '/contacts')->withStatus(302);
        }

    }

    public function updateContact(Request $request, Response $response, $args) 
    {
        if (envi('MINIMUM_DATA') === 'true') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        
        $db = $this->container->get('db');
        // Get the current URI
        $uri = $request->getUri()->getPath();

        if ($args) {
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid contact ID format');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
            
            $contact = $db->selectRow('SELECT id, identifier, voice, email, nin, nin_type, crdate, clid FROM contact WHERE identifier = ?',
            [ $args ]);

            if ($contact) {
                $registrars = $db->selectRow('SELECT id, clid, name FROM registrar WHERE id = ?', [$contact['clid']]);
                $iso3166 = new ISO3166();
                $countries = $iso3166->all();

                // Check if the user is not an admin (assuming role 0 is admin)
                if ($_SESSION["auth_roles"] != 0) {
                    $userRegistrars = $db->select('SELECT registrar_id FROM registrar_users WHERE user_id = ?', [$_SESSION['auth_user_id']]);

                    // Assuming $userRegistrars returns an array of arrays, each containing 'registrar_id'
                    $userRegistrarIds = array_column($userRegistrars, 'registrar_id');

                    // Check if the registrar's ID is in the user's list of registrar IDs
                    if (!in_array($registrars['id'], $userRegistrarIds)) {
                        // Redirect to the contacts view if the user is not authorized for this contact
                        return $response->withHeader('Location', '/contacts')->withStatus(302);
                    }
                }

                $contactPostal = $db->select('SELECT * FROM contact_postalInfo WHERE contact_id = ?',
                [ $contact['id'] ]);

                $_SESSION['contacts_to_update'] = [$contact['identifier']];

                $responseData = [
                    'contact' => $contact,
                    'contactPostal' => $contactPostal,
                    'registrars' => $registrars,
                    'countries' => $countries,
                    'currentUri' => $uri
                ];
                
                $verifyPhone = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPhone'");
                $verifyEmail = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyEmail'");
                $verifyPostal = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPostal'");
        
                if ($verifyPhone == 'on' || $verifyEmail == 'on' || $verifyPostal == 'on') {
                    $contact_validation = $db->selectRow('SELECT validation, validation_stamp, validation_log FROM contact WHERE identifier = ?', [ $args ]);
                    $responseData['contact_valid'] = $contact_validation['validation'];
                    $responseData['validation_enabled'] = true;
                }

                return view($response, 'admin/contacts/updateContact.twig', $responseData);
            } else {
                // Contact does not exist, redirect to the contacts view
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }

        } else {
            // Redirect to the contacts view
            return $response->withHeader('Location', '/contacts')->withStatus(302);
        }

    }
    
    public function validateContact(Request $request, Response $response, $args) 
    {
        if (envi('MINIMUM_DATA') === 'true') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        
        $db = $this->container->get('db');
        $verifyPhone = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPhone'");
        $verifyEmail = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyEmail'");
        $verifyPostal = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPostal'");
                
        if ($verifyPhone === NULL && $verifyEmail === NULL && $verifyPostal === NULL) {
            // Redirect to the contacts view
            return $response->withHeader('Location', '/contacts')->withStatus(302);
        }

        // Get the current URI
        $uri = $request->getUri()->getPath();

        if ($args) {
            $args = trim($args);

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                $this->container->get('flash')->addMessage('error', 'Invalid contact ID format');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
            
            $contact = $db->selectRow('SELECT id, identifier, voice, email, nin, nin_type, crdate, clid FROM contact WHERE identifier = ?',
            [ $args ]);
            
            if ($_SESSION["auth_roles"] != 0) {
                $clid = $db->selectValue('SELECT registrar_id FROM registrar_users WHERE user_id = ?', [$_SESSION['auth_user_id']]);
                $contact_clid = $contact['clid'];
                if ($contact_clid != $clid) {
                    return $response->withHeader('Location', '/contacts')->withStatus(302);
                }
            } else {
                $clid = $contact['clid'];
            }

            if ($contact) {
                $registrars = $db->selectRow('SELECT id, clid, name FROM registrar WHERE id = ?', [$contact['clid']]);
                $iso3166 = new ISO3166();
                $countries = $iso3166->all();

                $contactPostal = $db->select('SELECT * FROM contact_postalInfo WHERE contact_id = ?',
                [ $contact['id'] ]);

                $_SESSION['contacts_to_validate'] = [$contact['identifier']];

                $responseData = [
                    'contact' => $contact,
                    'contactPostal' => $contactPostal,
                    'registrars' => $registrars,
                    'countries' => $countries,
                    'currentUri' => $uri
                ];
                
                $verifyPhone = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPhone'");
                $verifyEmail = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyEmail'");
                $verifyPostal = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPostal'");
        
                if ($verifyPhone == 'on' || $verifyEmail == 'on' || $verifyPostal == 'on') {
                    $contact_validation = $db->selectRow('SELECT validation, validation_stamp, validation_log FROM contact WHERE identifier = ?', [ $args ]);
                    $responseData['contact_valid'] = $contact_validation['validation'];
                    $responseData['validation_enabled'] = true;
                    $responseData['verifyPhone'] = $verifyPhone;
                    $responseData['verifyEmail'] = $verifyEmail;
                    $responseData['verifyPostal'] = $verifyPostal;
                }
                
                if ($verifyPhone == 'on') {
                    $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
                    try {
                        $numberProto = $phoneUtil->parse($contact['voice'], $contactPostal[0]['cc']);
                        $isValid = $phoneUtil->isValidNumber($numberProto);
                        $responseData['phoneDetails'] = $isValid;
                    } catch (\libphonenumber\NumberParseException $e) {
                        $responseData['phoneDetails'] = $e;
                    }
                }
                
                if ($verifyEmail == 'on') {
                    $validator = new EmailValidator();
                    $multipleValidations = new MultipleValidationWithAnd([
                        new RFCValidation(),
                        new DNSCheckValidation()
                    ]);
                    $isValid = $validator->isValid($contact['email'], $multipleValidations);
                    $responseData['emailDetails'] = $isValid;
                }
                
                if ($verifyPostal == 'on') {
                    $formatter = new PostcodeFormatter();
                    try {
                        $isValid = $formatter->format($contactPostal[0]['cc'], $contactPostal[0]['pc']);
                        $responseData['postalDetails'] = $isValid;
                    } catch (\Brick\Postcode\UnknownCountryException $e) {
                        $responseData['postalDetails'] = null;
                        $responseData['postalDetailsI'] = $e;
                    } catch (\Brick\Postcode\InvalidPostcodeException $e) {
                        $responseData['postalDetails'] = null;
                        $responseData['postalDetailsI'] = $e;
                    }
                    
                }

                return view($response, 'admin/contacts/validateContact.twig', $responseData);
            } else {
                // Contact does not exist, redirect to the contacts view
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }

        } else {
            // Redirect to the contacts view
            return $response->withHeader('Location', '/contacts')->withStatus(302);
        }

    }
    
    public function approveContact(Request $request, Response $response) 
    {
        if (envi('MINIMUM_DATA') === 'true') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        
        if ($request->getMethod() === 'POST') {
            $db = $this->container->get('db');
            $verifyPhone = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPhone'");
            $verifyEmail = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyEmail'");
            $verifyPostal = $db->selectValue("SELECT value FROM settings WHERE name = 'verifyPostal'");
                    
            if ($verifyPhone === NULL && $verifyEmail === NULL && $verifyPostal === NULL) {
                // Redirect to the contacts view
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
        
            // Retrieve POST data
            $data = $request->getParsedBody();
            // Get the current URI
            $uri = $request->getUri()->getPath();
            
            if (!empty($_SESSION['contacts_to_validate'])) {
                $identifier = $_SESSION['contacts_to_validate'][0];
            } else {
                $this->container->get('flash')->addMessage('error', 'No contact specified for validation');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
            
            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $identifier)) {
                $this->container->get('flash')->addMessage('error', 'Invalid contact ID format');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
            
            $contact = $db->selectRow('SELECT id, identifier, voice, email, nin, nin_type, crdate, clid FROM contact WHERE identifier = ?',
            [ $identifier ]);
            
            if ($_SESSION["auth_roles"] != 0) {
                $clid = $db->selectValue('SELECT registrar_id FROM registrar_users WHERE user_id = ?', [$_SESSION['auth_user_id']]);
                $contact_clid = $contact['clid'];
                if ($contact_clid != $clid) {
                    return $response->withHeader('Location', '/contacts')->withStatus(302);
                }
            } else {
                $clid = $contact['clid'];
            }

            if ($contact) {
                if (!empty(envi('SUMSUB_TOKEN')) && !empty(envi('SUMSUB_KEY'))) {
                    $level_name = 'idv-and-phone-verification';

                    // Build request body
                    $bodyArray = [
                        'levelName' => $level_name,
                        'userId' => $identifier,
                        'applicantIdentifiers' => [
                            'email' => $contact['email'],
                            'phone' => $contact['voice']
                        ],
                        'ttlInSecs' => 1800
                    ];

                    $body = json_encode($bodyArray);
                    $path = '/resources/sdkIntegrations/levels/-/websdkLink';
                    $ts = time();
                    $signature = sign($ts, 'POST', $path, $body, envi('SUMSUB_KEY'));

                    // Guzzle client
                    $client = new \GuzzleHttp\Client([
                        'base_uri' => 'https://api.sumsub.com',
                        'headers' => [
                            'X-App-Token' => envi('SUMSUB_TOKEN'),
                            'X-App-Access-Ts' => $ts,
                            'X-App-Access-Sig' => $signature,
                            'Content-Type' => 'application/json',
                        ]
                    ]);

                    // Send request
                    try {
                        $response = $client->post($path, ['body' => $body]);
                        $data = json_decode($response->getBody(), true);
                        $link = $data['url'];

                        $currentDateTime = new \DateTime();
                        $stamp = $currentDateTime->format('Y-m-d H:i:s.v');
                        $email = $db->selectValue('SELECT email FROM users WHERE id = ?', [$_SESSION['auth_user_id']]);
                        $registry = $db->selectValue('SELECT value FROM settings WHERE name = ?', ['company_name']);
                        $message = file_get_contents(__DIR__.'/../../resources/views/mail/validation.html');
                        $placeholders = ['{registry}', '{link}', '{app_name}', '{app_url}', '{identifier}'];
                        $replacements = [$registry, $link, envi('APP_NAME'), envi('APP_URL'), $contact['identifier']];
                        $message = str_replace($placeholders, $replacements, $message);   
                        $mailsubject = '[' . envi('APP_NAME') . '] Contact Verification Required';
                        $from = ['email'=>envi('MAIL_FROM_ADDRESS'), 'name'=>envi('MAIL_FROM_NAME')];
                        $to = ['email'=>$contact['email'], 'name'=>''];
                        // send message
                        Mail::send($mailsubject, $message, $from, $to);

                        $this->container->get('flash')->addMessage('info', 'Contact validation process initiated with SumSub on ' . $stamp);
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    } catch (\GuzzleHttp\Exception\ClientException $e) {
                        $this->container->get('flash')->addMessage('error', 'Contact validation error: ' . $e->getMessage());
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                } else {
                    try {
                        $db->beginTransaction();
                        $currentDateTime = new \DateTime();
                        $stamp = $currentDateTime->format('Y-m-d H:i:s.v');
                        $db->update(
                            'contact',
                            [
                                'validation' => $data['verify'],
                                'validation_stamp' => $stamp,
                                'validation_log' => $clid . '|manual|Validated manually',
                                'upid' => $clid,
                                'lastupdate' => $stamp
                            ],
                            [
                                'identifier' => $identifier
                            ]
                        );                
                        $db->commit();
                    } catch (Exception $e) {
                        $db->rollBack();
                        $this->container->get('flash')->addMessage('error', 'Database failure during update: ' . $e->getMessage());
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }

                    unset($_SESSION['contacts_to_validate']);
                    $this->container->get('flash')->addMessage('success', 'Contact ' . $identifier . ' has been validated successfully on ' . $stamp);
                    return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                }
            } else {
                // Contact does not exist, redirect to the contacts view
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }

        }
        
    }
    
    public function updateContactProcess(Request $request, Response $response)
    {
        if (envi('MINIMUM_DATA') === 'true') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        
        if ($request->getMethod() === 'POST') {
            // Retrieve POST data
            $data = $request->getParsedBody();
            $db = $this->container->get('db');
            $iso3166 = new ISO3166();
            $countries = $iso3166->all();
            if (!empty($_SESSION['contacts_to_update'])) {
                $identifier = $_SESSION['contacts_to_update'][0];
            } else {
                $this->container->get('flash')->addMessage('error', 'No contact specified for update');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }

            if ($_SESSION["auth_roles"] != 0) {
                $clid = $db->selectValue('SELECT registrar_id FROM registrar_users WHERE user_id = ?', [$_SESSION['auth_user_id']]);
                $contact_clid = $db->selectValue('SELECT clid FROM contact WHERE identifier = ?', [$identifier]);
                if ($contact_clid != $clid) {
                    return $response->withHeader('Location', '/contacts')->withStatus(302);
                }
            } else {
                $clid = $db->selectValue('SELECT clid FROM contact WHERE identifier = ?', [$identifier]);
            }
          
            $postalInfoIntName = $data['intName'] ?? null;
            $postalInfoIntOrg = $data['org'] ?? null;
            $postalInfoIntStreet1 = $data['street1'] ?? null;
            $postalInfoIntCity = $data['city'] ?? null;
            $postalInfoIntSp = $data['sp'] ?? null;
            $postalInfoIntPc = $data['pc'] ?? null;
            $postalInfoIntCc = $data['cc'] ?? null;
            
            $postalInfoLocName = $data['locName'] ?? null;
            $postalInfoLocOrg = $data['locOrg'] ?? null;
            $postalInfoLocStreet1 = $data['locStreet1'] ?? null;
            $postalInfoLocCity = $data['locCity'] ?? null;
            $postalInfoLocSp = $data['locSP'] ?? null;
            $postalInfoLocPc = $data['locPC'] ?? null;
            $postalInfoLocCc = $data['locCC'] ?? null;
            
            $voice = $data['voice'] ?? null;
            $email = $data['email'] ?? null;

            if (!$identifier) {
                $this->container->get('flash')->addMessage('error', 'Unable to update contact: Please provide a contact ID');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }

            // Validation for contact ID
            $invalid_identifier = validate_identifier($identifier);
            if ($invalid_identifier) {
                $this->container->get('flash')->addMessage('error', $invalid_identifier);
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
            
            if ($postalInfoIntName) {
                if (!$postalInfoIntName) {
                    $this->container->get('flash')->addMessage('error', 'Unable to update contact: Missing contact name');
                    return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                }

                if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntName) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntName)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact name');
                    return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                }

                if ($postalInfoIntOrg) {
                    if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntOrg) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntOrg)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact org');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if ($postalInfoIntStreet1) {
                    if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntStreet1) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntStreet1)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact street');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if ($postalInfoIntStreet2) {
                    if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntStreet2) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntStreet2)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact street');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if ($postalInfoIntStreet3) {
                    if (preg_match('/(^\-)|(^\,)|(^\.)|(\-\-)|(\,\,)|(\.\.)|(\-$)/', $postalInfoIntStreet3) || !preg_match('/^[a-zA-Z0-9\-\&\,\.\/\s]{5,}$/', $postalInfoIntStreet3)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact street');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if (preg_match('/(^\-)|(^\.)|(\-\-)|(\.\.)|(\.\-)|(\-\.)|(\-$)|(\.$)/', $postalInfoIntCity) || !preg_match('/^[a-z][a-z\-\.\'\s]{2,}$/i', $postalInfoIntCity)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact city');
                    return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                }

                if ($postalInfoIntSp) {
                    if (preg_match('/(^\-)|(^\.)|(\-\-)|(\.\.)|(\.\-)|(\-\.)|(\-$)|(\.$)/', $postalInfoIntSp) || !preg_match('/^[A-Z][a-zA-Z\-\.\s]{1,}$/', $postalInfoIntSp)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact state/province');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if ($postalInfoIntPc) {
                    if (preg_match('/(^\-)|(\-\-)|(\-$)/', $postalInfoIntPc) || !preg_match('/^[A-Z0-9\-\s]{3,}$/', $postalInfoIntPc)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid contact postal code');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

            }
            
            if ($postalInfoLocName) {
                if (!validateLocField($postalInfoLocName, 3)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid loc contact name');
                    return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                }

                if ($postalInfoLocOrg) {
                    if (!validateLocField($postalInfoLocOrg, 3)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid loc contact org');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if ($postalInfoLocStreet1) {
                    if (!validateLocField($postalInfoLocStreet1, 3)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid loc contact street');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if (!validateLocField($postalInfoLocCity, 3)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid loc contact city');
                    return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                }

                if ($postalInfoLocSp) {
                    if (!validateLocField($postalInfoLocSp, 2)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid loc contact state/province');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }

                if ($postalInfoLocPc) {
                    if (!validateLocField($postalInfoLocPc, 3)) {
                        $this->container->get('flash')->addMessage('error', 'Unable to update contact: Invalid loc contact postal code');
                        return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                    }
                }
            }
            
            if ($voice && (!preg_match('/^\+\d{1,3}\.\d{1,14}$/', $voice) || strlen($voice) > 17)) {
                $this->container->get('flash')->addMessage('error', 'Unable to update contact: Voice must be (\+[0-9]{1,3}\.[0-9]{1,14})');
                return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
            }

            if (!validateUniversalEmail($email)) {
                $this->container->get('flash')->addMessage('error', 'Unable to update contact: Email address failed check');
                return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
            }

            if ($data['nin']) {
                $nin = $data['nin'];
                $nin_type = (isset($data['isBusiness']) && $data['isBusiness'] === 'on') ? 'business' : 'personal';

                if (!preg_match('/\d/', $nin)) {
                    $this->container->get('flash')->addMessage('error', 'Unable to update contact: NIN should contain one or more numbers');
                    return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
                }
            }
            
            // Check if either postalInfoIntName or postalInfoLocName exists
            if (!$postalInfoIntName && !$postalInfoLocName) {
                $this->container->get('flash')->addMessage('error', 'Unable to update contact: At least one of the postal info types (INT or LOC) is required.');
                return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
            }
            
            try {
                $db->beginTransaction();
                $currentDateTime = new \DateTime();
                $update = $currentDateTime->format('Y-m-d H:i:s.v');
                $db->update(
                    'contact',
                    [
                        'voice' => $voice,
                        'email' => $email,
                        'nin' => $nin ?? null,
                        'nin_type' => $nin_type ?? null,
                        'upid' => $clid,
                        'lastupdate' => $update
                    ],
                    [
                        'identifier' => $identifier
                    ]
                );
                $contact_id = $db->selectValue(
                    'SELECT id FROM contact WHERE identifier = ?',
                    [$identifier]
                );

                if ($postalInfoIntName) {
                    $db->update(
                        'contact_postalInfo',
                        [
                            'name' => $postalInfoIntName ?? null,
                            'org' => $postalInfoIntOrg ?? null,
                            'street1' => $postalInfoIntStreet1 ?? null,
                            'street2' => $postalInfoIntStreet2 ?? null,
                            'street3' => $postalInfoIntStreet3 ?? null,
                            'city' => $postalInfoIntCity ?? null,
                            'sp' => $postalInfoIntSp ?? null,
                            'pc' => $postalInfoIntPc ?? null,
                            'cc' => $postalInfoIntCc ?? null
                        ],
                        [
                            'contact_id' => $contact_id,
                            'type' => 'int',
                        ]
                    );
                }

                if ($postalInfoLocName) {
                    $does_it_exist = $db->selectValue("SELECT id FROM contact_postalInfo WHERE contact_id = ? AND type = 'loc'", [$contact_id]);
                    
                    if ($does_it_exist) {
                        $db->update(
                            'contact_postalInfo',
                            [
                                'name' => $postalInfoLocName ?? null,
                                'org' => $postalInfoLocOrg ?? null,
                                'street1' => $postalInfoLocStreet1 ?? null,
                                'city' => $postalInfoLocCity ?? null,
                                'sp' => $postalInfoLocSp ?? null,
                                'pc' => $postalInfoLocPc ?? null,
                                'cc' => $postalInfoLocCc ?? null
                            ],
                            [
                                'contact_id' => $contact_id,
                                'type' => 'loc',
                            ]
                        );
                    } else {
                        $db->insert(
                            'contact_postalInfo',
                            [
                                'contact_id' => $contact_id,
                                'type' => 'loc',
                                'name' => $postalInfoLocName ?? null,
                                'org' => $postalInfoLocOrg ?? null,
                                'street1' => $postalInfoLocStreet1 ?? null,
                                'city' => $postalInfoLocCity ?? null,
                                'sp' => $postalInfoLocSp ?? null,
                                'pc' => $postalInfoLocPc ?? null,
                                'cc' => $postalInfoLocCc ?? null
                            ]
                        );
                    }

                }
                
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $this->container->get('flash')->addMessage('error', 'Database failure during update: ' . $e->getMessage());
                return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
            }

            unset($_SESSION['contacts_to_update']);
            $this->container->get('flash')->addMessage('success', 'Contact ' . $identifier . ' has been updated successfully on ' . $update);
            return $response->withHeader('Location', '/contact/update/'.$identifier)->withStatus(302);
        }
    }
    
    public function deleteContact(Request $request, Response $response, $args)
    {
        if (envi('MINIMUM_DATA') === 'true') {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        
       // if ($request->getMethod() === 'POST') {
            $db = $this->container->get('db');
            // Get the current URI
            $uri = $request->getUri()->getPath();
        
            if ($args) {
                $args = trim($args);

                if (!preg_match('/^[a-zA-Z0-9\-]+$/', $args)) {
                    $this->container->get('flash')->addMessage('error', 'Invalid contact ID format');
                    return $response->withHeader('Location', '/contacts')->withStatus(302);
                }
            
                $contact = $db->selectRow('SELECT id, clid FROM contact WHERE identifier = ?',
                [ $args ]);
                $contact_id = $contact['id'];
                $registrar_id_contact = $contact['clid'];
                
                if ($_SESSION["auth_roles"] != 0) {
                    $clid = $db->selectValue('SELECT registrar_id FROM registrar_users WHERE user_id = ?', [$_SESSION['auth_user_id']]);
                    if ($registrar_id_contact != $clid) {
                        return $response->withHeader('Location', '/contacts')->withStatus(302);
                    }
                }
                
                $is_linked_registrant = $db->selectRow('SELECT id FROM domain WHERE registrant = ?',
                [ $contact_id ]);
                
                if ($is_linked_registrant) {
                    $this->container->get('flash')->addMessage('error', 'This contact is associated with a domain as a registrant');
                    return $response->withHeader('Location', '/contacts')->withStatus(302);
                }
                    
                $is_linked_other = $db->selectRow('SELECT contact_id FROM domain_contact_map WHERE contact_id = ?',
                [ $contact_id ]);
                
                if ($is_linked_other) {
                    $this->container->get('flash')->addMessage('error', 'This contact is associated with a domain');
                    return $response->withHeader('Location', '/contacts')->withStatus(302);
                }

                $db->delete(
                    'contact_postalInfo',
                    [
                        'contact_id' => $contact_id
                    ]
                );
                    
                $db->delete(
                    'contact',
                    [
                        'id' => $contact_id
                    ]
                );
                    
                $this->container->get('flash')->addMessage('success', 'Contact ' . $args . ' deleted successfully');
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            } else {
                // Redirect to the contacts view
                return $response->withHeader('Location', '/contacts')->withStatus(302);
            }
        
        //}

    }

    public function webhookSumsub(Request $request, Response $response)
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);
        $db = $this->container->get('db');

        // Validate input
        if (!isset($data['externalUserId']) || !isset($data['type'])) {
            $response->getBody()->write('Missing required fields');
            return $response->withStatus(400);
        }

        $identifier = $data['externalUserId'];
        $type = $data['type'];

        // Only process applicantReviewed type
        if ($type === 'applicantReviewed') {
            $answer = $data['reviewResult']['reviewAnswer'] ?? null;
            switch ($answer) {
                case 'GREEN':
                    $verify = '4'; // verified
                    break;
                case 'RED':
                    $verify = '0'; // failed
                    break;
                default:
                    // Ignore anything else
                    $response->getBody()->write('Ignored (unhandled reviewAnswer)');
                    return $response->withStatus(202);
            }
            $v_log = $data; // store full webhook for audit
            $clid = $data['applicantId'] ?? null;

            $currentDateTime = new \DateTime();
            $stamp = $currentDateTime->format('Y-m-d H:i:s.v');
            
            $db->update(
                'contact',
                [
                    'validation' => $verify,
                    'validation_stamp' => $stamp,
                    'validation_log' => $_SESSION['auth_user_id'] . '|automatic|Validated via SumSub',
                    //'upid' => $clid,
                    'lastupdate' => $stamp
                ],
                [
                    'identifier' => $identifier
                ]
            );

            $response->getBody()->write('OK');
            return $response->withStatus(200);
        }

        $response->getBody()->write('Ignored');
        return $response->withStatus(202);
    }

}
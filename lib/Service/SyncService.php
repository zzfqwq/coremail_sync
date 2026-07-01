<?php

declare(strict_types=1);

namespace OCA\CoremailSync\Service;

use OCA\CoremailSync\AppInfo\Application;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CardDAV\CardDavBackend;
use OCP\IConfig;

class SyncService {
    private const MODE_PRODUCTION = 'production';
    private const MODE_DEMO = 'demo';

    public function __construct(
        private IConfig $config,
        private CardDavBackend $cardDavBackend,
        private CalDavBackend $calDavBackend,
    ) {
    }

    public function shouldSyncUser(string $userId, int $now): bool {
        if ($this->config->getUserValue($userId, Application::APP_ID, 'enabled', '0') !== '1') {
            return false;
        }
        if ($this->config->getUserValue($userId, Application::APP_ID, 'password', '') === '') {
            return false;
        }
        if ($this->effectiveDavBaseUrl($userId) === '') {
            return false;
        }

        $lastRun = (int)$this->config->getUserValue($userId, Application::APP_ID, 'last_run', '0');
        $interval = max(1, (int)$this->config->getUserValue($userId, Application::APP_ID, 'interval_minutes', '30')) * 60;
        return $lastRun === 0 || ($now - $lastRun) >= $interval;
    }

    public function syncUser(string $userId): array {
        $settings = $this->readSettings($userId);
        if ($settings['email'] === '' || $settings['password'] === '') {
            return [
                'contacts' => ['ok' => false, 'count' => 0, 'message' => 'Coremail account or password is missing.'],
                'calendars' => ['ok' => false, 'count' => 0, 'message' => 'Coremail account or password is missing.'],
            ];
        }
        if ($settings['baseUrl'] === '') {
            return [
                'contacts' => ['ok' => false, 'count' => 0, 'message' => 'Coremail DAV URL is missing.'],
                'calendars' => ['ok' => false, 'count' => 0, 'message' => 'Coremail DAV URL is missing.'],
            ];
        }

        $result = [
            'contacts' => $settings['syncContacts']
                ? $this->syncContacts($userId, $settings)
                : ['ok' => true, 'count' => 0, 'message' => 'Contacts sync is disabled.'],
            'calendars' => $settings['syncCalendars']
                ? $this->syncCalendars($userId, $settings)
                : ['ok' => true, 'count' => 0, 'message' => 'Calendars sync is disabled.'],
        ];

        $summary = sprintf(
            'Contacts: %s, Calendars: %s',
            $result['contacts']['message'] ?? 'not run',
            $result['calendars']['message'] ?? 'not run'
        );
        $this->config->setUserValue($userId, Application::APP_ID, 'last_run', (string)time());
        $this->config->setUserValue($userId, Application::APP_ID, 'last_summary', $summary);

        return $result;
    }

    private function readSettings(string $userId): array {
        $mode = $this->securityMode();
        return [
            'email' => trim($this->config->getUserValue($userId, Application::APP_ID, 'email', '')),
            'password' => $this->config->getUserValue($userId, Application::APP_ID, 'password', ''),
            'baseUrl' => rtrim($this->effectiveDavBaseUrl($userId), '/'),
            'mode' => $mode,
            'verifyTls' => $mode !== self::MODE_DEMO,
            'allowHttp' => $mode === self::MODE_DEMO,
            'allowPrivateNetwork' => $mode === self::MODE_DEMO,
            'debugAttempts' => $mode === self::MODE_DEMO,
            'syncContacts' => $this->config->getUserValue($userId, Application::APP_ID, 'sync_contacts', '1') === '1',
            'syncCalendars' => $this->config->getUserValue($userId, Application::APP_ID, 'sync_calendars', '1') === '1',
        ];
    }

    private function effectiveDavBaseUrl(string $userId): string {
        $userValue = trim($this->config->getUserValue($userId, Application::APP_ID, 'dav_base_url', ''));
        if ($userValue !== '') {
            return $userValue;
        }

        return trim($this->config->getAppValue(Application::APP_ID, 'dav_base_url', ''));
    }

    private function securityMode(): string {
        $mode = $this->config->getAppValue(Application::APP_ID, 'security_mode', self::MODE_PRODUCTION);
        return $mode === self::MODE_DEMO ? self::MODE_DEMO : self::MODE_PRODUCTION;
    }

    private function syncContacts(string $userId, array $settings): array {
        try {
            $discovery = $this->discoverAddressBooks($settings);
            if ($discovery['addressBooks'] === []) {
                $result = [
                    'ok' => false,
                    'count' => 0,
                    'message' => 'No Coremail CardDAV address book was discovered.',
                ];
                if ($settings['debugAttempts']) {
                    $result['attempts'] = $discovery['attempts'];
                }
                return $result;
            }

            $principalUri = 'principals/users/' . $userId;
            $addressBookId = $this->ensureNativeAddressBook($principalUri);
            $count = 0;
            $seen = [];

            foreach ($discovery['addressBooks'] as $addressBook) {
                foreach ($this->queryContactObjects($settings, $addressBook) as $item) {
                    $filename = $item['filename'];
                    if (isset($seen[$filename])) {
                        continue;
                    }
                    $seen[$filename] = true;
                    if ($this->cardDavBackend->getCard($addressBookId, $filename) !== false) {
                        $this->cardDavBackend->updateCard($addressBookId, $filename, $item['data']);
                    } else {
                        $this->cardDavBackend->createCard($addressBookId, $filename, $item['data']);
                    }
                    $count++;
                }
            }

            return ['ok' => true, 'count' => $count, 'message' => $count . ' contact(s) synchronized.'];
        } catch (\Throwable $error) {
            return ['ok' => false, 'count' => 0, 'message' => 'Contacts sync failed: ' . $error->getMessage()];
        }
    }

    private function syncCalendars(string $userId, array $settings): array {
        try {
            $discovery = $this->discoverCalendars($settings);
            if ($discovery['calendars'] === []) {
                $result = [
                    'ok' => false,
                    'count' => 0,
                    'message' => 'No Coremail CalDAV calendar was discovered.',
                ];
                if ($settings['debugAttempts']) {
                    $result['attempts'] = $discovery['attempts'];
                }
                return $result;
            }

            $principalUri = 'principals/users/' . $userId;
            $calendarId = $this->ensureNativeCalendar($principalUri);
            $count = 0;
            $seen = [];

            foreach ($discovery['calendars'] as $calendar) {
                foreach ($this->queryCalendarObjects($settings, $calendar) as $item) {
                    $filename = $item['filename'];
                    if (isset($seen[$filename])) {
                        continue;
                    }
                    $seen[$filename] = true;
                    if ($this->calDavBackend->getCalendarObject($calendarId, $filename) !== null) {
                        $this->calDavBackend->updateCalendarObject($calendarId, $filename, $item['data']);
                    } else {
                        $this->calDavBackend->createCalendarObject($calendarId, $filename, $item['data']);
                    }
                    $count++;
                }
            }

            return ['ok' => true, 'count' => $count, 'message' => $count . ' calendar item(s) synchronized.'];
        } catch (\Throwable $error) {
            return ['ok' => false, 'count' => 0, 'message' => 'Calendars sync failed: ' . $error->getMessage()];
        }
    }

    private function ensureNativeAddressBook(string $principalUri): int {
        foreach ($this->cardDavBackend->getAddressBooksForUser($principalUri) as $addressBook) {
            if (($addressBook['uri'] ?? '') === 'coremail') {
                return (int)$addressBook['id'];
            }
        }

        return (int)$this->cardDavBackend->createAddressBook($principalUri, 'coremail', [
            '{DAV:}displayname' => 'Coremail',
            '{urn:ietf:params:xml:ns:carddav}addressbook-description' => 'Coremail synchronized contacts',
        ]);
    }

    private function ensureNativeCalendar(string $principalUri): int {
        foreach ($this->calDavBackend->getCalendarsForUser($principalUri) as $calendar) {
            if (($calendar['uri'] ?? '') === 'coremail') {
                return (int)$calendar['id'];
            }
        }

        return (int)$this->calDavBackend->createCalendar($principalUri, 'coremail', [
            '{DAV:}displayname' => 'Coremail',
            '{http://apple.com/ns/ical/}calendar-color' => '#0072ce',
            'components' => 'VEVENT,VTODO,VJOURNAL',
        ]);
    }

    private function discoverAddressBooks(array $settings): array {
        $encodedEmail = rawurlencode($settings['email']);
        $homes = array_values(array_unique([
            $settings['baseUrl'] . '/users/' . $encodedEmail . '/abs/default/',
            $settings['baseUrl'] . '/users/' . $settings['email'] . '/abs/default/',
            $settings['baseUrl'] . '/users/' . $encodedEmail . '/abs/',
            $settings['baseUrl'] . '/users/' . $settings['email'] . '/abs/',
            $settings['baseUrl'] . '/addressbooks/users/' . $encodedEmail . '/default/',
            $settings['baseUrl'] . '/addressbooks/users/' . $settings['email'] . '/default/',
            $settings['baseUrl'] . '/addressbooks/users/' . $encodedEmail . '/',
            $settings['baseUrl'] . '/addressbooks/users/' . $settings['email'] . '/',
        ]));
        $attempts = [];
        $body = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
  <d:prop>
    <d:displayname/>
    <d:resourcetype/>
  </d:prop>
</d:propfind>
XML;

        foreach ($homes as $home) {
            foreach (['0', '1'] as $depth) {
                $response = $this->davRequest('PROPFIND', $home, $settings, $body, ['Depth: ' . $depth]);
                $attempts[] = ['url' => $home, 'depth' => $depth, 'status' => $response['status']];
                if ($response['status'] < 200 || $response['status'] >= 300) {
                    continue;
                }

                $addressBooks = $this->parseAddressBookList($response['body'], $home);
                if ($addressBooks !== []) {
                    return ['addressBooks' => $addressBooks, 'attempts' => $attempts];
                }
                if (str_ends_with($home, '/default/')) {
                    return ['addressBooks' => [['name' => 'Coremail', 'url' => $home]], 'attempts' => $attempts];
                }
            }
        }

        return ['addressBooks' => [], 'attempts' => $attempts];
    }

    private function discoverCalendars(array $settings): array {
        $encodedEmail = rawurlencode($settings['email']);
        $homes = array_values(array_unique([
            $settings['baseUrl'] . '/users/' . $encodedEmail . '/cas/default/',
            $settings['baseUrl'] . '/users/' . $settings['email'] . '/cas/default/',
            $settings['baseUrl'] . '/users/' . $encodedEmail . '/cas/',
            $settings['baseUrl'] . '/users/' . $settings['email'] . '/cas/',
        ]));
        $attempts = [];
        $body = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:displayname/>
    <d:resourcetype/>
  </d:prop>
</d:propfind>
XML;

        foreach ($homes as $home) {
            foreach (['0', '1'] as $depth) {
                $response = $this->davRequest('PROPFIND', $home, $settings, $body, ['Depth: ' . $depth]);
                $attempts[] = ['url' => $home, 'depth' => $depth, 'status' => $response['status']];
                if ($response['status'] < 200 || $response['status'] >= 300) {
                    continue;
                }

                $calendars = $this->parseCalendarList($response['body'], $home);
                if ($calendars !== []) {
                    return ['calendars' => $calendars, 'attempts' => $attempts];
                }
                if (str_ends_with($home, '/default/')) {
                    return ['calendars' => [['name' => 'Coremail', 'url' => $home]], 'attempts' => $attempts];
                }
            }
        }

        return ['calendars' => [], 'attempts' => $attempts];
    }

    private function queryContactObjects(array $settings, array $addressBook): array {
        $body = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
  <d:prop>
    <d:getetag/>
    <card:address-data/>
  </d:prop>
</card:addressbook-query>
XML;

        $response = $this->davRequest('REPORT', $addressBook['url'], $settings, $body, ['Depth: 1']);
        $items = [];
        if ($response['status'] >= 200 && $response['status'] < 300) {
            $items = $this->parseAddressData($response['body'], $addressBook['url']);
        }
        if ($items === []) {
            $items = $this->fetchContactObjects($settings, $addressBook['url']);
        }

        return array_slice($items, 0, 1000);
    }

    private function queryCalendarObjects(array $settings, array $calendar): array {
        $start = gmdate('Ymd\THis\Z', strtotime('-365 days'));
        $end = gmdate('Ymd\THis\Z', strtotime('+730 days'));
        $body = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:getetag/>
    <c:calendar-data/>
  </d:prop>
  <c:filter>
    <c:comp-filter name="VCALENDAR">
      <c:comp-filter name="VEVENT">
        <c:time-range start="{$start}" end="{$end}"/>
      </c:comp-filter>
    </c:comp-filter>
  </c:filter>
</c:calendar-query>
XML;

        $response = $this->davRequest('REPORT', $calendar['url'], $settings, $body, ['Depth: 1']);
        $items = [];
        if ($response['status'] >= 200 && $response['status'] < 300) {
            foreach ($this->parseCalendarData($response['body']) as $ics) {
                $items[] = [
                    'filename' => $this->calendarFilename($ics),
                    'data' => $this->normalizeDavText($ics),
                ];
            }
        }
        if ($items === []) {
            $items = $this->fetchCalendarObjects($settings, $calendar['url']);
        }

        return array_slice($items, 0, 1000);
    }

    private function fetchContactObjects(array $settings, string $addressBookUrl): array {
        $body = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:">
  <d:prop>
    <d:getetag/>
    <d:getcontenttype/>
  </d:prop>
</d:propfind>
XML;
        $response = $this->davRequest('PROPFIND', $addressBookUrl, $settings, $body, ['Depth: 1']);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return [];
        }

        $items = [];
        foreach (array_slice($this->parseObjectHrefs($response['body'], $addressBookUrl, '.vcf'), 0, 1000) as $href) {
            $object = $this->davRequest('GET', $href, $settings, '', []);
            if ($object['status'] >= 200 && $object['status'] < 300 && trim($object['body']) !== '') {
                $items[] = [
                    'filename' => $this->contactFilename($object['body'], $href),
                    'data' => $this->normalizeDavText($object['body']),
                ];
            }
        }

        return $items;
    }

    private function fetchCalendarObjects(array $settings, string $calendarUrl): array {
        $body = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:">
  <d:prop>
    <d:getetag/>
    <d:getcontenttype/>
  </d:prop>
</d:propfind>
XML;
        $response = $this->davRequest('PROPFIND', $calendarUrl, $settings, $body, ['Depth: 1']);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return [];
        }

        $items = [];
        foreach (array_slice($this->parseObjectHrefs($response['body'], $calendarUrl, '.ics'), 0, 1000) as $href) {
            $object = $this->davRequest('GET', $href, $settings, '', []);
            if ($object['status'] >= 200 && $object['status'] < 300 && trim($object['body']) !== '') {
                $items[] = [
                    'filename' => $this->calendarFilename($object['body']),
                    'data' => $this->normalizeDavText($object['body']),
                ];
            }
        }

        return $items;
    }

    private function davRequest(string $method, string $url, array $settings, string $body, array $extraHeaders = [], string $contentType = 'application/xml; charset=UTF-8'): array {
        $this->assertDavUrlAllowed($url, $settings);
        $headers = array_merge([
            'Authorization: Basic ' . base64_encode($settings['email'] . ':' . $settings['password']),
            'Content-Type: ' . $contentType,
            'Accept: application/xml,text/xml,text/calendar,text/vcard,*/*',
            'Content-Length: ' . strlen($body),
        ], $extraHeaders);

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer' => (bool)$settings['verifyTls'],
                'verify_peer_name' => (bool)$settings['verifyTls'],
                'SNI_enabled' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $status = (int)$matches[1];
                break;
            }
        }

        return [
            'status' => $status,
            'body' => $result === false ? '' : $result,
        ];
    }

    private function assertDavUrlAllowed(string $url, array $settings): void {
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Invalid Coremail DAV URL.');
        }
        if ($scheme === 'http' && !$settings['allowHttp']) {
            throw new \RuntimeException('Production mode requires an HTTPS Coremail DAV URL.');
        }
        if (!$settings['allowPrivateNetwork'] && $this->isPrivateHost($host)) {
            throw new \RuntimeException('Production mode does not allow private or localhost Coremail DAV hosts.');
        }
    }

    private function isPrivateHost(string $host): bool {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = array_map('intval', explode('.', $host));
            return $parts[0] === 10
                || $parts[0] === 127
                || ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31)
                || ($parts[0] === 192 && $parts[1] === 168)
                || ($parts[0] === 169 && $parts[1] === 254);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $host === '::1'
                || str_starts_with($host, 'fc')
                || str_starts_with($host, 'fd')
                || str_starts_with($host, 'fe80:');
        }

        return false;
    }

    private function parseAddressBookList(string $xml, string $homeUrl): array {
        return $this->parseCollectionList($xml, $homeUrl, 'addressbook', 'Coremail');
    }

    private function parseCalendarList(string $xml, string $homeUrl): array {
        return $this->parseCollectionList($xml, $homeUrl, 'calendar', 'Coremail');
    }

    private function parseCollectionList(string $xml, string $homeUrl, string $type, string $fallbackName): array {
        $document = $this->loadXml($xml);
        if ($document === null) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $items = [];
        foreach ($xpath->query('//*[local-name()="response"]') ?: [] as $response) {
            $href = $this->text($xpath, './*[local-name()="href"]', $response);
            if ($href === '') {
                continue;
            }
            $absoluteUrl = $this->absoluteDavUrl($homeUrl, $href);
            $isCollection = $xpath->query('.//*[local-name()="resourcetype"]/*[local-name()="' . $type . '"]', $response)->length > 0;
            if (!$isCollection && rtrim($absoluteUrl, '/') === rtrim($homeUrl, '/') && !str_ends_with($homeUrl, '/default/')) {
                continue;
            }
            if (!$isCollection && !str_ends_with($absoluteUrl, '/')) {
                continue;
            }

            $name = $this->text($xpath, './/*[local-name()="displayname"]', $response);
            $items[] = [
                'name' => $name !== '' ? $name : (basename(trim($href, '/')) ?: $fallbackName),
                'url' => $absoluteUrl,
            ];
        }

        return $this->uniqueByUrl($items);
    }

    private function parseAddressData(string $xml, string $addressBookUrl): array {
        $document = $this->loadXml($xml);
        if ($document === null) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $items = [];
        foreach ($xpath->query('//*[local-name()="response"]') ?: [] as $response) {
            $href = $this->text($xpath, './*[local-name()="href"]', $response);
            $vcard = $this->text($xpath, './/*[local-name()="address-data"]', $response);
            if ($vcard === '') {
                continue;
            }
            $absolute = $href !== '' ? $this->absoluteDavUrl($addressBookUrl, $href) : '';
            $data = html_entity_decode($vcard, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $items[] = [
                'filename' => $this->contactFilename($data, $absolute),
                'data' => $this->normalizeDavText($data),
            ];
        }

        return $items;
    }

    private function parseCalendarData(string $xml): array {
        $document = $this->loadXml($xml);
        if ($document === null) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $items = [];
        foreach ($xpath->query('//*[local-name()="calendar-data"]') ?: [] as $node) {
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                $items[] = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        return $items;
    }

    private function parseObjectHrefs(string $xml, string $baseUrl, string $extension): array {
        $document = $this->loadXml($xml);
        if ($document === null) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $hrefs = [];
        foreach ($xpath->query('//*[local-name()="response"]/*[local-name()="href"]') ?: [] as $node) {
            $href = trim($node->textContent ?? '');
            if ($href === '' || !str_ends_with(strtolower($href), $extension)) {
                continue;
            }
            $hrefs[] = $this->absoluteDavUrl($baseUrl, $href);
        }

        return array_values(array_unique($hrefs));
    }

    private function contactFilename(string $vcard, string $href): string {
        $uid = $this->propertyValue($vcard, 'UID');
        if ($uid === '') {
            $base = basename(parse_url($href, PHP_URL_PATH) ?: '');
            $uid = preg_replace('/\.vcf$/i', '', $base) ?: md5($href . $vcard);
        }

        return $this->safeFilename($uid, 'vcf');
    }

    private function calendarFilename(string $ics): string {
        $uid = $this->propertyValue($ics, 'UID');
        if ($uid === '') {
            $uid = md5($ics);
        }

        return $this->safeFilename($uid, 'ics');
    }

    private function propertyValue(string $davText, string $property): string {
        foreach ($this->unfoldDavText($davText) as $line) {
            if (preg_match('/^' . preg_quote($property, '/') . '(?:;[^:]*)?:(.*)$/i', $line, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return '';
    }

    private function unfoldDavText(string $davText): array {
        $rawLines = preg_split('/\r\n|\n|\r/', $davText) ?: [];
        $lines = [];
        foreach ($rawLines as $line) {
            if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
                $last = count($lines) - 1;
                if ($last >= 0) {
                    $lines[$last] .= substr($line, 1);
                }
                continue;
            }
            $lines[] = $line;
        }

        return $lines;
    }

    private function normalizeDavText(string $value): string {
        $lines = preg_split('/\r\n|\n|\r/', trim($value)) ?: [];
        return implode("\r\n", $lines) . "\r\n";
    }

    private function safeFilename(string $uid, string $extension): string {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $uid) ?: md5($uid);
        return trim($safe, '-.') . '.' . $extension;
    }

    private function absoluteDavUrl(string $baseUrl, string $href): string {
        if (preg_match('/^https?:\/\//i', $href) === 1) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin . '/' . ltrim($href, '/');
    }

    private function loadXml(string $xml): ?\DOMDocument {
        if (trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $ok = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $document : null;
    }

    private function text(\DOMXPath $xpath, string $query, \DOMNode $context): string {
        $nodes = $xpath->query($query, $context);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return trim($nodes->item(0)->textContent ?? '');
    }

    private function uniqueByUrl(array $items): array {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = rtrim((string)$item['url'], '/');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }
}

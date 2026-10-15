<?php
declare(strict_types=1);

namespace App\Protocollo;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;

/**
 * Simple client for Comune di Brescia SICRAweb SOAP – InserisciProtocolloEAnagraficheString
 *
 * Usage (inside a controller):
 *
 *   $protocollo = new \App\Protocollo\Protocollo();
 *   $result = $protocollo->inserisciProtocolloEAnagraficheString($protocolloXml, $allegatiXml);
 *   // $result = [
 *   //   'idDocumento' => '6960398',
 *   //   'annoProtocollo' => 2025,
 *   //   'numeroProtocollo' => 274459,
 *   //   'dataProtocollo' => '2025-12-22T16:24:14.221+0100',
 *   //   'messaggio' => 'Inserimento Protocollo eseguito con successo',
 *   //   'errore' => null,
 *   //   'allegati' => [ ['serial' => '30297702', 'idBase' => '30297702', 'versione' => '0'] ]
 *   // ]
 */
class Protocollo
{
    /**
     * @var string
     */
    protected $baseEndpoint;

    /**
     * @var string
     */
    protected $userService;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $NumeroPratica;

    /**
     * @var string
     */
    protected $soapNamespace = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * @var string
     */
    protected $svcNamespace = 'http://tempuri.org/';

    public function __construct(?string $baseEndpoint = null, ?string $userService = null, ?string $password = null)
    {
        $this->baseEndpoint = $baseEndpoint ?? (string)Configure::read('Protocollo.baseEndpoint');
        $this->userService = $userService ?? (string)Configure::read('Protocollo.userService');
        $this->password = $password ?? (string)Configure::read('Protocollo.password');
        $this->NumeroPratica = (string)Configure::read('Protocollo.NumeroPratica', '6.5.1.5');
    }

    /**
     * Calls InserisciProtocolloEAnagraficheString SOAP action and returns parsed values.
     *
     * @param string $protocolloXml XML content for <xmlProtocollo> (plain XML string)
     * @param string|null $allegatiXml XML content for <xmlAllegati> (plain XML string). Optional.
     * @param string $xmlAnagrafiche Optional XML for <xmlAnagrafiche>, empty by default.
     * @return array Parsed result array as described in the class header.
     */
    public function inserisciProtocolloEAnagraficheString(string $protocolloXml, ?string $allegatiXml = null, string $xmlAnagrafiche = ''): array
    {
        $endpoint = $this->buildEndpoint();

        $body = $this->buildSoapEnvelope(
            $this->buildInserisciProtocolloBody($protocolloXml, $allegatiXml, $xmlAnagrafiche)
        );
        $body = $this->buildSoapEnvelope($protocolloXml);
        Log::write('debug', "Protocollo SOAP request to {$endpoint}: {$body}");

        $client = new Client([
            'timeout' => 90,
            // Many PA endpoints use custom TLS; switch off verification if configured
            'sslVerifyPeer' => (bool)Configure::read('Protocollo.sslVerifyPeer', false),
            'sslVerifyPeerName' => (bool)Configure::read('Protocollo.sslVerifyPeerName', false),
        ]);

        $auth = base64_encode($this->userService . ':' . $this->password);

        $response = $client->post($endpoint, $body, [
            'type' => 'xml',
            'headers' => [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $this->svcNamespace . 'InserisciProtocolloEAnagraficheString',
                'Authorization' => 'Basic ' . $auth,
            ],
        ]);

        if (!$response->isOk()) {
            $status = $response->getStatusCode();
            $err = $response->getStringBody();
            Log::write('error', "Protocollo SOAP HTTP error: {$status} - {$err}");
            throw new \RuntimeException('Protocollo SOAP HTTP error: ' . $status);
        }

        $soapXml = (string)$response->getStringBody();
        Log::write('debug', "Protocollo SOAP response: {$soapXml}");

        return $this->parseInserisciProtocolloResponse($soapXml);
    }

    /**
     * Builds the service endpoint including CID query param.
     */
    protected function buildEndpoint(): string
    {
        // Example: https://.../ProtocolloSoap?CID=<user>@sicraweb
        $cid = $this->userService . '@sicraweb';
        $sep = strpos($this->baseEndpoint, '?') === false ? '?' : '&';

        return $this->baseEndpoint . $sep . 'CID=' . rawurlencode($cid);
    }

    /**
     * Wraps a method body into a SOAP envelope.
     */
    protected function buildSoapEnvelope(string $methodBody): string
    {
        $soapBody = <<<SOAP
		<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/">
		<soapenv:Header/>
		<soapenv:Body>
			<tem:InserisciProtocolloEAnagraficheString>
			<tem:ProtocolloInStr><![CDATA[{$methodBody}]]></tem:ProtocolloInStr>
			<tem:CodiceAmministrazione></tem:CodiceAmministrazione>
			<tem:CodiceAOO></tem:CodiceAOO>
			</tem:InserisciProtocolloEAnagraficheString>
		</soapenv:Body>
		</soapenv:Envelope>
		SOAP;

        return $soapBody;
    }

    /**
     * Builds the InserisciProtocolloEAnagraficheString method body.
     */
    protected function buildInserisciProtocolloBody(string $protocolloXml, ?string $allegatiXml, string $xmlAnagrafiche): string
    {
        // Don't escape - già fatto in buildSoapEnvelope con CDATA
        $body = '<InserisciProtocolloEAnagraficheString xmlns="' . $this->svcNamespace . '">'
            . '<xmlProtocollo>' . $protocolloXml . '</xmlProtocollo>'
            . '<xmlAnagrafiche>' . $xmlAnagrafiche . '</xmlAnagrafiche>';
        if ($allegatiXml !== null) {
            $body .= '<xmlAllegati>' . $allegatiXml . '</xmlAllegati>';
        }
        $body .= '</InserisciProtocolloEAnagraficheString>';

        // Free input strings immediately
        unset($protocolloXml, $allegatiXml, $xmlAnagrafiche);

        return $body;
    }

    // build protocolloXml

    public function buildProtocolloXml(string $oggetto, $user, $allegatiXml): string
    {
        $data = date('d/m/Y H:i:s', strtotime('+1 hour'));
        $cf = $user->get('cf');
        $first_name = $user->get('first_name');
        $last_name = $user->get('last_name');
        $email = $user->get('email');
        $NumeroPratica = $this->NumeroPratica;

        $xml = <<<XML
        <ProtoIn>
            <Data>{$data}</Data>
            <Classifica>6.7</Classifica>
            <TipoDocumento>IST_POR</TipoDocumento>
            <Oggetto>$oggetto</Oggetto>
            <Origine>A</Origine>
            <MezzoInvio>ALTRO_PORTALE</MezzoInvio>
            <MittenteInterno>ANA</MittenteInterno>
            <MittentiDestinatari>
                <MittenteDestinatario>
                    <CodiceFiscale>$cf</CodiceFiscale>
                    <CognomeNome>$last_name</CognomeNome>
                    <Nome>$first_name</Nome>
                    <Indirizzo></Indirizzo>
                    <Localita></Localita>
                    <CodiceComuneResidenza></CodiceComuneResidenza>
                    <DataNascita></DataNascita>
                    <CodiceComuneNascita></CodiceComuneNascita>
                    <Nazionalita></Nazionalita>
                    <DataInvio_DataProt></DataInvio_DataProt>
                    <Spese_NProt></Spese_NProt>
                    <Mezzo>PEC</Mezzo>
                    <DataRicevimento></DataRicevimento>
                    <TipoSogg></TipoSogg>
                    <TipoPersona>FI</TipoPersona>
                    <Recapiti>
                        <Recapito>
                            <TipoRecapito>EMAIL</TipoRecapito>
                            <ValoreRecapito>$email</ValoreRecapito>
                        </Recapito>
                    </Recapiti>
                </MittenteDestinatario>
            </MittentiDestinatari>
            <AggiornaAnagrafiche>N</AggiornaAnagrafiche>
            <InCaricoA>MOBIL</InCaricoA>
            <AnnoPratica>2021</AnnoPratica>
            <NumeroPratica>{$NumeroPratica}</NumeroPratica>
            <DataDocumento></DataDocumento>
            <NumeroDocumento></NumeroDocumento>
            <NumeroAllegati></NumeroAllegati>
            <DataEvid></DataEvid>
            <OggettoStandard></OggettoStandard>
            <Utente></Utente>
            <Ruolo></Ruolo>
            <CodiceAmministrazione></CodiceAmministrazione>
            <Allegati>
        {$allegatiXml}
            </Allegati>
            <LivelloRiservatezza></LivelloRiservatezza>
            <DataFineRiservatezza></DataFineRiservatezza>
        </ProtoIn>
        XML;

        // Free allegatiXml from memory immediately
        unset($allegatiXml);

        return $xml;
    }

    // build allegatiXml

    public function buildAllegatiXml(string $base64, string $comento, string $nomeAllegato): string
    {
        // Build XML with base64 content (caller should unset $base64 after this)
        $xml = <<<XML
        <Allegato>
            <TipoFile>pdf</TipoFile>
            <ContentType/>
            <Image xmlns:dt="urn:schemas-microsoft-com:datatypes" dt:dt="bin.base64">{$base64}</Image>
            <Commento>{$comento}</Commento>
            <IdAllegatoPrincipale>1</IdAllegatoPrincipale>
            <NomeAllegato>{$nomeAllegato}</NomeAllegato>
            <Schema/>
        </Allegato>
XML;

        return $xml;
    }

    /**
     * Parses the SOAP response and returns a normalized array.
     */
    protected function parseInserisciProtocolloResponse(string $soapXml): array
    {
        // Load SOAP envelope
        $sx = @simplexml_load_string($soapXml);
        if ($sx === false) {
            throw new \RuntimeException('Invalid SOAP XML response');
        }
        $sx->registerXPathNamespace('soapenv', $this->soapNamespace);
        $sx->registerXPathNamespace('m', $this->svcNamespace);

        // Find the Result node which contains escaped XML
        $resultNodes = $sx->xpath('//soapenv:Body/m:InserisciProtocolloEAnagraficheStringResponse/m:InserisciProtocolloEAnagraficheStringResult');
        if (!$resultNodes || !isset($resultNodes[0])) {
            // Some services omit prefixes on inner nodes – try without ns
            $resultNodes = $sx->xpath('//soapenv:Body//*[local-name()="InserisciProtocolloEAnagraficheStringResult"]');
        }
        if (!$resultNodes || !isset($resultNodes[0])) {
            throw new \RuntimeException('SOAP result node not found');
        }

        $escaped = (string)$resultNodes[0];
        $innerXml = html_entity_decode($escaped, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $px = @simplexml_load_string($innerXml);
        if ($px === false) {
            throw new \RuntimeException('Invalid inner XML in SOAP result');
        }

        // Normalize to expected structure
        $out = [
            'idDocumento' => self::str($px->IdDocumento ?? null),
            'annoProtocollo' => self::int($px->AnnoProtocollo ?? null),
            'numeroProtocollo' => self::int($px->NumeroProtocollo ?? null),
            'dataProtocollo' => self::str($px->DataProtocollo ?? null),
            'messaggio' => self::str($px->Messaggio ?? null),
            'errore' => self::nullableStr($px->Errore ?? null),
            'allegati' => [],
            'raw' => [
                'soap' => $soapXml,
                'inner' => $innerXml,
            ],
        ];

        if (isset($px->Allegati->Allegato)) {
            foreach ($px->Allegati->Allegato as $al) {
                $out['allegati'][] = [
                    'serial' => self::str($al->Serial ?? null),
                    'idBase' => self::str($al->IDBase ?? null),
                    'versione' => self::str($al->Versione ?? null),
                ];
            }
        }

        return $out;
    }

    protected static function str($v): string
    {
        return $v === null ? '' : trim((string)$v);
    }

    protected static function nullableStr($v): ?string
    {
        $s = self::str($v);

        return $s === '' ? null : $s;
    }

    protected static function int($v): ?int
    {
        $s = self::str($v);

        return $s === '' ? null : (int)$s;
    }

    protected function xmlEscape(string $xml): string
    {
        return htmlspecialchars($xml, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}

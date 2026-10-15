<?php
/**
 * Modulo di adesione EMMA RE-R — PDF di riepilogo da firmare digitalmente (specifica §6).
 *
 * Reso in HTML e convertito con WeasyPrint da App\Service\AdhesionDocumentService.
 *
 * ⚠️ Tutti i valori passano da h(): il contenuto arriva da un form pubblico e anonimo e
 * non deve poter iniettare markup nel documento.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AdhesionRequest $request
 * @var array $data
 * @var \App\Model\Entity\AdhesionRequestPerson[] $people
 * @var \Cake\I18n\FrozenTime $generatedAt
 */

$value = function ($key, $fallback = '—') use ($data) {
    $raw = $data[$key] ?? null;
    if ($raw === null || $raw === '') {
        return $fallback;
    }
    if (is_bool($raw)) {
        return $raw ? 'Sì' : 'No';
    }

    return (string)$raw;
};

$organizationType = ($data['organization_type'] ?? $request->organization_type) === 'scuola'
    ? 'Scuola'
    : 'Azienda';
// Branch principale scuola/azienda (Allegato 4 vs Allegato 3): vedi il branch map in
// docs/dev-notes/adesione-flow.md per l'elenco di cosa è condiviso e cosa è specifico.
$isScuola = $organizationType === 'Scuola';

// Riga unica "Cognome e nome" (round 4): sostituisce le due righe separate Cognome/Nome
// in TUTTI i punti del template dove compare un nome di persona (legale rappresentante
// §3, MM aziendale §4, MM di distretto §4b) — stesso pattern difensivo sui dati parziali
// già usato per $resolveRowDisplay/"Iscrizione al Registro delle Imprese di" sopra.
$fullName = function ($lastNameKey, $firstNameKey) use ($data) {
    $lastName = trim((string)($data[$lastNameKey] ?? ''));
    $firstName = trim((string)($data[$firstNameKey] ?? ''));
    if ($lastName === '' && $firstName === '') {
        return '—';
    }

    return trim($lastName . ' ' . $firstName);
};

// Ordine ufficiale Allegato 3 (§2). Gli extra di piattaforma (Comune/Provincia di
// riferimento, Email, Telefono, Cellulare) vivono in $extraRows, resi DOPO questo
// blocco — vedi il ciclo più sotto.
$rows = [
    // "Denominazione" (round 4): era "Denominazione / Ragione sociale", il template
    // ufficiale usa solo "Denominazione".
    'Denominazione' => 'company_name',
    'Forma giuridica' => 'legal_form',
    // Unica riga "Sede legale (via, n., comune, CAP)": prima erano due righe separate
    // (Indirizzo sede legale + Comune sede legale), qui accorpate per aderire alla
    // dicitura ufficiale del template. Il valore composto vive in $legalAddress sotto.
    'Sede legale (via, n., comune, CAP)' => null,
    // Lowercase "Codice fiscale" (round 5): il template ufficiale usa la minuscola in
    // tutte le 4 occorrenze di questa etichetta (qui, §3, §4, §4b) — non tocca le altre
    // 2 occorrenze "Codice Fiscale" del file (intestazioni delle tabelle "Elenco delle
    // persone"/"Elenco delle altre aziende del distretto"), che sono intestazioni di
    // colonna di elenchi non previsti dall'Allegato 3, non l'etichetta del campo ufficiale.
    'Codice fiscale' => 'fiscal_code',
    'Partita IVA' => 'vat_number',
    'Codice ATECO' => 'ateco',
    // Riga unica "Iscrizione al Registro delle Imprese di" (round 4): prima erano due
    // righe separate (territorio, numero), qui accorpate in "{territorio}, n. {numero}"
    // — vedi $resolveRowDisplay sotto per la gestione dei dati parziali.
    'Iscrizione al Registro delle Imprese di' => null,
    // Wording ufficiale plurale: il modello dati resta un singolo valore libero.
    'Sede/i operativa/e oggetto del PSCL' => 'operational_address',
    // Wording ufficiale: nel modello dati esiste solo il totale aziendale, non la
    // scomposizione per unità locale — semplificazione nota e accettata, vedi il
    // commento su $displayAddress sotto.
    'N. dipendenti complessivo (e per unità locale qualificata)' => 'num_employees',
    // "PEC aziendale" (round 4): distingue da altre eventuali PEC nel documento (nessuna
    // oggi, ma l'etichetta nuda "PEC" era ambigua).
    'PEC aziendale' => 'pec',
];

// Extra di piattaforma, non previsti dall'Allegato 3: resi dopo $rows, con un lead-in
// dedicato (vedi il markup più sotto), non intrecciati ai campi ufficiali.
$extraRows = [
    'Comune di riferimento' => 'reference_city',
    'Provincia di riferimento' => 'reference_province',
    'Email' => 'email',
    'Telefono' => 'phone',
    'Cellulare' => 'mobile',
];

$address = trim((string)($data['legal_address_street'] ?? '') . ' ' . (string)($data['legal_address_number'] ?? ''));

// "{via, n.}, {comune} {CAP}" — stesso trim-join pattern di $address sopra, esteso col
// comune e il CAP (nuovo campo obbligatorio `legal_address_cap`).
$legalAddressCity = trim((string)($data['legal_address_city'] ?? ''));
$legalAddressCap = trim((string)($data['legal_address_cap'] ?? ''));
$legalAddress = $address;
if ($legalAddressCity !== '') {
    $legalAddress = $legalAddress !== '' ? $legalAddress . ', ' . $legalAddressCity : $legalAddressCity;
}
if ($legalAddressCap !== '') {
    $legalAddress = trim($legalAddress . ' ' . $legalAddressCap);
}

// Stessa risoluzione usata sia per la riga "Sede/i operativa/e oggetto del PSCL" sia
// per il numero dipendenti "per unità locale" (§3a della richiesta di questo round):
// hoisted qui, PRIMA del ciclo $rows, così le due righe leggono lo stesso valore
// risolto invece di ricalcolarlo (e potenzialmente disallinearlo) in due punti diversi.
$operationalAddress = trim((string)($data['operational_address'] ?? ''));
$displayAddress = $operationalAddress !== '' ? $operationalAddress : ($address !== '' ? $address : '—');

// Risolve il valore da mostrare per una riga della tabella §2, centralizzando i pochi
// casi speciali (indirizzo composto, numero dipendenti "per unità locale") così sia
// $rows sia $extraRows leggono la stessa logica senza duplicarla.
$resolveRowDisplay = function ($key, $label) use ($value, $legalAddress, $displayAddress, $data) {
    if ($label === 'Sede legale (via, n., comune, CAP)') {
        return $legalAddress !== '' ? $legalAddress : '—';
    }
    // Riga unica "Iscrizione al Registro delle Imprese di" (round 4): combina
    // registry_territory/registry_number in "{territorio}, n. {numero}", gestendo i dati
    // parziali senza virgola/", n." spuria — entrambi i campi dati restano indipendenti,
    // solo questa riga del PDF li accorpa.
    if ($label === 'Iscrizione al Registro delle Imprese di') {
        $registryTerritory = trim((string)($data['registry_territory'] ?? ''));
        $registryNumber = trim((string)($data['registry_number'] ?? ''));
        if ($registryTerritory === '' && $registryNumber === '') {
            return '—';
        }
        if ($registryTerritory !== '' && $registryNumber !== '') {
            return $registryTerritory . ', n. ' . $registryNumber;
        }

        return $registryTerritory !== '' ? $registryTerritory : 'n. ' . $registryNumber;
    }
    if ($key === 'operational_address') {
        return $displayAddress;
    }
    if ($key === 'num_employees') {
        $numEmployees = $value('num_employees');
        if ($numEmployees === '—') {
            return '—';
        }

        return sprintf('%s (complessivo) — %s (unità locale %s)', $numEmployees, $numEmployees, $displayAddress);
    }

    return $value($key);
};

// Lowercase (round 4): il template ufficiale usa 'dipendente'/'consulente'/'altro' in
// minuscolo, non capitalizzati.
$mmRelationshipLabels = [
    'dipendente' => 'dipendente',
    'consulente' => 'consulente',
    'altro' => 'altro',
];
// Testo verbatim del template ufficiale "Allegato 3": la base giuridica corretta per le
// opzioni 2/3 è l'art. 6, par. 1 del GDPR (lett. f)/e)), NON le Linee Guida regionali —
// citazione errata corretta in questa revisione. L'opzione 1 non riporta più la soglia
// dipendenti/comuni: quel testo era un'aggiunta non presente nel template ufficiale e
// poteva contraddire i dati dichiarati nel form.
$psclObligationOptions = [
    'obbligata_229' => 'azienda/ente tenuta all\'obbligo di adozione del PSCL e di nomina del Mobility '
        . 'Manager aziendale ai sensi dell\'art. 229 del D.L. 19 maggio 2020, n. 34, convertito dalla '
        . 'L. 17 luglio 2020, n. 77, e del D.M. 12 maggio 2021, n. 179 — base giuridica del trattamento: '
        . 'art. 6, par. 1, lett. c) del GDPR (necessità di adempiere ad un obbligo legale al quale il '
        . 'titolare è soggetto).',
    'volontaria_azienda' => 'azienda aderente in via volontaria, non rientrando fra i soggetti tenuti '
        . 'dall\'art. 229 D.L. 34/2020 — base giuridica del trattamento: art. 6, par. 1, lett. f) del GDPR '
        . '(legittimo interesse del titolare, consistente nella razionalizzazione degli spostamenti '
        . 'casa-lavoro del personale, nella riduzione dell\'impatto ambientale dell\'attività aziendale e '
        . 'nel miglioramento delle condizioni di mobilità dei dipendenti).',
    'volontaria_ente' => 'ente aderente in via volontaria, non rientrando fra i soggetti tenuti dall\'art. '
        . '229 D.L. 34/2020 — base giuridica del trattamento: art. 6, par. 1, lett. e) del GDPR, ovvero '
        . 'l\'esecuzione di un compito di interesse pubblico sotteso alla razionalizzazione degli '
        . 'spostamenti casa-lavoro del personale, alla riduzione dell\'impatto ambientale dell\'attività '
        . 'aziendale e al miglioramento delle condizioni di mobilità dei dipendenti; il titolare effettua '
        . 'e conserva la valutazione del bilanciamento (LIA) e garantisce ai dipendenti l\'esercizio del '
        . 'diritto di opposizione ex art. 21 del GDPR.',
];

// Tipologia (Allegato 4, istituto scolastico): quattro opzioni mutuamente esclusive,
// stesso pattern ☒/☐ di $mmRelationshipLabels sopra.
$schoolTypeLabels = [
    'infanzia' => 'infanzia',
    'primaria' => 'primaria',
    'secondaria_primo_grado' => 'secondaria I grado',
    'secondaria_secondo_grado' => 'secondaria II grado',
];
// Natura giuridica (Allegato 4): tre opzioni mutuamente esclusive, stesso pattern.
$schoolLegalNatureLabels = [
    'statale' => 'Statale',
    'paritaria' => 'Paritaria',
    'privata' => 'Privata',
];
// Testo verbatim del template ufficiale "Allegato 4": sostituisce integralmente
// $psclObligationOptions per la scuola — l'Allegato 4 non ha alcuna classificazione
// PSCL da barrare (concetto esclusivamente azienda), solo un'unica base giuridica fissa.
$schoolLegalBasisText = 'il trattamento dei dati personali effettuato tramite EMMA RE-R trova la propria '
    . 'base giuridica nell\'art. 6, par. 1, lett. c) del GDPR (necessità di adempiere ad un obbligo legale '
    . 'al quale il titolare è soggetto), discendente dall\'art. 5, comma 6, della L. 28 dicembre 2015, '
    . 'n. 221 (che istituisce la figura del Mobility Manager scolastico) e dall\'art. 229 del D.L. 19 '
    . 'maggio 2020, n. 34, nonché dalle Linee guida adottate con DGR Emilia-Romagna 27 aprile 2026, '
    . 'n. 637; per gli istituti paritari e privati la base giuridica si integra, ove ricorrente, con il '
    . 'legittimo interesse ex art. 6, par. 1, lett. f) GDPR.';

// EMMA RE-R: a 15pt (h1) WeasyPrint/Pango tratta il trattino ASCII come punto di
// interruzione di riga e lo elimina in fase di estrazione testo (confermato con
// pdftotext -layout su un PDF reale: "EMMA RER" invece di "EMMA RE-R"). Wrapparlo in
// white-space:nowrap ovunque compaia impedisce questo comportamento senza toccare la
// stringa del marchio.
$emmaRerNowrap = '<span style="white-space:nowrap;">EMMA RE-R</span>';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Modulo di adesione EMMA RE-R — richiesta n. <?= h($request->id) ?></title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.45; }
        h1 { font-size: 15pt; margin: 0 0 2mm; }
        h2 { font-size: 11.5pt; margin: 7mm 0 2mm; padding-bottom: 1mm; border-bottom: 1px solid #999; page-break-after: avoid; }
        h3 { font-size: 10pt; margin: 4mm 0 1.5mm; }
        .meta { font-size: 8.5pt; color: #555; margin-bottom: 5mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 1.4mm 2mm; }
        .kv th { width: 42%; font-weight: normal; color: #444; }
        .kv td { font-weight: bold; }
        .kv tr:nth-child(even) { background: #f4f4f4; }
        table.kv { page-break-inside: avoid; }
        table.people { margin-top: 2mm; font-size: 9pt; }
        table.people th { background: #e8e8e8; border: 1px solid #bbb; font-size: 8.5pt; }
        table.people td { border: 1px solid #bbb; }
        .decl { margin: 1.5mm 0; padding-left: 6mm; text-indent: -6mm; }
        .sign { margin-top: 12mm; }
        .sign .line { margin-top: 14mm; border-top: 1px solid #333; width: 70mm; padding-top: 1.5mm; font-size: 8.5pt; color: #555; }
        .note { margin-top: 6mm; padding: 3mm; background: #f4f4f4; border-left: 3px solid #888; font-size: 8.5pt; }
    </style>
</head>
<body>

<p style="font-size:8.5pt;color:#555;margin:0 0 1mm;letter-spacing:0.5pt;">
    <?= h($isScuola ? 'Allegato 4' : 'Allegato 3') ?>
</p>
<h1>Richiesta di adesione alla Piattaforma digitale "<?= $emmaRerNowrap ?>"</h1>
<?php if ($isScuola) : ?>
<p style="font-size:9pt;color:#444;margin:0 0 2mm;">
    per la gestione del piano degli spostamenti casa-scuola (Mobility Manager scolastici)
</p>
<?php else : ?>
<p style="font-size:9pt;color:#444;margin:0 0 2mm;">
    per la gestione del piano degli spostamenti casa-lavoro (Mobility Manager aziendali e di
    distretto)
</p>
<?php endif; ?>
<div class="meta">
    Richiesta n. <strong><?= h($request->id) ?></strong> ·
    Presentata il <?= h($request->created ? $request->created->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?> ·
    Documento generato il <?= h($generatedAt->i18nFormat('dd/MM/yyyy HH:mm')) ?>
</div>

<p>
    Spettabile — Comune di <strong><?= h($value('reference_city')) ?></strong>
    (<?= h($value('reference_province')) ?>) — Mobility Manager d'Area o Referente delle
    politiche di mobility management<br>
    <span style="font-size:8.5pt;color:#555;">(inviare via PEC al Comune territorialmente competente)</span>
</p>
<p>
    E, per conoscenza, Regione Emilia-Romagna — Viale Aldo Moro, 30 — 40127 Bologna — PEC:
    trasportopubblico@postacert.regione.emilia-romagna.it
</p>

<h2>1. Tipo di organizzazione</h2>
<?php
// Etichetta come aggiunta di piattaforma (round 5, estesa per B3/scuola): il flusso di
// adesione generico di questa applicazione supporta sia 'azienda' sia 'scuola' su più
// varianti di template, ma nessuno dei due template ufficiali che questo file replica
// (Allegato 3 per azienda, Allegato 4 per scuola) ha un campo "Tipo di organizzazione" —
// ciascuno è implicitamente mono-tipo. Il riferimento all'allegato nella nota sotto deve
// quindi seguire $isScuola, non essere fisso sull'uno o sull'altro: un render scuola non
// deve mai emettere il numero dell'allegato azienda e viceversa (vedi
// AdhesionDocumentServiceTest per le asserzioni negative). Nota volutamente resa un
// commento PHP (non un commento HTML `<!-- -->`, come altre note "meta" di questo file):
// un commento HTML finisce comunque nell'HTML generato (anche se invisibile a schermo),
// quindi qualunque menzione letterale di "Allegato 3"/"Allegato 4" al suo interno
// comparirebbe nell'output di ENTRAMBI i tipi indipendentemente dal branch corrente,
// falsando esattamente le asserzioni negative sopra citate — bug reale trovato scrivendo
// quei test.
?>
<p style="font-size:8.5pt;color:#888;font-style:italic;margin:0 0 2mm;">
    <?= h(sprintf(
        'Campo aggiuntivo della piattaforma, non previsto dall\'Allegato %s regionale.',
        $isScuola ? '4' : '3'
    )) ?>
</p>
<p><strong><?= h($organizationType) ?></strong></p>

<?php if ($isScuola) : ?>
<h2>2. Dati dell'istituto scolastico</h2>
<table class="kv">
    <tr><th>Denominazione</th><td><?= h($value('company_name')) ?></td></tr>
    <tr><th>Sede (via, n., comune, CAP)</th><td><?= h($legalAddress !== '' ? $legalAddress : '—') ?></td></tr>
    <tr><th>Codice meccanografico</th><td><?= h($value('school_mechanographic_code')) ?></td></tr>
    <tr>
        <th>Tipologia</th>
        <td>
            <?php foreach ($schoolTypeLabels as $optionKey => $optionLabel) : ?>
                <?= ($data['school_type'] ?? null) === $optionKey ? '☒' : '☐' ?>
                <?= h($optionLabel) ?>&nbsp;&nbsp;
            <?php endforeach; ?>
        </td>
    </tr>
    <tr>
        <th>Natura giuridica</th>
        <td>
            <?php foreach ($schoolLegalNatureLabels as $optionKey => $optionLabel) : ?>
                <?= ($data['school_legal_nature'] ?? null) === $optionKey ? '☒' : '☐' ?>
                <?= h($optionLabel) ?>&nbsp;&nbsp;&nbsp;
            <?php endforeach; ?>
        </td>
    </tr>
    <tr><th>PEC istituzionale</th><td><?= h($value('pec')) ?></td></tr>
    <tr><th>Telefono</th><td><?= h($value('phone')) ?></td></tr>
</table>
<!-- Codice fiscale: campo platform-internal (D6, docs/dev-notes/adesione-flow.md),
non previsto dall'Allegato 4 — companies.company_code/findExistingCompany() ne
dipendono. Etichettato qui, non nella tabella ufficiale sopra, per non far pensare a
un disallineamento in una revisione affiancata al documento ufficiale. -->
<p style="font-size:8.5pt;color:#888;font-style:italic;margin:2mm 0 0;">Campi aggiuntivi della piattaforma:</p>
<table class="kv">
    <tr><th>Codice fiscale</th><td><?= h($value('fiscal_code')) ?></td></tr>
    <tr><th>Comune di riferimento</th><td><?= h($value('reference_city')) ?></td></tr>
    <tr><th>Provincia di riferimento</th><td><?= h($value('reference_province')) ?></td></tr>
    <tr><th>Email</th><td><?= h($value('email')) ?></td></tr>
    <tr><th>Cellulare</th><td><?= h($value('mobile')) ?></td></tr>
</table>
<?php else : ?>
<h2>2. Dati dell'azienda / ente</h2>
<table class="kv">
    <?php foreach ($rows as $label => $key) : ?>
        <?php
        $display = $resolveRowDisplay($key, $label);
        // Salta le righe vuote dei campi specifici, per non allungare il modulo inutilmente.
        // La riga "Sede/i operativa/e oggetto del PSCL" resta sempre mostrata (ripiega
        // sulla sede legale via $resolveRowDisplay), quindi non è in questa lista. La riga
        // "Iscrizione al Registro delle Imprese di" (round 4, key null: valore accorpato
        // registry_territory/registry_number) è ora skippata per LABEL, non per key, dato
        // che ha rimpiazzato le due righe keyed 'registry_territory'/'registry_number'.
        $skipIfEmptyByKey = in_array($key, ['num_employees', 'ateco'], true);
        $skipIfEmptyByLabel = $label === 'Iscrizione al Registro delle Imprese di';
        if ($display === '—' && ($skipIfEmptyByKey || $skipIfEmptyByLabel)) {
            continue;
        }
        ?>
        <tr>
            <th><?= h($label) ?></th>
            <td><?= h($display) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p style="font-size:8.5pt;color:#888;font-style:italic;margin:2mm 0 0;">Campi aggiuntivi della piattaforma:</p>
<table class="kv">
    <?php foreach ($extraRows as $label => $key) : ?>
        <?php $display = $resolveRowDisplay($key, $label); ?>
        <tr>
            <th><?= h($label) ?></th>
            <td><?= h($display) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($isScuola) : ?>
<h2>3. Dati del Dirigente scolastico che sottoscrive l'adesione</h2>
<table class="kv">
    <tr><th>Cognome e nome</th><td><?= h($fullName('legal_rep_last_name', 'legal_rep_first_name')) ?></td></tr>
    <tr><th>Codice fiscale</th><td><?= h($value('legal_rep_cf')) ?></td></tr>
    <!-- Nessuna riga "Qualifica": l'Allegato 4 ha un solo ruolo possibile (Dirigente
    scolastico, legale rappresentante), non una scelta come per l'azienda. -->
    <tr>
        <th>Estremi del provvedimento di conferimento dell'incarico</th>
        <!-- Em-dash semplice come fallback ("—", il default di $value()), non "Non
        applicabile": a differenza di "Estremi dei poteri di rappresentanza" (azienda,
        pertinente solo per un procuratore), questo campo è SEMPRE pertinente per un
        Dirigente scolastico — un valore assente è un campo non compilato, non
        un'opzione non applicabile. -->
        <td><?= h($value('school_principal_appointment_reference')) ?></td>
    </tr>
    <tr><th>E-mail</th><td><?= h($value('legal_rep_email')) ?></td></tr>
</table>
<?php else : ?>
<h2>3. Dati del legale rappresentante che sottoscrive l'adesione</h2>
<table class="kv">
    <tr><th>Cognome e nome</th><td><?= h($fullName('legal_rep_last_name', 'legal_rep_first_name')) ?></td></tr>
    <tr><th>Codice fiscale</th><td><?= h($value('legal_rep_cf')) ?></td></tr>
    <!--
    Riga "Ruolo" (legal_rep_role) rimossa: non fa parte del template ufficiale
    Allegato 3 ed è un campo generico riusato altrove nel form (tutti i temi), la cui
    etichetta i18n lato RER duplica/confonde visivamente la riga "Qualifica" qui sotto.
    Il campo/l'input frontend non sono toccati, solo questa riga del PDF.
    -->
    <tr>
        <th>Qualifica</th>
        <td>
            <?= ($data['legal_rep_qualifica'] ?? null) === 'legale_rappresentante' ? '☒' : '☐' ?>
            Legale rappresentante&nbsp;&nbsp;&nbsp;
            <?= ($data['legal_rep_qualifica'] ?? null) === 'procuratore' ? '☒' : '☐' ?>
            Procuratore con idonei poteri
        </td>
    </tr>
    <tr>
        <th>Estremi dei poteri di rappresentanza</th>
        <!-- Fallback dedicato "Non applicabile" (round 4) invece del generico "—" di
        $value(): questa riga non è mai pertinente quando la qualifica non è
        "procuratore", e "Non applicabile" lo rende esplicito invece di sembrare un
        campo dimenticato — stesso $value(), solo il secondo argomento cambia. -->
        <td><?= h($value('legal_rep_powers_reference', 'Non applicabile')) ?></td>
    </tr>
    <tr><th>E-mail</th><td><?= h($value('legal_rep_email')) ?></td></tr>
</table>
<?php endif; ?>

<?php if ($isScuola) : ?>
<h2>4. Dati del Mobility Manager scolastico nominato</h2>
<table class="kv">
    <!-- Nessuna riga "Rapporto": l'Allegato 4 non chiede il rapporto tra il MM
    scolastico e l'istituto (concetto esclusivamente azienda). -->
    <tr><th>Cognome e nome</th><td><?= h($fullName('mm_last_name', 'mm_first_name')) ?></td></tr>
    <tr><th>Codice fiscale</th><td><?= h($value('mm_cf')) ?></td></tr>
    <tr><th>Atto di nomina (estremi e data)</th><td><?= h($value('mm_appointment_reference')) ?></td></tr>
    <tr><th>E-mail di servizio</th><td><?= h($value('mm_email')) ?></td></tr>
    <tr><th>Telefono</th><td><?= h($value('mm_phone')) ?></td></tr>
</table>
<?php else : ?>
<h2>4. Dati del Mobility Manager aziendale nominato</h2>
<table class="kv">
    <!-- Ordine ufficiale (round 4): Cognome e nome -> Codice fiscale -> Rapporto con
    l'azienda -> Atto di nomina (estremi e data) -> E-mail di servizio -> Telefono. -->
    <tr><th>Cognome e nome</th><td><?= h($fullName('mm_last_name', 'mm_first_name')) ?></td></tr>
    <tr><th>Codice fiscale</th><td><?= h($value('mm_cf')) ?></td></tr>
    <tr>
        <th>Rapporto con l'azienda</th>
        <td>
            <?php foreach ($mmRelationshipLabels as $optionKey => $optionLabel) : ?>
                <?= ($data['mm_relationship'] ?? null) === $optionKey ? '☒' : '☐' ?>
                <?= h($optionLabel) ?>&nbsp;&nbsp;
            <?php endforeach; ?>
        </td>
    </tr>
    <tr><th>Atto di nomina (estremi e data)</th><td><?= h($value('mm_appointment_reference')) ?></td></tr>
    <tr><th>E-mail di servizio</th><td><?= h($value('mm_email')) ?></td></tr>
    <tr><th>Telefono</th><td><?= h($value('mm_phone')) ?></td></tr>
</table>
<?php endif; ?>

<?php if ($isScuola) : ?>
    <?php
    // "Estensione del mandato" (Allegato 4): sostituisce integralmente la sezione MM di
    // distretto per la scuola — l'Allegato 4 non ha alcun concetto di distretto. Renderizzata
    // SOLO se compilata (a differenza della sezione MM di distretto sopra, che mostra sempre
    // un titolo/nota anche quando non usata): l'Allegato 4 stesso presenta questo campo come
    // puramente opzionale, senza un blocco "sezione non applicabile" dedicato.
    $mandateExtension = trim((string)($data['mandate_extension'] ?? ''));
    ?>
    <?php if ($mandateExtension !== '') : ?>
    <h2>Estensione del mandato</h2>
    <p>
        Da compilare nel caso in cui il Mobility Manager scolastico svolga la propria
        funzione per più istituti.
    </p>
    <p><?= nl2br(h($mandateExtension)) ?></p>
    <?php endif; ?>
<?php else : ?>
<h2>Sezione dedicata — Mobility Manager di distretto</h2>
<p>
    Da compilare SOLO se l'azienda intende avvalersi di un Mobility Manager di distretto,
    individuato congiuntamente con altre aziende limitrofe ai sensi dell'art. 3, § 3.5
    delle Linee guida adottate con d.g.r. 637/2026.
</p>
    <?php if (!empty($data['uses_district_mm'])) : ?>
    <p>L'azienda dichiara di avvalersi del seguente Mobility Manager di distretto:</p>
    <table class="kv">
        <tr><th>Cognome e nome</th><td><?= h($fullName('district_mm_last_name', 'district_mm_first_name')) ?></td></tr>
        <tr><th>Codice fiscale</th><td><?= h($value('district_mm_cf')) ?></td></tr>
        <tr><th>E-mail di servizio</th><td><?= h($value('district_mm_email')) ?></td></tr>
        <tr><th>Telefono</th><td><?= h($value('district_mm_phone')) ?></td></tr>
        <tr>
            <th>Atto di nomina collegiale</th>
            <td><?= h($value('district_mm_appointment_reference')) ?></td>
        </tr>
    </table>
    <!-- Lead-in (round 4): rende esplicito che le due opzioni sotto sono mutuamente
    esclusive e vanno barrate, stesso pattern del lead-in "Dato atto che:" per la
    classificazione PSCL più sotto nel documento. -->
    <p>Qualificazione del rapporto con il MM di distretto (barrare la casella applicabile):</p>
    <div class="decl">
        <?= ($data['district_relationship_type'] ?? null) === 'dipendente_azienda_distretto' ? '☒' : '☐' ?>
        Il MM di distretto è dipendente di una delle aziende del distretto. Azienda di
        appartenenza (denominazione, P.IVA): <?= h($value('district_employer_company')) ?>.
        L'azienda di appartenenza è designata responsabile del trattamento ai sensi
        dell'art. 28 GDPR, mediante apposito accordo scritto, dalle altre aziende del
        distretto.
    </div>
    <div class="decl">
        <?= ($data['district_relationship_type'] ?? null) === 'soggetto_esterno' ? '☒' : '☐' ?>
        Il MM di distretto è soggetto esterno condiviso (denominazione del soggetto /
        studio / società, P.IVA, sede): <?= h($value('district_external_entity')) ?>.
        Ciascuna azienda del distretto lo nomina autonomamente responsabile del
        trattamento ai sensi dell'art. 28 GDPR.
    </div>
        <?php
        // Difensivo: questo è un generatore di PDF pubblico, un elenco malformato non deve
        // mai far fallire la generazione — vedi lo stesso approccio per $people più sotto.
        $districtCompanies = is_array($data['district_companies'] ?? null) ? $data['district_companies'] : [];
        ?>
        <?php if (!empty($districtCompanies)) : ?>
        <h3>Elenco delle altre aziende del distretto coinvolte</h3>
        <table class="people">
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:45%">Denominazione</th>
                    <th style="width:25%">Codice Fiscale</th>
                    <th style="width:25%">Partita IVA</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_values($districtCompanies) as $i => $districtCompany) : ?>
                    <?php if (!is_array($districtCompany)) :
                        continue;
                    endif; ?>
                    <tr>
                        <td><?= h($i + 1) ?></td>
                        <td><?= h((string)($districtCompany['name'] ?? '—')) ?></td>
                        <td><?= h((string)($districtCompany['cf'] ?? '—')) ?></td>
                        <td><?= h((string)($districtCompany['vat'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    <?php else : ?>
    <p class="note">
        Sezione non applicabile: l'azienda non si avvale di un Mobility Manager di
        distretto condiviso con altre aziende del distretto.
    </p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($isScuola) : ?>
<p style="text-align:center;font-weight:bold;margin:6mm 0;">
    chiede, in qualità di Dirigente scolastico, legale rappresentante dell'istituto sopra
    indicato, l'adesione della scuola/istituto alla Piattaforma digitale regionale
    <?= $emmaRerNowrap ?> per la redazione del Piano degli Spostamenti Casa-Scuola (PSCS).
</p>
<?php else : ?>
<p style="text-align:center;font-weight:bold;margin:6mm 0;">
    chiede l'adesione dell'azienda/ente sopra indicato alla Piattaforma digitale regionale
    <?= $emmaRerNowrap ?> per la redazione del Piano degli Spostamenti Casa-Lavoro (PSCL).
</p>
<?php endif; ?>

<h2>Preso atto che</h2>
<!-- Marker "‒ " (round 4): U+2012 figure dash, non un trattino ASCII — occupa lo spazio
del rientro sporgente creato da ".decl" (padding-left: 6mm; text-indent: -6mm;), come un
bullet normale, invece di aggiungere un secondo livello di rientro. -->
<div class="decl">
    ‒ i flussi dei dati tra gli attori della piattaforma, la granularità geografica e
    temporale, le forme di anonimizzazione e i requisiti di sicurezza tecnica e
    organizzativa sono disciplinati dal Disciplinare tecnico adottato con atto della
    Regione Emilia-Romagna, suscettibile di aggiornamento periodico e comunicato ai
    soggetti accreditati tramite la piattaforma e il portale
    <span style="white-space:nowrap;">https://mobilita.regione.emilia-romagna.it</span>,
    e di accettarne integralmente i contenuti;
</div>
<div class="decl">
    ‒ i Mobility Manager d'area, i Referenti delle politiche di mobility management dei
    Comuni con popolazione inferiore a 50.000 abitanti e la Regione Emilia-Romagna
    accedono ai dati provenienti dai Mobility Manager aziendali, di distretto e
    scolastici esclusivamente in forma anonimizzata, in coerenza con il principio di
    minimizzazione di cui all'art. 5, par. 1, lett. c) del GDPR e con le modalità
    definite dal Disciplinare tecnico.
</div>

<h2>Dato atto che:</h2>
<?php if ($isScuola) : ?>
    <?php
    // Testo verbatim del template ufficiale Allegato 4 (D9/D10, docs/dev-notes/
    // adesione-flow.md): sostituisce integralmente il bullet di classificazione PSCL
    // dell'azienda — per la scuola non c'è una classificazione da barrare, solo
    // un'unica base giuridica fissa (L. 28 dicembre 2015, n. 221, istitutiva del MM
    // scolastico + art. 229 D.L. 34/2020 + Linee guida DGR 637/2026, con
    // l'integrazione del legittimo interesse per gli istituti paritari/privati).
    // Commento PHP (non HTML) per lo stesso motivo spiegato in §1 sopra: un
    // commento HTML sopravviverebbe nell'output e falserebbe l'asserzione negativa
    // di AdhesionDocumentServiceTest sulla stringa azienda-only che qui si sta
    // proprio sostituendo.
    ?>
    <p><?= h($schoolLegalBasisText) ?></p>
<?php else : ?>
    <!-- Doppio "che" (round 5, deciso — non ri-litigare): "Dato atto che:" (titolo) seguito da
    "che l'azienda/ente..." (paragrafo) produce visivamente "Dato atto che: che l'azienda/ente
    ...". Verificato contro il testo del template ufficiale "Allegato 3" nella sessione in cui è
    stata presa questa decisione: il doppio "che" è un difetto del testo ufficiale stesso —
    riprodotto intenzionalmente per fedeltà, non un errore di questo file. Non correggere il
    paragrafo qui sotto. -->
    <p>
        che l'azienda/ente, in relazione al trattamento dei dati personali del
        proprio personale tramite <?= $emmaRerNowrap ?>, si qualifica come segue (barrare la
        casella applicabile):
    </p>
    <?php foreach ($psclObligationOptions as $optionKey => $optionText) : ?>
        <div class="decl">
            <?= ($data['pscl_obligation_status'] ?? null) === $optionKey ? '☒' : '☐' ?>
            <?= h($optionText) ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Dichiara:</h2>
<?php
// Marker glyph consistency (round 3): il template ufficiale "Allegato 3" NON usa
// checkbox su questi bullet "Dichiara"/"Autocertificazione" (confermato con pdftotext
// -layout sul template sorgente) — solo testo dichiarativo, il vecchio marker testuale
// (barra quadra, X, barra quadra chiusa / barra quadra, spazio, barra quadra chiusa) era
// un'aggiunta non fedele. Un checkbox/marker resta corretto SOLO per campi genuinamente
// a scelta esclusiva (Qualifica, Rapporto con l'azienda, Classificazione PSCL sopra,
// Qualifica del MM di distretto in 4b) — qui sono semplici dichiarazioni booleane
// accetta/non-accetta, quindi stesso stile (nessun ".box") già usato per i bullet
// "Preso atto che" più sopra.
//
// Ordine (round 4): la sequenza dei 6 bullet sotto ricalca l'ordine ufficiale
// dell'Allegato 3. `accept_gdpr` è stata spostata (round 4) in fondo a questa sezione, poi
// (round 5) rimossa del tutto da qui e spostata ad affiancare la sezione "5. Elenco delle
// persone da abilitare" — vedi il blocco dedicato più sotto, subito dopo quell'h2. Questa
// sezione "Dichiara:" ora contiene SOLO i 6 bullet ufficiali, ciascuno col marker "‒ "
// (round 4, stesso U+2012 dei bullet "Preso atto che" sopra).
//
// Prefisso verbo rimosso (round 5): "Dichiara di"/"Il sottoscritto dichiara di" era
// ridondante — il titolo "Dichiara:" già governa grammaticalmente ciascun bullet come
// proposizione infinitiva (stile del template ufficiale, "‒ di essere consapevole…").
// Ogni bullet ora inizia "‒ di " seguito direttamente dalla proposizione infinitiva.
?>
<div class="decl">
    ‒ di essere consapevole, ai sensi degli artt. 46 e 47 del
    d.p.r. 445/2000, delle sanzioni penali nel caso di dichiarazioni non veritiere e
    falsità negli atti, richiamate dall'art. 76 del d.p.r. 445/2000.
</div>
<?php
// Dichiarazione UNCONDIZIONATA del template ufficiale (round 4: 2° della sequenza
// ufficiale, spostata qui da fondo sezione): affermazione sempre vera per chi
// sottoscrive, non gated da un proprio campo booleano nel modello dati — nessuna
// differenza di stile dagli altri bullet, solo nessun campo `data` dietro al testo.
?>
<?php if ($isScuola) : ?>
<div class="decl">
    ‒ di essere il legale rappresentante dell'istituto scolastico sopra indicato, ovvero
    dirigente a ciò legittimato con atto di conferimento della rappresentanza, munito di
    idonei poteri per la sottoscrizione del presente atto.
</div>
<?php else : ?>
<div class="decl">
    ‒ di essere il legale rappresentante dell'azienda/ente sopra indicato, ovvero
    dirigente a ciò legittimato con atto di conferimento della rappresentanza, munito di
    idonei poteri per la sottoscrizione del presente atto.
</div>
<?php endif; ?>
<div class="decl">
    ‒ di aver preso visione e di aver accettato integralmente le «Linee guida in
    materia di utilizzo della piattaforma EMMA RE-R per i mobility manager d'area,
    aziendali, scolastici e di distretto» approvate con DGR 27 aprile 2026, n. 637,
    reperibili al seguente link
    <span style="white-space:nowrap;">https://mobilita.regione.emilia-romagna.it/</span>,
    ed è informato, ai sensi del GDPR, che i dati raccolti su EMMA RE-R dalla Regione
    Emilia-Romagna saranno trattati per i soli fini riportati nelle sopracitate Linee
    guida e conservati nel rispetto di quanto in esse stabilito.
</div>
<div class="decl">
    ‒ di essere consapevole che, ai sensi dell'art. 10, comma 1, lett. b) e comma
    2 delle Linee guida adottate con DGR 27 aprile 2026, n. 637, la presente istanza è
    preventivamente verificata dal Comune territorialmente competente — incaricato
    dell'accertamento del ruolo dichiarato dall'aderente — ed è successivamente gestita
    dalla Regione Emilia-Romagna, la quale, conclusa la propria fase istruttoria, abilita
    l'accesso alla piattaforma EMMA RE-R.
</div>
<div class="decl">
    ‒ di nominare la Regione Emilia-Romagna, ai sensi e per gli effetti dell'art. 28
    del GDPR, quale responsabile esterno del trattamento dei dati personali limitatamente
    alle attività di gestione tecnica della piattaforma EMMA RE-R (manutenzione ordinaria
    ed evolutiva, aggiornamento, ottimizzazione, conservazione tecnica, gestione delle
    utenze e dei profili di accesso, tracciamento dei log), alle condizioni del documento
    «Accordo per il trattamento di dati personali» allegato al presente modulo
    (riproduttivo dell'Allegato 5 della DGR 637/2026); a tal fine il presente modulo, ad
    ogni effetto, vale anche quale sottoscrizione dell'accordo ex art. 28 GDPR.
</div>
<?php
// Dichiarazione UNCONDIZIONATA del template ufficiale (round 4: 6° e ultima della
// sequenza ufficiale). Come sopra, nessun campo booleano dedicato — e non più adiacente
// alla dichiarazione "legale rappresentante" (ora in 2ª posizione): le due erano vicine
// solo perché entrambe finivano "in coda" prima di questo round, non per un legame tra
// loro. Questo è anche l'ULTIMO bullet della sezione "Dichiara:" (round 5): `accept_gdpr`
// non segue più direttamente questo blocco — vedi sopra.
?>
<div class="decl">
    ‒ di essere consapevole che la comunicazione di tali dati è obbligatoria e il
    mancato conferimento non consente di dare corso alla richiesta.
</div>

<h2>5. Elenco delle persone da abilitare</h2>
<?php
// `accept_gdpr`: extra di piattaforma, non previsto dall'Allegato 3 regionale (round 3).
// Spostata (round 4) in fondo alla sezione "Dichiara:", poi (round 5) spostata QUI, subito
// sotto questo h2 — prima stava subito dopo il 6° bullet ufficiale, dove leggeva come una
// 7ª voce non numerata che facesse sembrare la sequenza 1-6 "tagliata"/incompleta. Qui è
// invece chiaramente associata alla sezione "Elenco delle persone", non a "Dichiara:".
// Resta senza marker "‒ " (non fa parte della sequenza numerata) e con la propria nota
// corsiva distintiva subito sotto, invariata dal round 3.
?>
<div class="decl">
    Dichiara di aver preso visione dell'informativa sul trattamento dei dati personali
    (Regolamento UE 2016/679 — GDPR).
</div>
<?php
// Riferimento all'allegato reso dinamico (B3/scuola): questa sezione è condivisa tra
// azienda e scuola (D10), ma la nota deve comunque citare l'allegato ufficiale corretto
// per il tipo corrente — non fissa sull'uno o sull'altro, per lo stesso motivo (e con lo
// stesso commento-non-HTML) della nota di §1 sopra.
?>
<p style="font-size:8.5pt;color:#888;font-style:italic;margin:0 0 3mm;">
    <?= h(sprintf(
        '(Dichiarazione aggiuntiva della piattaforma EMMA RE-R, non prevista dall\'Allegato %s regionale.)',
        $isScuola ? '4' : '3'
    )) ?>
</p>
<p style="font-size:8.5pt;color:#888;font-style:italic;margin:0 0 3mm;">
    <?= h(sprintf(
        'Sezione aggiuntiva della piattaforma EMMA RE-R, non prevista dall\'Allegato %s regionale.',
        $isScuola ? '4' : '3'
    )) ?>
</p>
<?php if (empty($people)) : ?>
    <p>Nessuna persona indicata.</p>
<?php else : ?>
    <table class="people">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:22%">Nome</th>
                <th style="width:23%">Cognome</th>
                <th style="width:30%">Email</th>
                <th style="width:20%">Codice Fiscale</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($people as $i => $person) : ?>
                <tr>
                    <td><?= h($i + 1) ?></td>
                    <td><?= h($person->first_name) ?></td>
                    <td><?= h($person->last_name) ?></td>
                    <td><?= h($person->email) ?></td>
                    <td><?= h($person->cf) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:8.5pt;color:#555;margin-top:2mm;">
        Totale persone da abilitare: <strong><?= h(count($people)) ?></strong>.
        L'abilitazione, in caso di esito positivo, ha durata di 365 giorni dalla data di
        approvazione.
    </p>
<?php endif; ?>

<div class="sign">
    <p>Luogo: <strong><?= h($value('place')) ?></strong> &nbsp;&nbsp;&nbsp; Data: <strong><?= h($generatedAt->i18nFormat('dd/MM/yyyy')) ?></strong></p>
    <div class="line">TIMBRO e FIRMA digitale del legale rappresentante</div>
</div>

<p class="note">
    Al presente documento si allega copia di un documento d'identità in corso di validità del
    legale rappresentante. (Dichiarazione sostitutiva dell'atto di notorietà, presentata ai
    sensi dell'art. 47 del d.p.r. 445/2000).
</p>

<div class="note">
    <strong>Come completare la richiesta:</strong> firmare digitalmente questo documento
    (formato PAdES, file <em>.pdf</em>, oppure CAdES, file <em>.p7m</em>) e ricaricarlo sulla
    piattaforma utilizzando il link ricevuto per email. La richiesta sarà presa in esame
    soltanto dopo il caricamento del documento firmato.
</div>

<?php
// --- Allegato: "Accordo per il trattamento di dati personali" (Allegato 5 DGR 637/2026) --
// Il template ufficiale dichiara che la sottoscrizione del modulo di adesione vale ANCHE
// come sottoscrizione di questo accordo art. 28 GDPR (vedi il blocco
// accept_data_processor_nomination nella sezione "Dichiara:", round 4): deve quindi essere fisicamente parte
// dello stesso PDF firmato, non un documento separato. Tutto il contenuto qui sotto è
// STATICO (uguale su ogni PDF generato, nessun lookup su $data) — nessun h() necessario
// sui letterali, ma i tag restano semplice HTML per coerenza con il resto del file.
?>
<div style="page-break-before: always;">
    <p style="font-size:8.5pt;color:#555;margin:0 0 1mm;letter-spacing:0.5pt;">Allegato</p>
    <h1>Accordo per il trattamento di dati personali</h1>
    <p>
        Il presente accordo costituisce parte integrante della domanda di adesione ad
        <?= $emmaRerNowrap ?> tra il soggetto fruitore (Comune, scuola, distretto aziendale,
        ecc.) e la Giunta della Regione Emilia-Romagna, designata responsabile del
        trattamento di dati personali ai sensi dell'art. 28 del GDPR.
    </p>

    <h3>Premesse</h3>
    <p>
        (A) Il presente accordo si compone delle clausole di seguito rappresentate e
        dall'Allegato 1: Glossario.
    </p>
    <p>
        (B) Le presenti clausole lasciano impregiudicati gli obblighi cui è soggetto il
        titolare del trattamento a norma del regolamento (UE) 2016/679.
    </p>
    <p>
        (C) Le presenti clausole non garantiscono, di per sé, il rispetto degli obblighi
        connessi ai trasferimenti internazionali conformemente al capo V del regolamento
        (UE) 2016/679.
    </p>
    <p>
        (D) Le presenti clausole vanno lette e interpretate alla luce delle disposizioni
        del regolamento (UE) 2016/679.
    </p>
    <p>
        (E) Le presenti clausole non devono essere interpretate in un senso che non sia
        conforme ai diritti e agli obblighi previsti dal regolamento (UE) 2016/679 o che
        pregiudichi i diritti o le libertà fondamentali degli interessati.
    </p>
    <p>
        (F) In caso di contraddizione tra le presenti clausole e le disposizioni di
        accordi correlati, vigenti tra le parti al momento dell'accettazione delle
        presenti clausole, o conclusi successivamente, prevalgono le presenti clausole.
    </p>

    <p><strong>Le Parti convengono quanto segue:</strong></p>

    <h3>1. Descrizione del trattamento</h3>
    <p>1.1 Finalità per le quali i dati personali sono trattati per conto del titolare del trattamento:</p>
    <p style="margin-left:4mm;">
        Gestione e manutenzione di <?= $emmaRerNowrap ?> per garantirne l'accesso agli
        utenti accreditati, il continuo aggiornamento e la più efficace ottimizzazione.
    </p>
    <p>1.2 Categorie di interessati i cui dati personali sono trattati:</p>
    <p style="margin-left:4mm;">
        ☒ Dipendenti/Consulenti&nbsp;&nbsp;&nbsp;☒ Utenti&nbsp;&nbsp;&nbsp;☒ Lavoratori<br>
        ☐ Soggetti che ricoprono cariche sociali&nbsp;&nbsp;&nbsp;☐ Beneficiari o
        assistiti&nbsp;&nbsp;&nbsp;☐ Pazienti<br>
        ☐ Minori&nbsp;&nbsp;&nbsp;☐ Persone vulnerabili&nbsp;&nbsp;&nbsp;☐ Migranti<br>
        ☐ Studenti maggiorenni&nbsp;&nbsp;&nbsp;☐ Cittadini
    </p>
    <p>1.3 Categorie di dati personali trattati:</p>
    <p style="margin-left:4mm;">
        ☒ Dati personali comuni<br>
        ☐ Dati personali di natura particolare<br>
        ☐ Dati personali relativi a condanne penali e reati
    </p>

    <h3>2. Trattamento dei dati nel rispetto delle istruzioni della Giunta della Regione Emilia-Romagna</h3>
    <p>
        2.1 Il responsabile del trattamento, relativamente a tutti i dati personali che
        tratta per conto del titolare garantisce che:
    </p>
    <p>
        2.1.1 tratta tali dati personali solo ai fini dell'utilizzo di <?= $emmaRerNowrap ?>,
        e, successivamente, solo nel rispetto di quanto eventualmente concordato dalle
        parti per iscritto, agendo pertanto, esclusivamente sulla base delle istruzioni
        documentate e fornite dal titolare;
    </p>
    <p>
        2.1.2 non trasferisce i dati personali a soggetti terzi, se non nel rispetto
        delle condizioni di liceità assolte dal titolare e a fronte di quanto
        disciplinato nel presente accordo;
    </p>
    <p>
        2.1.3 non tratta o utilizza i dati personali per finalità diverse da quelle per
        cui è conferito incarico dal titolare, financo per trattamenti aventi finalità
        compatibili con quelle originarie;
    </p>
    <p>
        2.1.4 prima di iniziare ogni trattamento e, ove occorra, in qualsiasi altro
        momento, informerà il titolare se, a suo parere, una qualsiasi istruzione
        fornita dal titolare si ponga in violazione della normativa applicabile.
    </p>
    <p>
        2.2 Al fine di dare seguito alle eventuali richieste da parte di soggetti
        interessati, il responsabile del trattamento si obbliga ad adottare procedure
        idonee a garantire il rispetto dei diritti degli interessati relativamente ai
        loro dati personali (accesso, rettifica, cancellazione, limitazione), e a
        somministrare l'informativa per il trattamento dei dati personali quando
        applicabile.
    </p>
    <p>
        2.3 Il responsabile del trattamento deve garantire e fornire al titolare
        cooperazione, assistenza e le informazioni che potrebbero essere ragionevolmente
        richieste dallo stesso, ivi compresi i provvedimenti del Garante per la
        protezione dei dati personali.
    </p>
    <p>
        2.4 Il responsabile del trattamento, ai sensi dell'art. 30 del Regolamento,
        mantiene un registro dei trattamenti dati personali che riporti tutte le
        informazioni richieste dalla norma.
    </p>
    <p>
        2.5 Il responsabile del trattamento assicura la massima collaborazione al fine
        dell'esperimento delle valutazioni di impatto ex art. 35 del GDPR.
    </p>

    <h3>3. Le misure di sicurezza</h3>
    <p>
        3.1 Il responsabile del trattamento adotta e mantiene appropriate misure di
        sicurezza, sia tecniche che organizzative, per proteggere i dati personali.
    </p>
    <p>3.2 Garantisce la separazione logica dei dati trattati per conto del titolare.</p>
    <p>
        3.3 Conserva gli estremi identificativi degli amministratori di sistema in
        attuazione del Provvedimento del Garante 27 novembre 2008.
    </p>
    <p>
        3.4 Adotta misure tecniche ed organizzative adeguate a salvaguardare la
        sicurezza delle reti di comunicazione elettronica.
    </p>
    <p>
        3.5 Attesta la conformità della propria organizzazione almeno ai parametri di
        livello minimo di cui alle misure di sicurezza individuate da AGID con
        circolare n. 2/2017.
    </p>
    <p>
        3.6 Nel valutare l'adeguato livello di sicurezza, le parti tengono conto dello
        stato dell'arte, dei costi, della natura, del contesto e delle finalità del
        trattamento.
    </p>

    <h3>4. Analisi dei rischi, privacy by design e privacy by default</h3>
    <p>
        4.1 Il responsabile assicura cooperazione e assistenza al titolare per
        l'attuazione delle azioni di mitigazione dei rischi identificati.
    </p>
    <p>
        4.2 Consente al titolare di adottare ogni misura tecnica ed organizzativa
        opportuna in materia di protezione dati.
    </p>
    <p>
        4.3 In linea con i principi di privacy by default, tratta per impostazione
        predefinita esclusivamente i dati personali necessari per ogni specifica finalità.
    </p>

    <h3>5. Soggetti autorizzati ad effettuare i trattamenti — Designazione</h3>
    <p>
        5.1 Il responsabile garantisce competenze ed affidabilità dei propri dipendenti
        e collaboratori autorizzati al trattamento.
    </p>
    <p>
        5.2 Garantisce che gli autorizzati abbiano ricevuto adeguata formazione in
        materia di protezione dei dati personali e sicurezza informatica.
    </p>
    <p>
        5.3 Impone ai propri autorizzati obblighi di riservatezza e risponde
        direttamente per qualsiasi divulgazione di dati personali ad opera di tali
        soggetti.
    </p>

    <h3>6. Documentazione e rispetto</h3>
    <p>6.1 Le parti devono essere in grado di dimostrare il rispetto delle presenti clausole.</p>
    <p>
        6.2 Il responsabile risponde prontamente e adeguatamente alle richieste di
        informazioni del titolare relative al trattamento dei dati.
    </p>
    <p>
        6.3 Il responsabile mette a disposizione del titolare tutte le informazioni
        necessarie a dimostrare il rispetto degli obblighi stabiliti e contribuisce alle
        attività di revisione, a intervalli ragionevoli o se vi sono indicazioni di
        inosservanza.
    </p>
    <p>
        6.4 Il titolare può condurre l'attività di revisione autonomamente o incaricare
        un revisore indipendente.
    </p>
    <p>
        6.5 Su richiesta, le parti mettono a disposizione delle autorità di controllo
        competenti le informazioni di cui alla presente clausola.
    </p>

    <h3>7. Ricorso a sub-responsabili del trattamento</h3>
    <p>
        7.1 Il responsabile è autorizzato alla designazione di altri responsabili del
        trattamento (sub-responsabili) previa informazione al titolare.
    </p>
    <p>
        7.2 L'autorizzazione è subordinata al possesso, da parte del sub-responsabile,
        dei seguenti requisiti: (a) sede legale in uno degli Stati membri dell'UE; (b)
        divieto di trasferimento dei dati in Paesi extra UE; (c) rapporto di subappalto
        o partnership con il responsabile; (d) certificazione
        <span style="white-space:nowrap;">ISO/IEC 27001</span> o garanzie
        equivalenti; (e) disciplina dei compiti mediante atto scritto.
    </p>
    <p>
        7.3 Il responsabile resta pienamente responsabile nei confronti del titolare
        dell'adempimento degli obblighi del sub-responsabile.
    </p>

    <h3>8. Trattamento dei dati personali fuori dall'area economica europea</h3>
    <p>
        8.1 Il titolare non autorizza il trasferimento dei dati personali oggetto di
        trattamento al di fuori dell'Unione Europea.
    </p>

    <h3>9. Assistenza al titolare del trattamento</h3>
    <p>
        9.1 Il responsabile notifica prontamente al titolare qualunque richiesta
        ricevuta dall'interessato e non risponde direttamente.
    </p>
    <p>
        9.2 Il responsabile assiste il titolare nell'adempimento degli obblighi di
        rispondere alle richieste degli interessati e degli adempimenti ex artt. 32, 33,
        34, 35, 36 GDPR.
    </p>

    <h3>10. Notifica di una violazione dei dati personali</h3>
    <p>
        10.1 In caso di violazione, il responsabile coopera con il titolare
        nell'adempimento degli obblighi di cui agli artt. 33 e 34 GDPR.
    </p>
    <p>
        10.2 In caso di violazione dei dati trattati dal responsabile, questi ne dà
        notifica al titolare senza ingiustificato ritardo, fornendo descrizione della
        natura della violazione, recapiti del punto di contatto, probabili conseguenze e
        misure adottate.
    </p>

    <h3>11. Inosservanza delle clausole e risoluzione</h3>
    <p>
        11.1 Qualora il responsabile violi gli obblighi, il titolare può istruirlo a
        sospendere il trattamento fino al ripristino del rispetto delle clausole o
        risolvere il rapporto.
    </p>
    <p>
        11.2 Alla risoluzione, il responsabile — a scelta del titolare — cancella o
        restituisce i dati personali.
    </p>

    <h3>12. Responsabilità e manleve</h3>
    <p>
        12.1 Il responsabile tiene indenne e manleva il titolare da ogni perdita, costo,
        sanzione, danno e responsabilità derivante dalla violazione delle disposizioni
        del presente accordo.
    </p>
    <p>
        12.2 Il rispetto delle istruzioni di cui al presente accordo non produce diritto
        al rimborso delle eventuali spese che il responsabile potrebbe dover sostenere
        per conformarsi.
    </p>

    <h3>Allegato 1 — Glossario</h3>
    <p>
        Definizioni: «Garante», «Dati personali», «GDPR», «Normativa Applicabile»,
        «Reclamo», «Titolare del Trattamento», «Trattamento», «Responsabile del
        trattamento», «Pseudonimizzazione» — secondo le definizioni dell'Allegato 1
        dell'Accordo per il trattamento di dati personali approvato con DGR Emilia-Romagna
        27 aprile 2026, n. 637.
    </p>
</div>

</body>
</html>

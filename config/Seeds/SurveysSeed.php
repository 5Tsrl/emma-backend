<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Surveys seed.
 */
class SurveysSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run()
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=0;');
        $data = [
            [
                'id' => 1,
                'name' => 'Questionario Test Automatico NON MODIFICARE',
                'company_id' => null,
                'version_tag' => 'template',
                'description' => 'template from Chicco Test Automatico NON MODIFICARE',
                'date' => null,
                'created' => '2025-07-16 07:55:00',
                'modified' => '2025-07-16 07:55:00',
                'user_id' => null,
                'opening_date' => null,
                'closing_date' => null,
                'welcome' => '
   <p class="my-2">
     Benvenuto,
    </p>
    <p>
Il questionario che stai per compilare permetterà al Mobility manager "aziendale" o "scolastico" di valutare le modalità ed i mezzi che utilizzi per 
<b>IL TUO SPOSTAMENTO Casa-Lavoro o Casa-Scuola</b>, di redigere il Piano degli spostamenti Casa-Lavoro o Casa-Scuola della tua Organizzazione lavorativa o di studio in modo utile, preciso e puntuale.
</p>    <p>

Con l\'obiettivo di ottimizzare e migliorare le abitudini del tuo spostamento riducendone il relativo impatto sull\'ambiente, il PSCL o PSCS permetterà alla tua Organizzazione lavorativa o di studio di avviare iniziative e politiche di mobilità sostenibile, migliorare e razionalizzare gli spostamenti sistematici dei dipendenti o degli studenti e ridurre le emissioni inquinanti con lo scopo di migliorare la qualità della vita.
</p>    <p>
Le informazioni che inserirai nel questionario saranno utilizzate soltanto per le finalità descritte, trattate nel rispetto della vigente normativa in materia di privacy e dei principi di correttezza, liceità e tutela della riservatezza ed eventualmente conferite a terzi solo in forma anonima o aggregata secondo le modalità consentite dalla Legge.
</p>    <p>
Le informazioni inserite nel questionario confluiranno <b>pseudonimizzate</b> nella Piattaforma di Mobility Management denominata Emma che ha la funzione di facilitare le attività del Mobility Manager rese obbligatorie dalle norme vigenti e permettere all\'Organizzazione lavorativa pubblica o privata di adottare il Piano di spostamento casa-lavoro o casa-studio. 
</p>    <p>
Questa attività è svolta ai sensi del Decreto "Rilancio" approvato dal Consiglio dei Ministri il 13 maggio 2020 e del successivo DECRETO 12 maggio 2021 "Modalità attuative delle disposizioni relative alla figura del Mobility Manager.
</p>    <p>
',
                'sending_mode' => 'a',
                'logo' => null,
                'partnership' => null,
                'show_translation' => null,
                'year' => null
            ],
            [
                'id' => 2,
                'name' => 'Questionario Emilia Romagna',
                'company_id' => null,
                'version_tag' => 'template',
                'description' => 'template from Emilia Romagna',
                'date' => null,
                'created' => '2025-07-16 07:55:00',
                'modified' => '2025-07-16 07:55:00',
                'user_id' => null,
                'opening_date' => null,
                'closing_date' => null,
                'welcome' => '
   <p class="my-2">
     Benvenuto,
    </p>
    <p>
Il questionario che stai per compilare permetterà al Mobility manager "aziendale" o "scolastico" di valutare le modalità ed i mezzi che utilizzi per 
<b>IL TUO SPOSTAMENTO Casa-Lavoro o Casa-Scuola</b>, di redigere il Piano degli spostamenti Casa-Lavoro o Casa-Scuola della tua Organizzazione lavorativa o di studio in modo utile, preciso e puntuale.
</p>    <p>

Con l\'obiettivo di ottimizzare e migliorare le abitudini del tuo spostamento riducendone il relativo impatto sull\'ambiente, il PSCL o PSCS permetterà alla tua Organizzazione lavorativa o di studio di avviare iniziative e politiche di mobilità sostenibile, migliorare e razionalizzare gli spostamenti sistematici dei dipendenti o degli studenti e ridurre le emissioni inquinanti con lo scopo di migliorare la qualità della vita.
</p>    <p>
Le informazioni che inserirai nel questionario saranno utilizzate soltanto per le finalità descritte, trattate nel rispetto della vigente normativa in materia di privacy e dei principi di correttezza, liceità e tutela della riservatezza ed eventualmente conferite a terzi solo in forma anonima o aggregata secondo le modalità consentite dalla Legge.
</p>    <p>
Le informazioni inserite nel questionario confluiranno <b>pseudonimizzate</b> nella Piattaforma di Mobility Management denominata Emma che ha la funzione di facilitare le attività del Mobility Manager rese obbligatorie dalle norme vigenti e permettere all\'Organizzazione lavorativa pubblica o privata di adottare il Piano di spostamento casa-lavoro o casa-studio. 
</p>    <p>
Questa attività è svolta ai sensi del Decreto "Rilancio" approvato dal Consiglio dei Ministri il 13 maggio 2020 e del successivo DECRETO 12 maggio 2021 "Modalità attuative delle disposizioni relative alla figura del Mobility Manager.
</p>    <p>
',
                'sending_mode' => 'a',
                'logo' => null,
                'partnership' => null,
                'show_translation' => null,
                'year' => null
            ]
        ];

        $table = $this->table('surveys');
        $table->truncate();
        $table->insert($data)->save();
        $this->execute('SET FOREIGN_KEY_CHECKS=1;');
    }
}

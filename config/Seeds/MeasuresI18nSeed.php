<?php
declare(strict_types=1);

//<editor-fold desc="Preamble">
/**
 * EMMA(tm) : Electronic Mobility Management Applications
 * Copyright (c) 5T Torino, Regione Piemonte, Città Metropolitana di Torino
 *
 * SPDX-License-Identifier: EUPL-1.2
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) 5T - https://5t.torino.it
 * @link      https://emma.5t.torino.it
 * @author    Massimo INFUNTI - https://github.com/impronta48
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

use Migrations\AbstractSeed;

/**
 * MeasuresI18n seed.
 *
 * Semina le traduzioni francesi (fr) dei campi `name` e `description` delle
 * misure PSCL (Piano Spostamenti Casa-Lavoro) nella tabella EAV `i18n` usata
 * dal Translate behavior (vedi `MeasuresTable::initialize` ->
 * `addBehavior('Translate', ['fields' => ['name', 'description']])`, aggiunto
 * separatamente a questo seed).
 *
 * IMPORTANTE - modello e campi:
 * Il `model` su `i18n` è 'Measures' (referenceName del behavior, cioè il nome
 * della classe Table senza il suffisso 'Table'). A differenza di
 * SectionsI18nSeed (solo `description`), qui si traducono ENTRAMBI i campi
 * `name` e `description` per ogni riga, quindi ogni misura genera 2 righe
 * `i18n` (una per campo).
 *
 * Le 61 righe di `measures` sul DB `emma_rer` (brand RER) si dividono in:
 * - 5 righe con `type = 0` ("asse"/"axe", chiamate "pillar" nel frontend
 *   5t.vue prima della riorganizzazione che ha fuso il vecchio modello
 *   Pillars dentro Measures con `type = 0`);
 * - 56 righe con `type = 1` (le misure vere e proprie).
 * Gli id (foreign_key) sono quelli di `emma_rer.measures` e non sono
 * portabili su altri DB con la stessa garanzia delle CompanyTypes (modello
 * condiviso da seed), ma coincidono con quelli del DB target di questo seed.
 *
 * Idempotente: rimuove le righe fr esistenti PRIMA di reinserirle, così può
 * essere rieseguito senza creare duplicati.
 *
 * ATTENZIONE - scoping della DELETE (a differenza di SectionsI18nSeed):
 * qui la DELETE è scopata solo su `model = 'Measures' AND locale = 'fr'`
 * (senza filtro sui foreign_key), perché a differenza di Questions/Sections
 * (che condividono `i18n` con ~290 altre traduzioni non correlate), le righe
 * `i18n` con `model = 'Measures'` sono interamente di proprietà di questo
 * seed: al momento della scrittura non esiste nessun'altra riga `i18n` con
 * quel model. Una DELETE ristretta ai soli foreign_key non sarebbe più sicura
 * qui e aggiungerebbe solo complessità.
 *
 * Esecuzione: bin/cake migrations seed --seed MeasuresI18nSeed
 */
class MeasuresI18nSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * @return void
     */
    public function run()
    {
        // foreign_key => ['name' => ..., 'description' => ...] (traduzione francese).
        // Assi (type = 0, id 185-189) in fondo all'array; le misure (type = 1)
        // sono raggruppate per asse/categoria, come nel DB di origine.
        $translations = [
            // ASSE 1 - Disincentivare l'uso individuale dell'autovettura privata
            78 => [ // tariffa-sosta
                'name' => '1.1.2 STATIONNEMENT - Tarification du stationnement automobile',
                'description' => "Introduction d'un système de tarification des aires de stationnement de "
                    . "l'entreprise destinées aux véhicules motorisés privés (voitures, motos, cyclomoteurs)",
            ],
            97 => [ // community-carpooling
                'name' => '1.2.2 COVOITURAGE - Communauté de covoiturage',
                'description' => "Création d'un groupe social de covoiturage d'entreprise pour l'organisation "
                    . 'des équipages entre les salariés',
            ],
            98 => [ // contributi-carpooling
                'name' => '1.2.4 COVOITURAGE - Contributions financières aux covoitureurs',
                'description' => 'Versement de contributions financières aux salariés covoitureurs',
            ],
            101 => [ // riduzione-sosta
                'name' => '1.1.1 STATIONNEMENT - Réduction du stationnement automobile',
                'description' => "Réduction des places de stationnement de l'entreprise habituellement "
                    . 'destinées aux véhicules motorisés privés (voitures, motos, cyclomoteurs)',
            ],
            104 => [ // convenzioni-carsharing
                'name' => '1.3.1 AUTOPARTAGE - Conventions autopartage public',
                'description' => "Mise en place, auprès de l'organisme gestionnaire, de conventions à tarifs "
                    . "préférentiels pour le service d'autopartage public",
            ],
            153 => [ // sosta-carpooling
                'name' => '1.2.1 COVOITURAGE - Places réservées aux covoitureurs',
                'description' => "Aménagement de places de stationnement d'entreprise réservées aux salariés "
                    . 'covoitureurs',
            ],
            154 => [ // piattaforma-carpooling
                'name' => '1.2.3 COVOITURAGE – Plateforme de covoiturage',
                'description' => "Mise en place d'une plateforme / application / section intranet de "
                    . "covoiturage d'entreprise pour l'organisation des équipages entre les salariés",
            ],
            155 => [ // auto-carpooling
                'name' => "1.2.5 COVOITURAGE - Véhicules d'entreprise pour le covoiturage",
                'description' => "Mise à disposition de véhicules d'entreprise réservés au covoiturage",
            ],
            156 => [ // carsharing-aziendale
                'name' => "1.3.2 AUTOPARTAGE - Autopartage d'entreprise",
                'description' => "Mise en place d'un service d'autopartage d'entreprise réservé exclusivement "
                    . 'aux salariés',
            ],
            157 => [ // altra-asse1
                'name' => '1.4.1 AXE 1 - Autre mesure',
                'description' => "Mise en place d'une autre mesure visant à réduire l'usage individuel de la "
                    . 'voiture privée par les salariés',
            ],

            // ASSE 2 - Favorire l'uso del trasporto pubblico
            92 => [ // infrastruttura-tpl
                'name' => "2.4.1 DEMANDES À LA COLLECTIVITÉ - Amélioration de l'infrastructure de transport "
                    . 'public',
                'description' => "Interlocution avec l'Administration communale pour demander l'amélioration "
                    . "de l'infrastructure du service de transport public urbain (par ex. tracé des lignes, "
                    . 'emplacement des arrêts), à travers un engagement proactif, y compris financier, à la '
                    . 'charge du PSCL',
            ],
            93 => [ // navetta
                'name' => "2.1.3 TRANSPORT PUBLIC URBAIN - Navette d'entreprise",
                'description' => "Introduction d'un service de navette d'entreprise réservé exclusivement aux "
                    . 'salariés',
            ],
            95 => [ // convenzioni-tplurbano
                'name' => '2.1.1 TRANSPORT PUBLIC URBAIN - Conventions',
                'description' => "Mise en place, auprès de l'organisme gestionnaire, de conventions à tarifs "
                    . "préférentiels pour l'achat de titres de transport et/ou d'abonnements au transport "
                    . 'public urbain',
            ],
            158 => [ // contributi-tplurbano
                'name' => '2.1.2 TRANSPORT PUBLIC URBAIN - Contributions',
                'description' => "Versement de contributions financières aux salariés pour l'achat de titres "
                    . "de transport et/ou d'abonnements au transport public urbain",
            ],
            159 => [ // convenzioni-tplexg
                'name' => '2.2.1 TRANSPORT PUBLIC INTERURBAIN ROUTIER - Conventions',
                'description' => "Mise en place, auprès de l'organisme gestionnaire, de conventions à tarifs "
                    . "préférentiels pour l'achat de titres de transport et/ou d'abonnements au transport "
                    . 'public interurbain routier',
            ],
            160 => [ // contributi-tplexgomma
                'name' => '2.2.2 TRANSPORT PUBLIC INTERURBAIN ROUTIER - Contributions',
                'description' => "Versement de contributions financières aux salariés pour l'achat de titres "
                    . "de transport et/ou d'abonnements au transport public interurbain routier",
            ],
            161 => [ // convenzioni-tplexf
                'name' => '2.3.1 TRANSPORT PUBLIC INTERURBAIN FERROVIAIRE - Conventions',
                'description' => "Mise en place, auprès de l'organisme gestionnaire, de conventions à tarifs "
                    . "préférentiels pour l'achat de titres de transport et/ou d'abonnements au transport "
                    . 'public interurbain ferroviaire',
            ],
            162 => [ // contributi-tplexferro
                'name' => '2.3.2 TRANSPORT PUBLIC INTERURBAIN FERROVIAIRE - Contributions',
                'description' => "Versement de contributions financières aux salariés pour l'achat de titres "
                    . "de transport et/ou d'abonnements au transport public interurbain ferroviaire",
            ],
            163 => [ // esercizio-tpl
                'name' => "2.4.2 DEMANDES À LA COLLECTIVITÉ - Amélioration de l'exploitation du transport "
                    . 'public',
                'description' => "Interlocution avec l'Administration communale pour demander l'amélioration "
                    . "de l'exploitation du service de transport public urbain (par ex. horaires et/ou "
                    . 'fréquences de passage), à travers un engagement proactif, y compris financier, à la '
                    . 'charge du PSCL',
            ],
            164 => [ // altra-asse2
                'name' => '2.5.1 AXE 2 - Autre mesure',
                'description' => "Mise en place d'une autre mesure visant à favoriser l'usage du transport "
                    . 'public par les salariés',
            ],

            // ASSE 3 - Favorire la mobilità attiva
            83 => [ // sosta-bici
                'name' => '3.2.2 MOBILITÉ CYCLABLE - Aires de stationnement pour vélos',
                'description' => "Réalisation et/ou réaménagement des aires de l'entreprise destinées au "
                    . 'stationnement des vélos, y compris râteliers et/ou mobilier urbain',
            ],
            84 => [ // spogliatoi
                'name' => '3.2.1 MOBILITÉ CYCLABLE - Vestiaires cyclistes',
                'description' => "Réalisation, dans le périmètre de l'entreprise, de vestiaires réservés aux "
                    . 'salariés, équipés de douches et de casiers',
            ],
            85 => [ // segnaletica
                'name' => '3.4.2 DEMANDES À LA COLLECTIVITÉ - Amélioration de la signalisation',
                'description' => "Interlocution avec l'Administration communale pour demander le "
                    . 'réaménagement de la signalisation des infrastructures piétonnes et/ou cyclables à '
                    . "proximité de l'U.L., à travers un engagement proactif, y compris financier, à la "
                    . 'charge du PSCL',
            ],
            86 => [ // infrastrutture-ciclopedonali
                'name' => '3.4.3 DEMANDES À LA COLLECTIVITÉ - Amélioration des infrastructures piétonnes '
                    . 'et/ou cyclables',
                'description' => "Interlocution avec l'Administration communale pour demander la réalisation "
                    . 'et/ou la sécurisation des infrastructures piétonnes et/ou cyclables à proximité de '
                    . "l'U.L., à travers un engagement proactif, y compris financier, à la charge du PSCL",
            ],
            87 => [ // incentivi-b2w
                'name' => '3.1.6 MOBILITÉ CYCLABLE - Incitations financières au Bike To Work',
                'description' => 'Versement de contributions financières aux salariés qui utilisent le vélo, '
                    . "l'e-bike ou des dispositifs de micromobilité pour leurs déplacements domicile-travail",
            ],
            89 => [ // miglioramento-bicimia
                'name' => '3.4.1 DEMANDES À LA COLLECTIVITÉ - Amélioration du service de vélos partagés '
                    . 'municipal',
                'description' => "Interlocution avec l'Administration communale pour demander l'amélioration "
                    . 'du service Bicimia (par ex. mise en place de stations et/ou de vélos supplémentaires), '
                    . 'à travers un engagement proactif, y compris financier, à la charge du PSCL',
            ],
            90 => [ // ebike-comodato
                'name' => "3.3.4 MOBILITÉ PARTAGÉE - Attribution d'e-bikes en prêt à usage",
                'description' => "Achat d'e-bikes d'entreprise à attribuer en prêt à usage aux salariés pour "
                    . 'leurs déplacements personnels domicile-travail',
            ],
            105 => [ // manutenzione-bici
                'name' => "3.2.4 MOBILITÉ CYCLABLE - Outils pour l'entretien des vélos",
                'description' => "Mise à disposition, par l'entreprise, d'espaces et d'outils pour l'entretien "
                    . 'des vélos (par ex. pompes à air)',
            ],
            165 => [ // acquisto-bici
                'name' => "3.1.1 MOBILITÉ CYCLABLE - Conventions pour l'achat de vélos ou de dispositifs de "
                    . 'micromobilité',
                'description' => 'Mise en place, auprès de commerces, de conventions à tarifs préférentiels '
                    . "pour l'achat de vélos ou de dispositifs de micromobilité",
            ],
            166 => [ // acquisto-ebike
                'name' => "3.1.2 MOBILITÉ CYCLABLE - Conventions pour l'achat d'e-bikes",
                'description' => 'Mise en place, auprès de commerces, de conventions à tarifs préférentiels '
                    . "pour l'achat d'e-bikes",
            ],
            167 => [ // accessori-bici
                'name' => "3.1.3 MOBILITÉ CYCLABLE - Conventions d'assurance et/ou d'achat d'accessoires",
                'description' => "Mise en place de conventions d'assurance ou de remises, auprès de commerces, "
                    . "pour l'achat d'accessoires de mobilité cyclable (par ex. casque, pompe à air)",
            ],
            168 => [ // contributi-bici
                'name' => "3.1.4 MOBILITÉ CYCLABLE - Contributions pour l'achat de vélos ou de dispositifs de "
                    . 'micromobilité',
                'description' => "Versement de contributions financières aux salariés pour l'achat de vélos "
                    . 'ou de dispositifs de micromobilité (trottinettes, hoverboards, segways, monoroues)',
            ],
            169 => [ // contributi-ebike
                'name' => "3.1.5 MOBILITÉ CYCLABLE - Contributions pour l'achat d'e-bikes",
                'description' => "Versement de contributions financières aux salariés pour l'achat d'e-bikes",
            ],
            170 => [ // community-ciclisti
                'name' => '3.1.7 MOBILITÉ CYCLABLE - Communauté de cyclistes',
                'description' => "Création d'une communauté des salariés cyclistes",
            ],
            171 => [ // sosta-ebike
                'name' => '3.2.3 MOBILITÉ CYCLABLE - Aires de stationnement pour e-bikes ou dispositifs de '
                    . 'micromobilité',
                'description' => "Réalisation et/ou réaménagement des aires de l'entreprise destinées au "
                    . 'stationnement des e-bikes ou dispositifs de micromobilité (trottinettes, hoverboards, '
                    . 'segways, monoroues), y compris râteliers, stations de recharge et/ou mobilier urbain',
            ],
            172 => [ // flotta-bici
                'name' => "3.3.1 MOBILITÉ PARTAGÉE - Flotte d'entreprise de vélos ou de dispositifs de "
                    . 'micromobilité',
                'description' => "Constitution et/ou développement d'une flotte d'entreprise de vélos ou de "
                    . 'dispositifs de micromobilité (trottinettes, hoverboards, segways, monoroues), destinée '
                    . 'aux déplacements professionnels',
            ],
            173 => [ // flotta-ebike
                'name' => "3.3.2 MOBILITÉ PARTAGÉE - Flotte d'entreprise d'e-bikes",
                'description' => "Constitution et/ou développement d'une flotte d'entreprise d'e-bikes, "
                    . 'destinée aux déplacements professionnels',
            ],
            174 => [ // flotta-cargobike
                'name' => "3.3.3 MOBILITÉ PARTAGÉE - Flotte d'entreprise de vélos cargo",
                'description' => "Constitution et/ou développement d'une flotte d'entreprise de vélos cargo, "
                    . 'destinée aux déplacements professionnels',
            ],
            175 => [ // bicimia
                'name' => '3.3.5 MOBILITÉ PARTAGÉE - Conventions',
                'description' => "Mise en place, auprès de l'organisme gestionnaire, de conventions à tarifs "
                    . "préférentiels pour l'achat d'abonnements à des services de vélos partagés et/ou "
                    . "d'autopartage",
            ],
            176 => [ // contributi-bicimia
                'name' => '3.3.6 MOBILITÉ PARTAGÉE - Contributions',
                'description' => "Versement de contributions financières aux salariés pour l'achat "
                    . "d'abonnements à des services de vélos partagés et/ou d'autopartage",
            ],
            177 => [ // altra-asse3
                'name' => '3.5.1 AXE 3 - Autre mesure',
                'description' => "Mise en place d'une autre mesure visant à favoriser la mobilité active "
                    . '(marche, vélo, micromobilité) des salariés',
            ],

            // ASSE 4 - Ridurre ed ottimizzare la domanda di mobilità
            77 => [ // smart-working
                'name' => '4.1.2 ORGANISATION DU TRAVAIL - Télétravail',
                'description' => "Élaboration d'un plan organisationnel et/ou renforcement des outils "
                    . 'informatiques pour favoriser le recours au télétravail',
            ],
            79 => [ // spesa-domicilio
                'name' => '4.2.3 SERVICES ET ÉQUIPEMENTS - Courses et services en entreprise',
                'description' => "Mise en place d'un service de réception de colis et d'articles personnels "
                    . "au sein de l'U.L. et/ou installation de casiers d'entreprise",
            ],
            80 => [ // farmacia
                'name' => '4.2.4 SERVICES ET ÉQUIPEMENTS - Pharmacie en entreprise',
                'description' => "Mise en place d'un service de réception de produits pharmaceutiques au sein "
                    . "de l'U.L. sans coût supplémentaire",
            ],
            81 => [ // lavanderia-azienda
                'name' => '4.2.5 SERVICES ET ÉQUIPEMENTS - Blanchisserie à domicile ou en entreprise',
                'description' => "Mise en place d'un service de réception et de livraison des vêtements au "
                    . "sein de l'U.L. ou à domicile sans coût supplémentaire",
            ],
            82 => [ // asilo-aziendale
                'name' => "4.2.6 SERVICES ET ÉQUIPEMENTS - Crèche d'entreprise",
                'description' => "Mise en place d'un service de crèche d'entreprise réservé aux enfants des "
                    . 'salariés',
            ],
            120 => [ // coworking
                'name' => '4.1.3 ORGANISATION DU TRAVAIL - Partager des espaces (Coworking)',
                'description' => "Offrir une salle de réunion à d'autres travailleurs du réseau, ou permettre "
                    . 'à ses propres salariés de travailler depuis un site proche de leur domicile',
            ],
            178 => [ // flessibilita-oraria
                'name' => '4.1.1 ORGANISATION DU TRAVAIL - Flexibilité horaire',
                'description' => "Extension de la flexibilité des horaires d'arrivée et/ou de départ des "
                    . "salariés, afin de faciliter l'accès aux services de transport public",
            ],
            179 => [ // app-mobilita
                'name' => '4.2.1 SERVICES ET ÉQUIPEMENTS - Plateforme web pour la mobilité des salariés',
                'description' => "Mise en place d'une plateforme / application / section intranet d'entreprise "
                    . "destinée à promouvoir et à faciliter l'accès aux services de mobilité pour les "
                    . 'déplacements domicile-travail des salariés',
            ],
            180 => [ // mensa
                'name' => "4.2.2 SERVICES ET ÉQUIPEMENTS - Cantine d'entreprise",
                'description' => "Mise en place d'un service de cantine d'entreprise, afin de réduire les "
                    . 'déplacements pendant la pause déjeuner',
            ],
            181 => [ // altra-asse4
                'name' => '4.3.1 AXE 4 – Autre mesure',
                'description' => "Mise en place d'une autre mesure visant à réduire et optimiser la demande de "
                    . 'mobilité systématique',
            ],

            // ASSE 5 - Ulteriori misure
            91 => [ // eventi-aziendali
                'name' => "5.1.2 SENSIBILISATION - Événements d'entreprise de promotion de la mobilité "
                    . 'durable',
                'description' => 'Promotion des déplacements par des modes alternatifs à la voiture privée en '
                    . "organisant des événements d'entreprise",
            ],
            94 => [ // informazione-tpl
                'name' => "5.1.1 SENSIBILISATION - Campagnes d'information d'entreprise sur la mobilité "
                    . 'durable',
                'description' => 'Actions de communication et/ou de promotion de la mobilité durable, via les '
                    . "canaux de l'entreprise, mettant en avant l'accessibilité de l'entreprise par des modes "
                    . 'alternatifs à la voiture privée (par ex. transport public, vélo, marche, covoiturage), '
                    . 'les avantages et incitations, les infrastructures disponibles, etc.',
            ],
            182 => [ // eventi-cittadini
                'name' => "5.1.3 SENSIBILISATION - Adhésion / promotion d'initiatives à l'échelle "
                    . 'supra-entreprise',
                'description' => "Adhésion de l'entreprise à des initiatives sur la mobilité durable promues "
                    . 'au niveau public municipal et/ou impliquant plusieurs acteurs (par ex. Semaine '
                    . 'européenne de la mobilité, initiatives BiciBrescia, etc.)',
            ],
            183 => [ // gamification
                'name' => '5.1.4 SENSIBILISATION – Gamification',
                'description' => "Introduction d'initiatives ludiques et compétitives entre les salariés, "
                    . 'pour encourager le recours à des modes de transport durables',
            ],
            184 => [ // altra-asse5
                'name' => '5.2.1 AXE 5 – Autre mesure',
                'description' => "Mise en place d'une initiative spécifique visant à promouvoir la mobilité "
                    . 'durable auprès du personnel salarié',
            ],

            // ASSI (type = 0) - "pillar" nel frontend 5t.vue
            185 => [ // pillar-1
                'name' => "AXE 1 - DÉCOURAGER L'USAGE INDIVIDUEL DE LA VOITURE PRIVÉE",
                'description' => 'Les mesures comprises dans cet axe visent à réduire progressivement '
                    . "l'utilisation du véhicule privé pour les déplacements domicile-travail, en favorisant "
                    . 'des solutions de mobilité plus durables et efficaces. Parmi les actions possibles '
                    . "figurent les politiques de gestion des aires de stationnement de l'entreprise, la "
                    . "rationalisation des parkings et l'introduction d'incitations au partage de la voiture.",
            ],
            186 => [ // pillar-2
                'name' => "AXE 2 – FAVORISER L'USAGE DU TRANSPORT PUBLIC",
                'description' => "Les mesures comprises dans cet axe visent à promouvoir l'utilisation du "
                    . 'transport public, urbain et interurbain, à travers des facilités économiques, des '
                    . "conventions d'entreprise, l'amélioration de l'accessibilité aux arrêts et la diffusion "
                    . "d'abonnements intégrés. L'objectif est de faire du transport public une alternative "
                    . "concrète, pratique et compétitive par rapport à l'usage de la voiture privée.",
            ],
            187 => [ // pillar-3
                'name' => 'AXE 3 – FAVORISER LA MOBILITÉ ACTIVE',
                'description' => 'Les mesures comprises dans cet axe visent à encourager les déplacements à '
                    . 'pied et à vélo, en promouvant la santé, la durabilité et le bien-être. Les actions '
                    . "peuvent inclure l'amélioration des infrastructures cyclables et piétonnes, la mise en "
                    . "place de services d'entreprise en soutien à la pratique du vélo (aires de "
                    . "stationnement, vestiaires, douches), ainsi que des incitations à l'achat ou à "
                    . "l'utilisation de moyens de mobilité active.",
            ],
            188 => [ // pillar-4
                'name' => 'AXE 4 – RÉDUIRE ET OPTIMISER LA DEMANDE DE MOBILITÉ',
                'description' => 'Les mesures comprises dans cet axe visent à diminuer le besoin de '
                    . "déplacements physiques et à améliorer l'efficacité globale des flux de mobilité, grâce "
                    . "à l'adoption d'outils organisationnels et numériques. Parmi les actions prévues "
                    . "figurent le travail à distance, la flexibilité horaire, l'utilisation de plateformes "
                    . "numériques pour la gestion de la mobilité domicile-travail et l'introduction de "
                    . "services ou d'équipements réduisant les déplacements complémentaires.",
            ],
            189 => [ // pillar-5
                'name' => 'AXE 5 – MESURES COMPLÉMENTAIRES',
                'description' => 'Cet axe rassemble les actions transversales de communication, de formation '
                    . "et d'implication du personnel, visant à diffuser une culture d'entreprise orientée "
                    . 'vers la mobilité durable, dans le but de consolider dans le temps un changement '
                    . 'culturel stable et partagé des habitudes de déplacement.',
            ],
        ];

        // Pulisco tutte le righe fr esistenti per il model Measures (idempotenza
        // ampia: vedi ATTENZIONE sopra sul perché qui, a differenza di
        // SectionsI18nSeed, non serve restringere sui foreign_key).
        $this->execute("DELETE FROM i18n WHERE model = 'Measures' AND locale = 'fr'");

        $rows = [];
        foreach ($translations as $foreignKey => $fields) {
            foreach ($fields as $field => $content) {
                $rows[] = [
                    'locale' => 'fr',
                    'model' => 'Measures',
                    'foreign_key' => $foreignKey,
                    'field' => $field,
                    'content' => $content,
                ];
            }
        }

        $this->table('i18n')->insert($rows)->save();
    }
}

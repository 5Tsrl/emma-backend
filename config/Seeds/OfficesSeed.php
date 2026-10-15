<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Offices seed.
 */
class OfficesSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'name' => 'Sede Principale',
                'address' => 'Via Giuseppe Garibaldi 1, Borgo Trento',
                'cap' => '10122',
                'city' => 'Torino',
                'province' => 'TO',
                'company_id' => 1,
                'lat' => 45.0714564,
                'lon' => 7.6842848,
                'company_code' => null,
                'office_code' => 'SEDE',
                'extended_name' => null,
                'office_type' => null,
                'office_code_external' => '11',
                'office_type_extra' => null,
                'survey' => '{"tpl":null,"ztl":null,"gg_SW":null,"gg_TL":null,"mensa":null,"sosta":["Sì, stalli pubblici a raso gratuiti","Sì, stalli pubblici a raso a pagamento"],"turni":[{"id":null,"nome":null,"nr_dipendenti":null,"orario_uscita":null,"orario_ingresso":null}],"ul_cap":"10122","az_auto":null,"az_bici":null,"az_moto":null,"sharing":null,"az_sosta":null,"bacheche":null,"nr_turni":null,"tragitto":null,"coworking":null,"equipaggi":null,"ul_comune":"Torino","az_auto_nr":null,"az_bici_nr":null,"az_moto_nr":null,"az_veicoli":null,"carpooling":null,"spogliatoi":null,"bs_stazioni":[{"id":null,"nome":null,"distanza":null}],"budget_moma":null,"car_sharing":null,"convenzioni":[{"id":null,"nome":null,"nr_persone":null}],"cs_stazioni":[{"id":null,"nome":null,"distanza":null}],"km_percorsi":null,"monopattini":null,"bike_sharing":null,"moto_sharing":null,"nr_coworking":null,"tipo_accordo":null,"ul_indirizzo":"Via Giuseppe Garibaldi 1, Borgo Trento","az_sosta_bici":null,"az_sosta_moto":null,"carpooling_nr":null,"costo_annuale":null,"linee_fermate":[{"id":null,"linea_nome":null,"linea_tipo":null,"fermata_nome":null,"fermata_distanza":null}],"orario_uscita":null,"az_monopattini":null,"carpooling_app":null,"contributo_tpl":null,"localizzazione":null,"altri_incentivi":null,"car_sharing_abb":null,"mensa_frequenza":null,"orario_ingresso":null,"turni_esistenti":null,"ul_sede_filiale":"Sede legale o principale","az_sosta_auto_nr":null,"az_sosta_bici_nr":null,"az_sosta_moto_nr":null,"bike_sharing_abb":null,"car_sharing_tipo":null,"costo_passeggero":null,"moto_sharing_abb":null,"ul_nr_dipendenti":null,"az_auto_contratto":null,"az_bici_contratto":null,"az_monopattini_nr":null,"az_moto_contratto":null,"bike_sharing_tipo":null,"lavoro_flessibile":null,"az_sosta_pagamento":null,"comunicazione_moma":null,"contributo_sharing":null,"sosta_tariffazione":null,"ul_dipendenti_full":null,"ul_dipendenti_part":null,"az_auto_affidamento":null,"az_auto_spostamento":null,"az_bici_affidamento":null,"az_bici_spostamento":null,"az_moto_affidamento":null,"az_moto_spostamento":null,"az_sosta_auto_costo":null,"az_sosta_auto_el_nr":null,"az_sosta_bici_el_nr":null,"az_sosta_carpooling":null,"az_sosta_moto_el_nr":null,"az_sosta_visitatori":null,"comunicazione_altro":null,"contributo_tpl_perc":null,"convenzioni_sharing":null,"orario_flessibilita":null,"trasporto_aziendale":null,"budget_moma_quantita":null,"distribuzione_lavoro":null,"gg_lavoro_flessibile":null,"localizzazione_altro":null,"az_auto_alimentazione":null,"az_bici_alimentazione":null,"az_moto_alimentazione":null,"carpooling_contributi":[{"id":null,"forma":null,"importo":null}],"carpooling_contributo":null,"contributo_auto_green":null,"az_sosta_auto_el_costo":null,"az_sosta_carpooling_nr":null,"az_sosta_visitatori_nr":null,"nr_trasporto_aziendale":null,"comunicazione_frequenza":null,"comunicazione_strumenti":[],"monopattini_sharing_abb":null,"az_monopattini_contratto":null,"orario_flessibilita_info":null,"ul_dipendenti_flessibili":null,"az_monopattini_affidamento":null,"az_monopattini_spostamento":null,"distribuzione_lavoro_altro":null,"az_monopattini_alimentazione":null,"comunicazione_strumenti_altro":null}',
                'label_survey' => null,
                'aggregated_survey' => null,
                'num_employees' => null,
                'email' => null,
                'PSCL' => null,
                'coworking' => 0,
                'pascal_measures' => null,
                'info_coworking' => '[{"link":null,"sale":null,"type":null,"costo":null}]',
                'info_parking' => null,
                'private_coworking' => 0,
                'years' => null,
            ],
            [
                'id' => 2,
                'name' => 'sede seconda',
                'address' => 'Via Giuseppe Garibaldi 1',
                'cap' => '10122',
                'city' => 'Torino',
                'province' => 'TO',
                'company_id' => 1,
                'lat' => 45.0714564,
                'lon' => 7.6842848,
                'company_code' => null,
                'office_code' => '11',
                'extended_name' => null,
                'office_type' => null,
                'office_code_external' => null,
                'office_type_extra' => null,
                'survey' => null,
                'label_survey' => null,
                'aggregated_survey' => null,
                'num_employees' => null,
                'email' => null,
                'PSCL' => null,
                'coworking' => 0,
                'pascal_measures' => null,
                'info_coworking' => '[{"link":null,"sale":null,"type":null,"costo":null}]',
                'info_parking' => null,
                'private_coworking' => 0,
                'years' => null,
            ],
            [
                'id' => 3,
                'name' => 'Sede 2',
                'address' => 'Via Brusiti 13a, Brescia Antica',
                'cap' => '10060',
                'city' => 'San Secondo di Pinerolo',
                'province' => 'TO',
                'company_id' => 1,
                'lat' => 44.863796,
                'lon' => 7.292447,
                'company_code' => null,
                'office_code' => '11',
                'extended_name' => null,
                'office_type' => null,
                'office_code_external' => '13',
                'office_type_extra' => null,
                'survey' => '{"tpl":null,"ztl":null,"gg_SW":null,"gg_TL":null,"mensa":null,"sosta":"No, non sono presenti aree di sosta pubbliche","turni":[{"id":null,"nome":null,"nr_dipendenti":null,"orario_uscita":null,"orario_ingresso":null}],"ul_cap":"10060","az_auto":null,"az_bici":null,"az_moto":null,"sharing":null,"az_sosta":null,"bacheche":null,"nr_turni":null,"tragitto":null,"coworking":null,"equipaggi":null,"ul_comune":"San Secondo di Pinerolo","az_auto_nr":null,"az_bici_nr":null,"az_moto_nr":null,"az_veicoli":null,"carpooling":null,"spogliatoi":null,"bs_stazioni":[{"id":null,"nome":null,"distanza":null}],"budget_moma":null,"car_sharing":null,"convenzioni":[{"id":null,"nome":null,"nr_persone":null}],"cs_stazioni":[{"id":null,"nome":null,"distanza":null}],"km_percorsi":null,"monopattini":null,"bike_sharing":null,"moto_sharing":null,"nr_coworking":null,"tipo_accordo":null,"ul_indirizzo":"Via Brusiti 13a, Brescia Antica","az_sosta_bici":null,"az_sosta_moto":null,"carpooling_nr":null,"costo_annuale":null,"linee_fermate":[{"id":null,"linea_nome":null,"linea_tipo":null,"fermata_nome":null,"fermata_distanza":null}],"orario_uscita":null,"az_monopattini":null,"carpooling_app":null,"contributo_tpl":null,"localizzazione":null,"altri_incentivi":null,"car_sharing_abb":null,"mensa_frequenza":null,"orario_ingresso":null,"turni_esistenti":null,"ul_sede_filiale":null,"az_sosta_auto_nr":null,"az_sosta_bici_nr":null,"az_sosta_moto_nr":null,"bike_sharing_abb":null,"car_sharing_tipo":null,"costo_passeggero":null,"moto_sharing_abb":null,"ul_nr_dipendenti":null,"az_auto_contratto":null,"az_bici_contratto":null,"az_monopattini_nr":null,"az_moto_contratto":null,"bike_sharing_tipo":null,"lavoro_flessibile":null,"az_sosta_pagamento":null,"comunicazione_moma":null,"contributo_sharing":null,"sosta_tariffazione":null,"ul_dipendenti_full":null,"ul_dipendenti_part":null,"az_auto_affidamento":null,"az_auto_spostamento":null,"az_bici_affidamento":null,"az_bici_spostamento":null,"az_moto_affidamento":null,"az_moto_spostamento":null,"az_sosta_auto_costo":null,"az_sosta_auto_el_nr":null,"az_sosta_bici_el_nr":null,"az_sosta_carpooling":null,"az_sosta_moto_el_nr":null,"az_sosta_visitatori":null,"comunicazione_altro":null,"contributo_tpl_perc":null,"convenzioni_sharing":null,"orario_flessibilita":null,"trasporto_aziendale":null,"budget_moma_quantita":null,"distribuzione_lavoro":null,"gg_lavoro_flessibile":null,"localizzazione_altro":null,"az_auto_alimentazione":null,"az_bici_alimentazione":null,"az_moto_alimentazione":null,"carpooling_contributi":[{"id":null,"forma":null,"importo":null}],"carpooling_contributo":null,"contributo_auto_green":null,"az_sosta_auto_el_costo":null,"az_sosta_carpooling_nr":null,"az_sosta_visitatori_nr":null,"nr_trasporto_aziendale":null,"comunicazione_frequenza":null,"comunicazione_strumenti":[],"monopattini_sharing_abb":null,"az_monopattini_contratto":null,"orario_flessibilita_info":null,"ul_dipendenti_flessibili":null,"az_monopattini_affidamento":null,"az_monopattini_spostamento":null,"distribuzione_lavoro_altro":null,"az_monopattini_alimentazione":null,"comunicazione_strumenti_altro":null}',
                'label_survey' => null,
                'aggregated_survey' => null,
                'num_employees' => null,
                'email' => null,
                'PSCL' => null,
                'coworking' => 0,
                'pascal_measures' => null,
                'info_coworking' => '[{"link":null,"sale":null,"type":null,"costo":null}]',
                'info_parking' => null,
                'private_coworking' => 0,
                'years' => null,
            ],
        ];

        $table = $this->table('offices');
        $table->insert($data)->save();
    }
}

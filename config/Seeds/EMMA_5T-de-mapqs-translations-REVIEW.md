# German (de) `mapqs` Map-Question UI-String Translations -- Review

This file lists every DISTINCT Italian source string collected from the `mapqs` UI-string dictionary embedded in `questions.long_description` (question id 340, canonical) and the 9 distinct `questions_surveys.long_description` overrides that diverge from it, alongside its proposed German translation. This dictionary does **not** go through the `i18n` table -- it is read directly by `MapQuestionEdit.vue`/`MapQuestion.vue` at runtime (`mapqs_ori[lang]`). Review this BEFORE writing `mapqs.de` back into the DB (a separate merge mechanism is needed; this file only produces the data).

Register: formal **Sie**, matching the existing `EMMA_5T-de-questions-translations-REVIEW.md` convention (`survey-defaults.js`'s `welcome_de` is also formal). Rows flagged with ⚠️ were genuinely ambiguous, incomplete fragments, or required a judgment call because the source content didn't match its own key name.

**Rows** = `questions.340` (canonical, 53 keys) + `questions_surveys` ids `42002, 68184, 94389` (byte-identical or functionally-identical to canonical), `86900, 95195` (school/`casa-scuola` variant of canonical), `71429, 88635` (canonical structure with anomalous `privacy_que`/`unavalible_adress` content), `64095` (school 59-key variant with extra `type_bus`/`type_walking`/`meansOfTransportSchool` keys), `65147` (work 59-key variant, same shape as 64095).

## Nested-object KEYS left untouched (values only were translated)
`type_auto`, `type_bike`, `type_mono`, `type_moto`, `type_train`/`type_treno`, `type_Personal`/`type_personale`, `type_bus`, `type_walking`, `meansOfTransport` -- all internal keys (`Taxi`, `Personal`/`Personale`, `Car pooling`, `Car sharing`, `company_car`, `Bike sharing`, `Scooter sharing`/`Monopattino sharing`, `Moto sharing`, `bikebus`, `Conducive`/`Passenger`, `Conducente`/`Passeggero`, `tpl`, `Schoolbus`, `walking`, `walkingbus`, `Auto`, `Bike`/`Bici`, `Moto`, `Metro`, `Train`/`Treno`, `On_foot`/`Piedi`, `Scooter`/`Monopattino`, `Bus/Tram`, the two `Servizio ferroviario ...` train-service names) are canonical matching identifiers and were preserved exactly. The `enabled` sibling key (6 booleans) was not touched at all.

## Scalar / top-level strings

| Italian | German | Key | Used by |
|---|---|---|---|
| ⚠️ Fai click sul | ⚠️ Klicken Sie auf das | `do` | all 10 rows (incomplete fragment, paired with an icon in the UI) |
| Cap | PLZ | `Cap` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Elimina questo mezzo | Dieses Verkehrsmittel löschen | `del` | all 10 rows |
| Indietro | Zurück | `back` | all 10 rows |
| Città | Stadt | `city` | all 10 rows |
| ⚠️ mezzo che usi uscendo da casa | ⚠️ Verkehrsmittel, das Sie beim Verlassen Ihres Hauses benutzen | `mean` | all 10 rows (fragment, no article/verb -- likely concatenated in the UI with a preceding number/label) |
| PROSSIMO | WEITER | `next` (canonical rows) / `PROSSIMO` (64095/65147) | all 10 rows |
| Avanti | Weiter | `next` | 64095, 65147 |
| Clicca qui per localizzarti sulla mappa | Klicken Sie hier, um sich auf der Karte zu lokalisieren | `click` | all 10 rows |
| per almeno 500 metri | für mindestens 500 Meter | `meters` | all 10 rows |
| Indirizzo | Adresse | `address` (canonical) / `Indirizzo` (64095/65147) | all 10 rows |
| Qual è la cilindrata della tua auto? | Wie hoch ist der Hubraum Ihres Autos? | `cc_auto` | all 10 rows |
| Qual è la cilindrata della tua moto o ciclomotore? | Wie hoch ist der Hubraum Ihres Motorrads oder Mopeds? | `cc_moto` | all 10 rows |
| Provincia | Provinz | `province` | all 10 rows |
| Ricerca in corso ... | Suche läuft ... | `research` | all 10 rows |
| Mettere un costo maggiore di 0 e minore di 100000. | Geben Sie einen Kostenwert größer als 0 und kleiner als 100000 ein. | `set_cost` | all 10 rows |
| A quale classe ambientale appartiene la tua auto? | Welcher Umweltklasse gehört Ihr Auto an? | `amb_class` | all 10 rows |
| Usi un altro mezzo? Aggiungilo | Nutzen Sie ein anderes Verkehrsmittel? Fügen Sie es hinzu | `but_descr` | all 10 rows |
| Inserisci un Cap, es. 10036 | Geben Sie eine Postleitzahl ein, z. B. 10036 | `insert_Cap` / `Inserisci_Cap` | all 10 rows |
| Mezzo | Verkehrsmittel | `mean_label` | all 10 rows |
| Quanto spendi al mese (A/R)? | Wie viel geben Sie monatlich aus (Hin- und Rückfahrt)? | `which_cost` | canonical + 42002, 68184, 71429, 86900, 88635, 95195, 64095, 65147 |
| Quanto spendi al mese (andata e ritorno)? | Wie viel geben Sie monatlich aus (Hin- und Rückfahrt)? | `which_cost` | 94389 (same meaning, spelled-out wording; DE output identical to canonical's) |
| Qual è l'alimentazione della tua auto? | Welche Antriebsart hat Ihr Auto? | `car_fueling` | all 10 rows |
| Inserisci una città, es. Settimo Torinese | Geben Sie eine Stadt ein, z. B. Settimo Torinese | `insert_city` | all 10 rows |
| solo città e provincia. | nur Stadt und Provinz. | `only_ci_pro` | all 10 rows |
| Dubbi sulla privacy? ... dispone già. ... casa-lavoro ... | Bedenken hinsichtlich des Datenschutzes? Sie geben keine vertraulichen Informationen preis. Die Adresse ist Ihrem Arbeitgeber bereits bekannt. Sie wird nur für Zwecke im Zusammenhang mit dem Arbeitsweg verwendet und nicht an Dritte weitergegeben, es sei denn in anonymer Form. | `privacy_que` | canonical, 42002, 68184, 94389, 64095 |
| Dubbi sulla privacy? ... la scuola dispone già. ... casa-scuola ... | Bedenken hinsichtlich des Datenschutzes? Sie geben keine vertraulichen Informationen preis. Die Adresse ist der Schule bereits bekannt. Sie wird nur für Zwecke im Zusammenhang mit dem Schulweg verwendet und nicht an Dritte weitergegeben, es sei denn in anonymer Form. | `privacy_que` | 86900, 95195 (school variant) |
| ⚠️ *(empty string)* | ⚠️ *(kept empty)* | `privacy_que` | 71429 -- source value is `""`; nothing to translate, left empty |
| ⚠️ Se l'indirizzo non viene riconosciuto correttamente puoi aggiustare la posizione facendo click sulla mappa. | ⚠️ Wenn die Adresse nicht korrekt erkannt wird, können Sie die Position durch Klicken auf die Karte anpassen. | `unavalible_adress` (canonical/42002/68184/86900/94389/95195/64095) **and** `privacy_que` (88635, mislabeled) | see note: same Italian phrase appears under two DIFFERENT keys depending on row |
| Scegli il tipo di auto? | Welchen Autotyp wählen Sie? | `select_auto` / `sel_type_auto` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Scegli il tipo di auto | Welchen Autotyp wählen Sie | `select_auto` / `sel_type_auto` | 64095, 65147 (no trailing `?`) |
| Quali mezzi usi? | Welche Verkehrsmittel nutzen Sie? | `which_modes` | all 10 rows |
| 1. Da dove parti per venire al lavoro? | 1. Von wo aus starten Sie, um zur Arbeit zu kommen? | `leavingpoint` (canonical) / `dadoveparti` (65147) | canonical, 42002, 68184, 71429, 88635, 94389, 65147 |
| 1. Da dove parti per venire a scuola? | 1. Von wo aus starten Sie, um zur Schule zu kommen? | `leavingpoint` | 86900, 95195 |
| 1. Da dove parti per andare a scuola? | 1. Von wo aus starten Sie, um zur Schule zu gehen? | `dadoveparti` | 64095 |
| Qual è l'alimentazione della tua moto o ciclomotore? | Welche Antriebsart hat Ihr Motorrad oder Moped? | `moto_fueling` | all 10 rows |
| Impostare una distanza maggiore di 0 e minore di 100000. | Geben Sie eine Distanz größer als 0 und kleiner als 100000 ein. | `set_distance` | all 10 rows |
| Salva e Continua | Speichern und Fortfahren | `next_continue` | all 10 rows |
| Scegli il tipo di Bici? | Welchen Fahrradtyp wählen Sie? | `sel_type_bike` | all 10 rows |
| Scegli il tipo di Monopattino? | Welchen Scooter-Typ wählen Sie? | `sel_type_mono` | all 10 rows |
| Scegli il tipo di moto? | Welchen Motorradtyp wählen Sie? | `sel_type_moto` | all 10 rows |
| Inserisci un indirizzo, es. Via Roma 20, Settimo Torinese | Geben Sie eine Adresse ein, z. B. Via Roma 20, Settimo Torinese | `Insert_address` / `Inserisci_indirizzo` | all 10 rows |
| A quale classe ambientale appartiene la tua moto? | Welcher Umweltklasse gehört Ihr Motorrad an? | `amb_class_moto` | all 10 rows |
| Inserisci l'indirizzo da cui parti più di frequente, anche se non è la residenza o il domicilio | Geben Sie die Adresse ein, von der aus Sie am häufigsten losfahren, auch wenn es sich nicht um Ihren Wohnsitz oder Aufenthaltsort handelt | `insert_address` | all 10 rows |
| Scegli il tipo di Treno? | Welchen Zugtyp wählen Sie? | `sel_type_train` | all 10 rows |
| Quanta distanza percorri con questo mezzo (A/R) ? | Wie viel Strecke legen Sie mit diesem Verkehrsmittel zurück (Hin- und Rückfahrt)? | `which_distance` | canonical + 42002, 68184, 71429, 86900, 88635, 95195, 64095, 65147 |
| Quanta distanza percorri con questo mezzo (andata e ritorno) ? | Wie viel Strecke legen Sie mit diesem Verkehrsmittel zurück (Hin- und Rückfahrt)? | `which_distance` | 94389 (same meaning, spelled-out wording) |
| TO | TO | `insert_province` | all 53-key rows (province code, left unchanged -- not translatable) |
| Impossibile trovare questo indirizzo, prova ad inserire | Diese Adresse konnte nicht gefunden werden, versuchen Sie es mit | `impossible_search` | all 10 rows |
| ⚠️ Scegli se sei conducente oppure passaggero? | ⚠️ Wählen Sie, ob Sie Fahrer oder Beifahrer sind? | `sel_type_personal` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 (source has a typo, "passaggero", and a stray `?` after an indirect question) |
| Scegli se sei conducente oppure passeggero | Wählen Sie, ob Sie Fahrer oder Beifahrer sind | `sel_type_personale` | 64095, 65147 |
| ⚠️ Indica origine e destinazione del tuo spostamento ed i tuoi mezzi invernali. Indica per primo il mezzo prevalente. ... | ⚠️ Geben Sie Start- und Zielort Ihres Arbeitswegs sowie Ihre im Winter genutzten Verkehrsmittel an. Nennen Sie zuerst das überwiegend genutzte Verkehrsmittel. Die Angabe wird nur für Zwecke im Zusammenhang mit dem Arbeitsweg verwendet und nicht an Dritte weitergegeben, es sei denn in anonymer Form. | `unavalible_adress` | 71429 (mentions "mezzi invernali" / winter transport modes -- out of place for a general map question, possibly copy-pasted from a seasonal survey) |
| ⚠️ Indica l'origine del tuo spostamento; verrà usata solo per questioni legate agli spostamenti casa-lavoro ... La risposta è facoltativa | ⚠️ Geben Sie den Ausgangsort Ihres Arbeitswegs an; er wird nur für Zwecke im Zusammenhang mit dem Arbeitsweg verwendet und nicht an Dritte weitergegeben, es sei denn in anonymer Form. Die Angabe ist freiwillig. | `unavalible_adress` | 88635 (holds the usual `privacy_que` topic instead) |
| Domanda obbligatoria | Pflichtfrage | `Mandatory_question` / `Domanda_obbligatoria` | all 10 rows |
| In quale sede/azienda inizi il lavoro più di frequente? | In welchem Büro/Unternehmen beginnen Sie am häufigsten mit der Arbeit? | `step_which_company` | canonical, 42002, 68184, 71429, 88635, 94389, 65147 |
| In quale scuola ti rechi? | Welche Schule besuchen Sie? | `step_which_company` | 64095 |
| ⚠️ In quale plesso frequenti scuola? | ⚠️ Welchen Schulkomplex besuchen Sie? | `step_which_company` | 95195 (awkward source grammar, "frequenti scuola" instead of "frequenti a scuola"; "plesso" = school building/campus, translation is a best-effort equivalent) |

## Nested-dictionary VALUES (keys unchanged)

| Italian value | German value | Nested key (preserved) | Used by |
|---|---|---|---|
| Taxi | Taxi | `type_auto.Taxi` | all 10 rows |
| Personale (canonical `Personal` key) | Privat | `type_auto.Personal` / `type_bike.Personal` / `type_mono.Personal` / `type_moto.Personal` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Personale (64095/65147 `Personale` key) | Privat | `type_auto.Personale` / `type_mono.Personale` / `type_moto.Personale` | 64095, 65147 |
| Car pooling | Car pooling (loanword, unchanged like IT/EN) | `type_auto` | all 10 rows |
| Car sharing | Car sharing (loanword, unchanged like IT/EN) | `type_auto` | all 10 rows |
| Auto aziendale | Firmenwagen | `type_auto.company_car` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Bike sharing | Bike sharing (loanword, unchanged like IT/EN) | `type_bike."Bike sharing"` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Bici propria | Eigenes Fahrrad | `type_bike.Personale` | 64095, 65147 |
| Bici condivisa (bike sharing) | Geteiltes Fahrrad (Bike Sharing) | `type_bike."Bike sharing"` | 64095, 65147 |
| Bicibus | Bicibus (loanword, unchanged like IT/EN) | `type_bike.bikebus` | 64095, 65147 |
| Monopattino sharing | Scooter-Sharing | `type_mono."Scooter sharing"` (canonical) / `type_mono."Monopattino sharing"` (64095/65147) | all 10 rows |
| Moto sharing | Moto sharing (loanword, unchanged like IT/EN) | `type_moto."Moto sharing"` | all 10 rows |
| Servizio ferroviario Trenitalia/GTT | Bahnservice von Trenitalia/GTT | `type_train."Servizio ferroviario Trenitalia/GTT"` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...) | Bahnservice anderer Anbieter (Trenord, Italo, Hochgeschwindigkeitszüge, ...) | `type_train."Servizio ferroviario altri vettori..."` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Servizio ferroviario Trenitalia | Bahnservice von Trenitalia | `type_treno."Servizio ferroviario Trenitalia/GTT"` | 64095, 65147 |
| Servizio ferroviario altri vettori (Italo, Alta Velocità) | Bahnservice anderer Anbieter (Italo, Hochgeschwindigkeitszüge) | `type_treno."Servizio ferroviario altri vettori..."` | 64095, 65147 |
| Personale come conducente | Privat als Fahrer | `type_Personal.Conducive` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Personale come passeggero | Privat als Beifahrer | `type_Personal.Passenger` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Conducente | Fahrer | `type_personale.Conducente` | 64095, 65147 |
| Passeggero | Beifahrer | `type_personale.Passeggero` | 64095, 65147 |
| TPL | TPL (loanword/acronym, unchanged) | `type_bus.tpl` | 64095, 65147 |
| Scuaolabus *(source typo for "Scuolabus")* | Schulbus | `type_bus.Schoolbus` | 64095, 65147 |
| Piedi (da solo o accompagnato) | Zu Fuß (allein oder in Begleitung) | `type_walking.walking` | 64095, 65147 |
| pedibus | Pedibus (loanword, capitalized) | `type_walking.walkingbus` | 64095, 65147 |
| Auto (canonical `Auto` key) | Auto | `meansOfTransport.Auto` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Bici (canonical `Bike` key) | Fahrrad | `meansOfTransport.Bike` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Moto (`Moto` key) | Motorrad | `meansOfTransport.Moto` | all 10 rows |
| Metro (`Metro` key) | Metro (loanword, unchanged) | `meansOfTransport.Metro` | all 10 rows |
| Treno (canonical `Train` key) | Zug | `meansOfTransport.Train` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Piedi (canonical `On_foot` key) | Zu Fuß | `meansOfTransport.On_foot` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Monopattino (canonical `Scooter` key) | Scooter | `meansOfTransport.Scooter` | canonical + 42002, 68184, 71429, 86900, 88635, 94389, 95195 |
| Bus/Tram | Bus/Tram (loanword, unchanged) | `meansOfTransport."Bus/Tram"` | all 10 rows |
| in Auto (64095/65147 `Auto` key) | Mit dem Auto | `meansOfTransport.Auto` | 64095, 65147 |
| In Bicicletta (64095/65147 `Bici` key) | Mit dem Fahrrad | `meansOfTransport.Bici` | 64095, 65147 |
| A piedi (64095/65147 `Piedi` key) | Zu Fuß | `meansOfTransport.Piedi` | 64095, 65147 |
| Monopattino (64095/65147 `Monopattino` key) | Scooter | `meansOfTransport.Monopattino` | 64095, 65147 |

## `stepStrings` array (order and length preserved, 10 elements)

| Italian | German |
|---|---|
| PRIMO / FIRST | ERSTER |
| SECONDO / SECOND | ZWEITER |
| TERZO / THIRD | DRITTER |
| QUARTO / FOURTH | VIERTER |
| QUINTO / FIFTH | FÜNFTER |
| SESTO / SIXTH | SECHSTER |
| SETTIMO / SEVENTH | SIEBTER |
| OTTAVO / EIGHTH | ACHTER |
| NONO / NINTH | NEUNTER |
| DECIMO / TENTH | ZEHNTER |

Used by all 10 rows.

## `meansOfTransportSchool` array (64095, 65147 only)

| Field | Decision |
|---|---|
| `icon` (`left`, `name`, `unicode`) | Left UNCHANGED -- technical icon configuration, not language text. |
| `name` (`"Auto"`, `"Bici"`) | Left UNCHANGED -- matches `meansOfTransport`'s canonical identifiers. |
| ⚠️ `label` (`"Auto School"`, `"Bici school"`) | Left UNCHANGED. The `it` and `en` source blocks use the exact same string for both languages ("Auto School" is not real Italian or English) -- this looks like dead/legacy data rather than genuine UI copy, so no German translation was invented for it. |

## Summary

- 71 distinct (key, Italian-value) pairs were translated across scalar strings, nested-dictionary values, and the `stepStrings` array (10 ordinal words counted as one array unit above); the `meansOfTransportSchool.label` pair was intentionally left untouched rather than translated (see above).
- 9 rows/entries flagged ⚠️: `do`, `mean`, `unavalible_adress`/`privacy_que` cross-key duplicate, `sel_type_personal`, 71429's `privacy_que` (empty) + `unavalible_adress` (winter-mode content), 88635's `unavalible_adress` (swapped content), 95195's `step_which_company` ("plesso"), and `meansOfTransportSchool.label`.

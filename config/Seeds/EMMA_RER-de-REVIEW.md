# EMMA RER — German (de) translations: review

Database `emma_rer` (rerfe.drupalvm.test). Files: `EMMA_RER-de-questions-translations-canonical.json`, `EMMA_RER-de-questions-translations-overrides.json`, `EMMA_RER-de-mapqs-translations.json`.

## Summary

- Canonical questions with German: **361**; per-survey override rows: **67**.
- German **reused from the 5T review** where the Italian text (and options) is identical; **newly translated**: 212 distinct strings + 14 array group labels/options.
- Every entry carries `_it` (the Italian description it was written for). The import command skips a row whose `_it` differs from the database, and skips rows whose id does not exist.
- Acceptance: `HTTP_HOST=rerfe.drupalvm.test ./bin/cake import_questions_translations <file> --locale de --dry-run` and `import_mapqs_translations` give 0 failed and 0 id mismatches.

## Needs a native-speaker review

- Machine-translated, not human-reviewed. Test/junk source strings (e.g. "test", "PW ...") were left as-is or lightly translated.
- Place, company and address names inside option lists were intentionally left in Italian.

## New translations — Question text (120)

| Italian | German |
|---|---|
| % AUTO | % AUTO |
| % BICI | % FAHRRAD |
| A quali condizioni saresti disponibile a cambiare le tue abitudini per gli spostamenti di servizio? | Unter welchen Bedingungen wären Sie bereit, Ihre Gewohnheiten bei dienstlichen Fahrten zu ändern? |
| A quali condizioni saresti disposta/o a condividere il viaggio in auto con i colleghi (car pooling) per lo spostamento casa-lavoro almeno 3 giorni alla settimana? | Unter welchen Bedingungen wären Sie bereit, für die Fahrt von zu Hause zur Arbeit an mindestens 3 Tagen pro Woche die Autofahrt mit Kolleginnen und Kollegen zu teilen (Carpooling)? |
| A quali condizioni saresti disposta/o ad utilizzare il trasporto pubblico (TPL) per lo spostamento casa-lavoro per almeno 3 giorni alla settimana? | Unter welchen Bedingungen wären Sie bereit, für die Fahrt von zu Hause zur Arbeit an mindestens 3 Tagen pro Woche den öffentlichen Nahverkehr (TPL) zu nutzen? |
| A quali condizioni saresti disposta/o ad utilizzare la bicicletta per lo spostamento casa-lavoro per almeno 3 giorni alla settimana? | Unter welchen Bedingungen wären Sie bereit, für die Fahrt von zu Hause zur Arbeit an mindestens 3 Tagen pro Woche das Fahrrad zu nutzen? |
| A quali condizioni saresti interessato ad un servizio di navetta aziendale? | Unter welchen Bedingungen wären Sie an einem Firmenshuttle-Dienst interessiert? |
| A quali iniziative saresti interessato/a? | An welchen Initiativen wären Sie interessiert? |
| Abitualmente a che ora arrivi al lavoro? | Um wie viel Uhr kommen Sie üblicherweise zur Arbeit? |
| Abitualmente a che ora esci dal lavoro? | Um wie viel Uhr verlassen Sie üblicherweise die Arbeit? |
| Abitualmente, con quale frequenza ti rechi al lavoro presso la sede indicata? | Wie häufig fahren Sie üblicherweise zum angegebenen Standort zur Arbeit? |
| Abitualmente, durante la pausa pranzo: | In der Regel, während der Mittagspause: |
| Abitualmente, fai delle tappe intermedie quando torni dal lavoro (es. gestione dei figli, commissioni, impegni ecc.)? | Machen Sie üblicherweise Zwischenstopps auf dem Heimweg von der Arbeit (z. B. Kinderbetreuung, Besorgungen, Verpflichtungen usw.)? |
| Abitualmente, fai delle tappe intermedie quando vai al lavoro (es. gestione dei figli, commissioni, impegni ecc.)? | Machen Sie üblicherweise Zwischenstopps auf dem Weg zur Arbeit (z. B. Kinderbetreuung, Besorgungen, Verpflichtungen usw.)? |
| Abitualmente, quanto tempo impieghi per recarti al lavoro presso la sede indicata? | Wie viel Zeit benötigen Sie üblicherweise, um zur Arbeit am angegebenen Standort zu gelangen? |
| Altro [%] | Sonstiges [%] |
| Auto come conducente o moto | Auto als Fahrer/in oder Motorrad |
| Auto come passeggero o in carpooling [%] | Auto als Beifahrer/in oder im Carpooling [%] |
| Auto o moto + TPL | Auto oder Motorrad + ÖPNV |
| Con che frequenza, abitualmente, ricorri allo Smart Working? | Wie oft greifen Sie üblicherweise auf Smart Working zurück? |
| Con quale frequenza ti capita di spostarti per motivi di servizio? | Wie häufig kommt es vor, dass Sie aus dienstlichen Gründen unterwegs sind? |
| Con quale modalità ti sposti prevalentemente durante gli spostamenti di servizio? | Mit welcher Fortbewegungsart sind Sie bei Dienstfahrten überwiegend unterwegs? |
| Con quale modalità ti sposti prevalentemente durante la pausa pranzo? | Mit welcher Fortbewegungsart sind Sie während der Mittagspause überwiegend unterwegs? |
| Dove parcheggi abitualmente? | Wo parken Sie üblicherweise? |
| Dove ti rechi più frequentemente per motivi di servizio? | Wohin fahren Sie am häufigsten aus dienstlichen Gründen? |
| Fascia d'età | Altersgruppe |
| Hai idee o suggerimenti? | Haben Sie Ideen oder Vorschläge? |
| Il tuo spostamento abituale casa-lavoro | Ihre übliche Fahrt zwischen Wohnort und Arbeitsplatz |
| In estate e inverno, utilizzi le stesse modalità di trasporto per recarti al lavoro? | Nutzen Sie im Sommer und im Winter dieselben Verkehrsmittel für die Fahrt zur Arbeit? |
| Indica le iniziative di Mobility Management di tuo interesse | Geben Sie die Mobility-Management-Initiativen an, die Sie interessieren |
| Iniziative e proposte | Initiativen und Vorschläge |
| Navetta [%] | Shuttle [%] |
| Numero di dipendenti rispondenti | Anzahl der antwortenden Beschäftigten |
| PW Domanda 1 | PW Frage 1 |
| PW Domanda 2 | PW Frage 2 |
| PW Domanda 3 | PW Frage 3 |
| PW Domanda 4 | PW Frage 4 |
| PW Domanda existing likert 1787124521138 | PW Frage vorhandenes Likert 1787124521138 |
| PW Domanda existing likert 1787256211131 | PW Frage vorhandenes Likert 1787256211131 |
| PW Domanda existing likert 1787257834906 | PW Frage vorhandenes Likert 1787257834906 |
| PW Domanda existing map-gal 1787124521138 | PW Frage vorhandene map-gal 1787124521138 |
| PW Domanda existing map-gal 1787256211131 | PW Frage vorhandene map-gal 1787256211131 |
| PW Domanda existing map-gal 1787257834906 | PW Frage vorhandene map-gal 1787257834906 |
| PW Domanda existing multiple 1787124521138 | PW Frage vorhandene Mehrfachauswahl 1787124521138 |
| PW Domanda existing multiple 1787256211131 | PW Frage vorhandene Mehrfachauswahl 1787256211131 |
| PW Domanda existing multiple 1787257834906 | PW Frage vorhandene Mehrfachauswahl 1787257834906 |
| PW Domanda existing number 1787124521138 | PW Frage vorhandene Zahl 1787124521138 |
| PW Domanda existing number 1787256211131 | PW Frage vorhandene Zahl 1787256211131 |
| PW Domanda existing number 1787257834906 | PW Frage vorhandene Zahl 1787257834906 |
| PW Domanda existing single 1787124521138 | PW Frage vorhandene Einzelauswahl 1787124521138 |
| PW Domanda existing single 1787256211131 | PW Frage vorhandene Einzelauswahl 1787256211131 |
| PW Domanda existing single 1787257834906 | PW Frage vorhandene Einzelauswahl 1787257834906 |
| PW Domanda existing text 1787124521138 | PW Frage vorhandener Text 1787124521138 |
| PW Domanda existing text 1787256211131 | PW Frage vorhandener Text 1787256211131 |
| PW Domanda existing text 1787257834906 | PW Frage vorhandener Text 1787257834906 |
| PW Domanda likert 1787124582053 | PW Frage Likert 1787124582053 |
| PW Domanda likert 1787256275618 | PW Frage Likert 1787256275618 |
| PW Domanda likert 1787257900272 | PW Frage Likert 1787257900272 |
| PW Domanda map-gal 1787124582053 | PW Frage map-gal 1787124582053 |
| PW Domanda map-gal 1787256275618 | PW Frage map-gal 1787256275618 |
| PW Domanda map-gal 1787257900272 | PW Frage map-gal 1787257900272 |
| PW Domanda multiple 1787124582053 | PW Frage Mehrfachauswahl 1787124582053 |
| PW Domanda multiple 1787256275618 | PW Frage Mehrfachauswahl 1787256275618 |
| PW Domanda multiple 1787257900272 | PW Frage Mehrfachauswahl 1787257900272 |
| PW Domanda number 1787124582053 | PW Frage Zahl 1787124582053 |
| PW Domanda number 1787256275618 | PW Frage Zahl 1787256275618 |
| PW Domanda number 1787257900272 | PW Frage Zahl 1787257900272 |
| PW Domanda single 1787124582053 | PW Frage Einzelauswahl 1787124582053 |
| PW Domanda single 1787256275618 | PW Frage Einzelauswahl 1787256275618 |
| PW Domanda single 1787257900272 | PW Frage Einzelauswahl 1787257900272 |
| PW Domanda text 1787124582053 | PW Frage Text 1787124582053 |
| PW Domanda text 1787256275618 | PW Frage Text 1787256275618 |
| PW Domanda text 1787257900272 | PW Frage Text 1787257900272 |
| Perché scegli il motociclo? | Warum wählen Sie das Motorrad? |
| Perché scegli l’autovettura? | Warum wählen Sie das Auto? |
| Perché scegli questa modalità di spostamento? | Warum wählen Sie diese Art der Fortbewegung? |
| Perché scegli questa modalità per gli spostamenti di servizio? | Warum wählen Sie diese Fortbewegungsart für Dienstfahrten? |
| Perché scegli queste modalità di spostamento? | Warum wählen Sie diese Arten der Fortbewegung? |
| Propensione al cambiamento - A piedi [%] | Veränderungsbereitschaft - Zu Fuß [%] |
| Propensione al cambiamento - Bici, monopattino o bike sharing [%] | Veränderungsbereitschaft - Fahrrad, E-Scooter oder Bikesharing [%] |
| Propensione al cambiamento - Carpooling [%] | Veränderungsbereitschaft - Carpooling [%] |
| Propensione al cambiamento - Intermodalità [%] | Veränderungsbereitschaft - Intermodalität [%] |
| Propensione al cambiamento - Navetta aziendale [%] | Veränderungsbereitschaft - Firmenshuttle [%] |
| Propensione al cambiamento - Trasporto pubblico [%] | Veränderungsbereitschaft - Öffentliche Verkehrsmittel [%] |
| Propensione al cambiamento2 | Veränderungsbereitschaft2 |
| Qual è la propensione ad utilizzare un monopattino elettrico per recarsi nel luogo di lavoro? | Wie hoch ist die Bereitschaft, einen E-Scooter für den Weg zum Arbeitsplatz zu nutzen? |
| Qual'è il tuo inquadramento? | Welche berufliche Einstufung haben Sie? |
| Quale mezzo utilizzi prevalentemente per recarti al lavoro | Welches Verkehrsmittel nutzen Sie überwiegend, um zur Arbeit zu gelangen |
| Quali modalità alternative utilizzeresti per lo spostamento casa-lavoro? | Welche alternativen Fortbewegungsarten würden Sie für die Fahrt zwischen Wohnort und Arbeitsplatz nutzen? |
| Quali sono le criticità che riscontri durante il tuo tragitto abituale casa-lavoro? | Welche Probleme stellen Sie auf Ihrer üblichen Strecke zwischen Wohnort und Arbeitsplatz fest? |
| Quando c’è il sole o fa caldo, come ti sposti abitualmente? | Wie fahren Sie üblicherweise, wenn die Sonne scheint oder es heiß ist? |
| Quando piove o fa freddo, come ti sposti abitualmente? | Wie fahren Sie üblicherweise, wenn es regnet oder kalt ist? |
| Quanto conosci l’offerta di trasporto pubblico cittadino? | Wie gut kennen Sie das Angebot des städtischen öffentlichen Verkehrs? |
| Quanto tempo impieghi a trovare parcheggio e a raggiungere poi la sede di lavoro? | Wie viel Zeit benötigen Sie, um einen Parkplatz zu finden und anschließend den Arbeitsplatz zu erreichen? |
| Quanto ti ritieni soddisfatto delle tue attuali abitudini di viaggio? | Wie zufrieden sind Sie mit Ihren derzeitigen Reisegewohnheiten? |
| SPLIT MODALE - A piedi (> 5 minuti) [%] | MODAL SPLIT - Zu Fuß (> 5 Minuten) [%] |
| SPLIT MODALE - Bici o monopattino [%] | MODAL SPLIT - Fahrrad oder E-Scooter [%] |
| Saresti disponibile a rinunciare all’autovettura o al motociclo per lo spostamento casa-lavoro per almeno 3 giorni alla settimana? | Wären Sie bereit, für die Fahrt von zu Hause zur Arbeit an mindestens 3 Tagen pro Woche auf das Auto oder Motorrad zu verzichten? |
| Scegli Il Tipo Di Bici (Muscolare O E-Bike) | Wählen Sie die Art des Fahrrads (Muskelkraft oder E-Bike) |
| Scegli il tipo di mezzo | Wählen Sie die Art des Verkehrsmittels |
| Sei dipendente dell'azienda per cui stai compilando il questionario? | Sind Sie Angestellte/r des Unternehmens, für das Sie den Fragebogen ausfüllen? |
| Sei dipendente dell’azienda per cui stai compilando il questionario? | Sind Sie Angestellte/r des Unternehmens, für das Sie den Fragebogen ausfüllen? |
| Sezione Test Automatico DA CANCELLARE A 1787124548331 | Abschnitt Automatischer Test ZU LÖSCHEN A 1787124548331 |
| Sezione Test Automatico DA CANCELLARE A 1787256238223 | Abschnitt Automatischer Test ZU LÖSCHEN A 1787256238223 |
| Sezione Test Automatico DA CANCELLARE A 1787257861368 | Abschnitt Automatischer Test ZU LÖSCHEN A 1787257861368 |
| Sezione Test Automatico DA CANCELLARE A 1789400772476 | Abschnitt Automatischer Test ZU LÖSCHEN A 1789400772476 |
| Sezione Test Automatico DA CANCELLARE B 1787124548331 | Abschnitt Automatischer Test ZU LÖSCHEN B 1787124548331 |
| Sezione Test Automatico DA CANCELLARE B 1787256238223 | Abschnitt Automatischer Test ZU LÖSCHEN B 1787256238223 |
| Sezione Test Automatico DA CANCELLARE B 1787257861368 | Abschnitt Automatischer Test ZU LÖSCHEN B 1787257861368 |
| Sezione Test Automatico DA CANCELLARE B 1789400772476 | Abschnitt Automatischer Test ZU LÖSCHEN B 1789400772476 |
| Somministrazione del questionario al personale dipendente | Durchführung des Fragebogens beim angestellten Personal |
| Spostamenti di servizio | Dienstfahrten |
| Spostamenti in inverno ed estate | Fahrten im Winter und im Sommer |
| Tipologia di orario | Art der Arbeitszeit |
| Trasporto pubblico [%] | Öffentliche Verkehrsmittel [%] |
| doamnda numero | Frage Nummer |
| domanda likert | Likert-Frage |
| i tuoi spostamenti su mappa (gal) | Ihre Fahrten auf der Karte (gal) |
| mezzo utilizzato frequententemente | Häufig genutztes Verkehrsmittel |
| test | Test |

## New translations — Long description (21)

| Italian | German |
|---|---|
| Cosa comprende: Auto come conducente / moto Auto come conducente / moto + Bici / monopattino / bike sharing | Was umfasst: Auto als Fahrer / Motorrad Auto als Fahrer / Motorrad + Fahrrad / E-Scooter / Bikesharing |
| Cosa comprende: Auto come passeggero o in carpooling & Auto come passeggero o in carpooling + Bici / monopattino / bike sharing | Was umfasst: Auto als Beifahrer oder im Carpooling & Auto als Beifahrer oder im Carpooling + Fahrrad / E-Scooter / Bikesharing |
| Cosa comprende: Bici / monopattino / bike sharing | Was umfasst: Fahrrad / E-Scooter / Bikesharing |
| Cosa comprende: Car sharing & Taxi & Bici / Auto / TPL + Car sharing / Taxi | Was umfasst: Carsharing & Taxi & Fahrrad / Auto / ÖPNV + Carsharing / Taxi |
| Cosa comprende:  Autobus / Metropolitana / Tram / Treno & combinazione mezzi TPL & TPL + Bici / monopattino / bike sharing | Was umfasst:  Bus / U-Bahn / Straßenbahn / Zug & Kombination von ÖPNV-Verkehrsmitteln & ÖPNV + Fahrrad / E-Scooter / Bikesharing |
| Cosa comprende: Auto conducente o passeggero o carpooling / moto + autobus / metropolitana / tram / treno | Was umfasst: Auto als Fahrer oder Beifahrer oder Carpooling / Motorrad + Bus / U-Bahn / Straßenbahn / Zug |
| Cosa comprende: Navetta & Navetta + Bici / monopattino / bike sharing / auto / trasporto pubblico | Was umfasst: Shuttle & Shuttle + Fahrrad / E-Scooter / Bikesharing / Auto / öffentliche Verkehrsmittel |
| Esprimi un giudizio facendo riferimento alla complessità del tuo spostamento casa-lavoro.  1 = per nulla soddisfatto, 5 = assolutamente soddisfatto | Geben Sie eine Bewertung ab und beziehen Sie sich dabei auf die Komplexität Ihrer Fahrt von zu Hause zur Arbeit.  1 = überhaupt nicht zufrieden, 5 = absolut zufrieden |
| Il valore deve essere riportato come percentuale computata rispetto al totale dei dipendenti rispondenti al questionario di indagine | Der Wert ist als Prozentsatz bezogen auf die Gesamtzahl der Beschäftigten anzugeben, die den Fragebogen der Erhebung beantwortet haben |
| Il valore deve essere un numero | Der Wert muss eine Zahl sein |
| In caso di tragitto non diretto, considera anche le tappe intermedie | Berücksichtigen Sie bei einer nicht direkten Strecke auch die Zwischenstopps |
| Indica il livello di gradimento delle seguenti modalità organizzative | Geben Sie an, wie sehr Ihnen die folgenden Organisationsformen zusagen |
| Indica il numero ... | Geben Sie die Zahl an ... |
| Indica il numero di lavoratori che utilizzano l'auto per venire al lavoro | Geben Sie die Anzahl der Mitarbeitenden an, die mit dem Auto zur Arbeit kommen |
| Scegli l'inquadramento più vicino alle tue effettive mansioni tra le opzioni sotto riportate | Wählen Sie unter den unten aufgeführten Optionen die Einstufung, die Ihren tatsächlichen Aufgaben am nächsten kommt |
| Scegli l’inquadramento più vicino alle tue effettive mansioni tra le opzioni sotto riportate | Wählen Sie unter den unten aufgeführten Optionen die Einstufung, die Ihren tatsächlichen Aufgaben am nächsten kommt |
| Secondo il Decreto Legislativo n° 66 del 8.4.2003 ("Attuazione delle direttive 93/104/CE e 2000/34/CE concernenti taluni aspetti dell'organizzazione dell'orario di lavoro") si intende per: “lavoro a turni” qualsiasi metodo di organizzazione del lavoro anche a squadre in base al quale dei lavoratori siano successivamente occupati negli stessi posti di lavoro, secondo un determinato ritmo, compreso il ritmo rotativo, che può essere di tipo continuo o discontinuo, e il quale comporti la necessità per i lavoratori di compiere un lavoro a ore differenti su un periodo determinato di giorni o di settimane. | Nach dem Gesetzesdekret Nr. 66 vom 8.4.2003 („Umsetzung der Richtlinien 93/104/EG und 2000/34/EG über bestimmte Aspekte der Arbeitszeitgestaltung“) bezeichnet „Schichtarbeit“ jede Form der Arbeitsorganisation, auch in Teams, bei der Arbeitnehmer nach einem bestimmten Rhythmus, einschließlich eines Rotationsrhythmus, der kontinuierlich oder diskontinuierlich sein kann, nacheinander an denselben Arbeitsplätzen eingesetzt werden und die es erforderlich macht, dass die Arbeitnehmer über einen bestimmten Zeitraum von Tagen oder Wochen zu unterschiedlichen Zeiten arbeiten. |
| Sì, ma solo se l'azienda mi fornisse gli strumenti necessari. Sì, se l'azienda contribuisse alle spese (es. connessione internet, elettricità, ecc..). No, mai. Sarei interessato a lavorare presso un ufficio di coworking vicino a casa | Ja, aber nur, wenn mir das Unternehmen die notwendige Ausstattung zur Verfügung stellt. Ja, wenn sich das Unternehmen an den Kosten beteiligt (z. B. Internetverbindung, Strom usw.). Nein, nie. Ich wäre daran interessiert, in einem Coworking-Büro in der Nähe meines Wohnorts zu arbeiten |
| indica gli spostamenti per te più importanti o frequenti | geben Sie die für Sie wichtigsten oder häufigsten Fahrten an |
| sdasd | sdasd |
| test | test |

## New translations — Answer options (| separated) (71)

| Italian | German |
|---|---|
| 1-5 min\|5-10 min\|10-15 min\|15-20 min | 1-5 Min.\|5-10 Min.\|10-15 Min.\|15-20 Min. |
| 1\|2\|3\|4\|5 | 1\|2\|3\|4\|5 |
| 1\|2\|3\|4\|5\|6 | 1\|2\|3\|4\|5\|6 |
| 6 giorni / settimana\|5 giorni / settimana\|4 giorni / settimana\|3 giorni / settimana\|2 giorni / settimana\|1 giorno / settimana | 6 Tage / Woche\|5 Tage / Woche\|4 Tage / Woche\|3 Tage / Woche\|2 Tage / Woche\|1 Tag / Woche |
| < 100 mt\|> 100 mt | < 100 m\|> 100 m |
| <b>Dirigente</b> - *Servizio di trasporto a chiamata: sono servizi di mobilità che non seguono orari e percorsi fissi, ma si attivano solo quando un utente prenota lo spostamento. Nella pratica, si richiede il viaggio (per telefono o tramite app) indicando luogo di partenza e destinazione, e il mezzo viene organizzato in base alle richieste ricevute.)\|test | <b>Führungskraft</b> - *Bedarfsverkehr: Dies sind Mobilitätsdienste, die keinen festen Fahrplänen und Strecken folgen, sondern nur aktiviert werden, wenn ein Nutzer die Fahrt bucht. In der Praxis wird die Fahrt (telefonisch oder per App) unter Angabe von Start- und Zielort angefordert, und das Fahrzeug wird entsprechend den eingegangenen Anfragen organisiert.)\|test |
| <b>meno</b> di 15 minuti\|<b> più </b> di 90 minuti\|tra 15 e 30 minuti\|tra 30 e 45 minuti\|tra 45 e 60 minuti\|tra 60 e 75 minuti\|tra 75 e 90 minuti | <b>Weniger</b> als 15 Minuten\|<b> Mehr </b> als 90 Minuten\|Zwischen 15 und 30 Minuten\|Zwischen 30 und 45 Minuten\|Zwischen 45 und 60 Minuten\|Zwischen 60 und 75 Minuten\|Zwischen 75 und 90 Minuten |
| A piedi (> 5min)\|Bicicletta muscolare / elettrica o monopattino\|Navetta aziendale\|Trasporto pubblico (autobus, metropolitana, treno)\|Condivisione dell''auto con i colleghi (car pooling)\|Autovettura o motociclo | Zu Fuß (> 5 Min.)\|Fahrrad (mit Muskelkraft) / E-Bike oder Tretroller\|Firmenshuttle\|Öffentlicher Verkehr (Bus, U-Bahn, Zug)\|Fahrgemeinschaft mit Kolleginnen und Kollegen (Carpooling)\|Auto oder Motorrad |
| A piedi (> 5min)\|Bicicletta muscolare / elettrica, cargo-bike o monopattino\|Trasporto pubblico (autobus, metropolitana, tram, treno)\|Condivisione dell'autovettura con i colleghi (car pooling)\|Autoveicolo o motociclo | Zu Fuß (> 5 Min.)\|Fahrrad (mit Muskelkraft) / E-Bike, Lastenrad oder Tretroller\|Öffentlicher Verkehr (Bus, U-Bahn, Straßenbahn, Zug)\|Fahrgemeinschaft mit Kolleginnen und Kollegen (Carpooling)\|Kraftfahrzeug oder Motorrad |
| A piedi (> 5min)\|Bicicletta muscolare / elettrica, cargo-bike o monopattino\|Trasporto pubblico (autobus, metropolitana, treno)\|Condivisione dell'autovettura con i colleghi (car pooling)\|Autoveicolo o motociclo | Zu Fuß (> 5 Min.)\|Fahrrad (mit Muskelkraft) / E-Bike, Lastenrad oder Tretroller\|Öffentlicher Verkehr (Bus, U-Bahn, Zug)\|Fahrgemeinschaft mit Kolleginnen und Kollegen (Carpooling)\|Kraftfahrzeug oder Motorrad |
| A piedi\|Bicicletta, monopattino o bike sharing\|Carpooling\|Navetta aziendale\|Trasporto pubblico (autobus, metropolitana, tram o treno)\|Intermodalità (auto / moto + TPL) | Zu Fuß\|Fahrrad, E-Scooter oder Bikesharing\|Carpooling\|Werksshuttle\|Öffentliche Verkehrsmittel (Bus, U-Bahn, Straßenbahn oder Zug)\|Intermodalität (Auto / Motorrad + ÖPNV) |
| Alfa\|Beta | Alfa\|Beta |
| Arca Technologies S.r.l. Via Statale 17 Bollengo TO | Arca Technologies S.r.l. Via Statale 17 Bollengo TO |
| Auto | Auto |
| Auto aziendale\|Auto da solo come conducente\|Auto come passeggero o in carpooling\|Car sharing aziendale\|Car sharing cittadino\|Taxi | Firmenwagen\|Auto allein als Fahrer/in\|Auto als Beifahrer/in oder im Carpooling\|Betriebliches Carsharing\|Städtisches Carsharing\|Taxi |
| Conoscenza e confidenza con i possibili compagni di viaggio\|Maggiore flessibilità oraria che permetta la conciliazione degli orari tra colleghi\|Disponibilità di una piattaforma digitale per lo scambio dei passaggi\|Disponibilità di autovetture aziendali per il car pooling\|Presenza, in azienda, di stalli di sosta riservati al car pooling\|Assegnazione di premialità e/o incentivi economici per i carpoolisti\|Nessuna condizione, non sono disponibile al car pooling | Kenntnis und Vertrautheit mit den möglichen Mitfahrern\|Mehr zeitliche Flexibilität, die eine Abstimmung der Arbeitszeiten unter Kollegen ermöglicht\|Verfügbarkeit einer digitalen Plattform zur Vermittlung von Mitfahrgelegenheiten\|Verfügbarkeit von Firmenfahrzeugen für Carpooling\|Vorhandensein von Parkplätzen im Unternehmen, die für Carpooling reserviert sind\|Gewährung von Prämien und/oder finanziellen Anreizen für Fahrgemeinschaftsteilnehmer\|Keine Bedingung, ich bin nicht bereit zum Carpooling |
| Conosco bene e so usare in totale autonomia la rete di trasporto e gli strumenti per l’accesso (es. RERpp!)\|Conosco solo la linea metro e la linea autobus nei pressi della mia abitazione\|Non la conosco, ma vorrei approfondire | Ich kenne das Verkehrsnetz und die Zugangsinstrumente (z. B. RERpp!) gut und kann sie völlig selbstständig nutzen\|Ich kenne nur die U-Bahn-Linie und die Buslinie in der Nähe meiner Wohnung\|Ich kenne es nicht, möchte mich aber näher damit befassen |
| Destinazione in città\|Destinazione fuori città\|Cantieri o sopralluoghi in zone difficilmente accessibili | Ziel in der Stadt\|Ziel außerhalb der Stadt\|Baustellen oder Ortsbesichtigungen in schwer zugänglichen Gebieten |
| Dirigente - *Servizio di trasporto a chiamata: sono servizi di mobilità che non seguono orari e percorsi fissi, ma si attivano solo quando un utente prenota lo spostamento. Nella pratica, si richiede il viaggio (per telefono o tramite app) indicando luogo di partenza e destinazione, e il mezzo viene organizzato in base alle richieste ricevute.)\|test | Führungskraft - *Bedarfsverkehr: Dies sind Mobilitätsdienste, die keinen festen Fahrplänen und Strecken folgen, sondern nur aktiviert werden, wenn ein Nutzer die Fahrt bucht. In der Praxis wird die Fahrt (telefonisch oder per App) unter Angabe von Start- und Zielort angefordert, und das Fahrzeug wird entsprechend den eingegangenen Anfragen organisiert.)\|test |
| Dirigente\|Quadro\|Impiegato\|Operaio\|Docente\|Studente\|Altro | Leitende/r Angestellte/r\|Führungskraft\|Angestellte/r\|Arbeiter/in\|Lehrkraft\|Studierende/r\|Sonstiges |
| Euro 0\|Euro 1\|Euro 2\|Euro 3\|Euro 4\|Euro 5\|Euro 6 | Euro 0\|Euro 1\|Euro 2\|Euro 3\|Euro 4\|Euro 5\|Euro 6 |
| Fermata autobus più vicina agli ingressi della sede lavorativa\|Fermata autobus più vicina alla mia abitazione\|Corse più frequenti\|Maggiori frequenze nelle fasce orarie serali e notturne\|Maggiore flessibilità oraria, così da poter accedere ai servizi (es. treno, servizio extraurbano, ecc.)\|Assegnazione di premialità o riconoscimenti economici\|Utilizzo gratuito o a tariffe agevolate dei servizi\|Nessuna condizione, non sono disponibile a utilizzare il trasporto pubblico | Näher an den Eingängen des Arbeitsstandorts gelegene Bushaltestelle\|Näher an meiner Wohnung gelegene Bushaltestelle\|Häufigere Fahrten\|Höhere Taktfrequenzen in den Abend- und Nachtstunden\|Mehr zeitliche Flexibilität, um die Dienste nutzen zu können (z. B. Zug, Überlandverkehr usw.)\|Gewährung von Prämien oder finanziellen Anerkennungen\|Kostenlose oder ermäßigte Nutzung der Dienste\|Keine Bedingung, ich bin nicht bereit, öffentliche Verkehrsmittel zu nutzen |
| Gratuità del servizio\|Minore tempo di percorrenza rispetto all’auto privata\|Minore difficoltà di parcheggio rispetto all’auto privata\|Servizio frequente e disponibile a qualunque orario\|Servizio a domicilio\|Nessuna condizione, non sono interessato | Kostenlosigkeit des Dienstes\|Kürzere Fahrzeit als mit dem privaten Auto\|Weniger Schwierigkeiten bei der Parkplatzsuche als mit dem privaten Auto\|Häufiger Dienst, der zu jeder Uhrzeit verfügbar ist\|Haustürservice\|Keine Bedingung, ich bin nicht interessiert |
| Incontri sulle strategie per la mobilità sostenibile cittadina\|Incontri formativi sull’offerta di trasporto di RER\|Incontri conoscitivi sulle strategie aziendali per il casa-lavoro\|Momenti aggregativi, visite guidate, biciclettate, ecc. sul tema mobilità sostenibile\|Corsi di Eco-Guida\|Community e Gruppi per l’uso dei diversi servizi\|Promozione dell’uso del trasporto pubblico con agevolazioni o abbonamenti aziendali\|Progetti di “bike to work” con incentivi o benefit\|Servizi aziendali di bike sharing o monopattini elettrici condivisi\|Servizi navetta aziendali o interaziendali verso nodi del trasporto pubblico\|Parcheggi aziendali dedicati a chi condivide l’auto\|Workshop sul benessere e sicurezza legata agli spostamenti casa-lavoro\|Challenge e premi per chi adotta modalità sostenibili\|Sportelli di consulenza individuale sulla mobilità\|Giornate tematiche aziendali (Bike Day, Mobility Day, Car Free Day)\|Altro | Veranstaltungen zu den Strategien für nachhaltige städtische Mobilität\|Schulungsveranstaltungen zum Verkehrsangebot von RER\|Informationsveranstaltungen zu den betrieblichen Strategien für den Arbeitsweg\|Treffen, Führungen, Fahrradtouren usw. zum Thema nachhaltige Mobilität\|Eco-Driving-Kurse\|Communities und Gruppen für die Nutzung der verschiedenen Dienste\|Förderung der Nutzung öffentlicher Verkehrsmittel durch Vergünstigungen oder Firmenabonnements\|„Bike to Work“-Projekte mit Anreizen oder Zusatzleistungen\|Betriebliche Bikesharing- oder E-Scooter-Sharing-Dienste\|Betriebliche oder zwischenbetriebliche Shuttle-Dienste zu Knotenpunkten des öffentlichen Verkehrs\|Betriebliche Parkplätze für Personen, die ihr Auto teilen\|Workshops zu Wohlbefinden und Sicherheit auf dem Arbeitsweg\|Challenges und Preise für alle, die nachhaltige Verkehrsmittel nutzen\|Schalter für individuelle Mobilitätsberatung\|Betriebliche Thementage (Bike Day, Mobility Day, Car Free Day)\|Sonstiges |
| Mai od occasionalmente (non più di 2 o 3 volte al mese)\|Frequentemente (qualche giorno a settimana)\|Sempre (tutti o quasi tutti i giorni della mia settimana lavorativa) | Nie oder gelegentlich (nicht öfter als 2 oder 3 Mal pro Monat)\|Häufig (einige Tage pro Woche)\|Immer (an allen oder fast allen Tagen meiner Arbeitswoche) |
| Mai od occasionalmente, non più di 2 o 3 volte al mese\|Frequentemente, qualche giorno a settimana\|Sempre, tutti i giorni della mia settimana lavorativa\|In determinati periodi dell'anno | Nie oder gelegentlich, höchstens 2 oder 3 Mal im Monat\|Häufig, einige Tage pro Woche\|Immer, an allen Tagen meiner Arbeitswoche\|In bestimmten Zeiten des Jahres |
| Mai od occasionalmente, non più di 2 o 3 volte al mese\|Frequentemente, qualche giorno a settimana\|Sempre, tutti i giorni della mia settimana lavorativa\|In determinati periodi dell’anno | Nie oder gelegentlich, höchstens 2 oder 3 Mal im Monat\|Häufig, einige Tage pro Woche\|Immer, an allen Tagen meiner Arbeitswoche\|In bestimmten Zeiten des Jahres |
| Meno di 1 minuto\|1 - 5 minuti\|6 - 10 minuti\|Oltre 10 minuti | Weniger als 1 Minute\|1 - 5 Minuten\|6 - 10 Minuten\|Mehr als 10 Minuten |
| Meno di 30\|30 - 39\|40 - 49\|50 - 59\|60 o più | Weniger als 30\|30 - 39\|40 - 49\|50 - 59\|60 oder mehr |
| Meno di 5 min\|5 - 10 min\|11 - 15 min\|16 - 20 min\|21 - 30 min\|31 - 40 min\|41 - 50 min\|51 - 60 min\|Oltre 1 ora | Weniger als 5 Min.\|5 - 10 Min.\|11 - 15 Min.\|16 - 20 Min.\|21 - 30 Min.\|31 - 40 Min.\|41 - 50 Min.\|51 - 60 Min.\|Über 1 Stunde |
| Microtecnica - Torino\|Microtecnica - Luserna San Giovanni | Microtecnica - Torino\|Microtecnica - Luserna San Giovanni |
| Miglioramento delle condizioni di sicurezza nel tragitto che devo percorrere\|Disponibilità di un servizio gratuito aziendale di biciclette elettriche in condivisione e/o su prenotazione\|Disponibilità di una bicicletta elettrica aziendale assegnatami in comodato d’uso esclusivo\|Presenza, in azienda, di rastrelliere e/o spazi coperti e sicuri per la sosta\|Presenza, in azienda, di colonnine di ricarica per biciclette elettriche\|Presenza, in azienda, di armadietti / docce / spogliatoi dedicati\|Presenza, in azienda, di colonnine di manutenzione / riparazione bici\|Disponibilità di incentivi economici per l’acquisto della bicicletta\|Assegnazione di premialità e/o rimborsi chilometrici per l’utilizzo della bicicletta\|Nessuna condizione, non sono disponibile ad utilizzare la bicicletta\|Nessuna condizione, sono impossibilitato/a ad utilizzare la bicicletta | Verbesserung der Sicherheitsbedingungen auf meiner Strecke\|Verfügbarkeit eines kostenlosen betrieblichen Sharing- und/oder Reservierungsdienstes für E-Bikes\|Verfügbarkeit eines mir zur ausschließlichen Nutzung überlassenen betrieblichen E-Bikes\|Vorhandensein von Fahrradständern und/oder überdachten, sicheren Abstellplätzen im Unternehmen\|Vorhandensein von Ladestationen für E-Bikes im Unternehmen\|Vorhandensein von eigenen Spinden / Duschen / Umkleideräumen im Unternehmen\|Vorhandensein von Wartungs- / Reparaturstationen für Fahrräder im Unternehmen\|Verfügbarkeit finanzieller Anreize für den Kauf eines Fahrrads\|Gewährung von Prämien und/oder Kilometererstattungen für die Nutzung des Fahrrads\|Keine Bedingung, ich bin nicht bereit, das Fahrrad zu nutzen\|Keine Bedingung, ich kann das Fahrrad nicht nutzen |
| No, il tragitto è diretto\|Sì, 1 - 3 volte alla settimana\|Sì, tutti i giorni | Nein, die Strecke ist direkt\|Ja, 1 - 3 Mal pro Woche\|Ja, jeden Tag |
| Non utilizzo abitualmente l’autovettura o il motociclo\|No, non sono disponibile\|Sì, a determinate condizioni | Ich nutze Auto oder Motorrad nicht regelmäßig\|Nein, ich bin nicht bereit dazu\|Ja, unter bestimmten Bedingungen |
| Orario fisso e/o con flessibilità oraria\|Orario a turnazione fissa\|Orario a turnazione variabile | Feste Arbeitszeit und/oder mit Gleitzeit\|Feste Schichtarbeit\|Wechselnde Schichtarbeit |
| Parcheggio gratuito di pertinenza aziendale\|Parcheggio a pagamento di pertinenza aziendale\|Parcheggio riservato assegnatomi dall’azienda\|Parcheggio pubblico a pagamento nelle vicinanze della sede\|Parcheggio pubblico gratuito nelle vicinanze della sede\|Altra soluzione di parcheggio | Kostenloser betriebseigener Parkplatz\|Kostenpflichtiger betriebseigener Parkplatz\|Reservierter, mir vom Unternehmen zugewiesener Parkplatz\|Kostenpflichtiger öffentlicher Parkplatz in der Nähe des Standorts\|Kostenloser öffentlicher Parkplatz in der Nähe des Standorts\|Andere Parkmöglichkeit |
| Per abitudine (non ho mai valutato o provato alternative)\|Per la facilità di parcheggio\|Per ragioni di tempo e/o affidabilità\|Perché mi serve per svolgere la mia attività lavorativa\|Per necessità di conciliazione vita-lavoro (es. accompagnamento familiari, spostamenti in pausa pranzo, ecc.)\|Per comfort di viaggio (es. viaggio solo e seduto, non vengo disturbato da altri, nessun tratto a piedi, ecc.)\|Altra motivazione | Aus Gewohnheit (ich habe nie Alternativen geprüft oder ausprobiert)\|Wegen der einfachen Parkmöglichkeiten\|Aus Zeit- und/oder Zuverlässigkeitsgründen\|Weil ich es für meine berufliche Tätigkeit brauche\|Aus der Notwendigkeit, Privatleben und Beruf zu vereinbaren (z. B. Begleitung von Familienangehörigen, Wege in der Mittagspause usw.)\|Aus Reisekomfort (z. B. ich fahre allein und sitzend, ich werde nicht von anderen gestört, kein Fußweg usw.)\|Andere Begründung |
| Per abitudine (non ho mai valutato o provato alternative)\|Per ragioni legate alla mia ridotta mobilità fisica o sensoriale\|Per la facilità di parcheggio\|Per ragioni di tempo e/o affidabilità\|Perché mi serve per svolgere la mia attività lavorativa\|Per necessità di conciliazione vita-lavoro (es. accompagnamento familiari, spostamenti in pausa pranzo, ecc.)\|Per comfort di viaggio (es. viaggio solo e seduto, non vengo disturbato da altri, trasporto oggetti, fresco d’estate e caldo d’inverno, sono riparato dalla pioggia, nessun tratto a piedi, ecc.)\|Altra motivazione | Aus Gewohnheit (ich habe nie Alternativen geprüft oder ausprobiert)\|Aus Gründen, die mit meiner eingeschränkten körperlichen oder sensorischen Mobilität zusammenhängen\|Wegen der einfachen Parkmöglichkeiten\|Aus Zeit- und/oder Zuverlässigkeitsgründen\|Weil ich es für meine berufliche Tätigkeit brauche\|Aus der Notwendigkeit, Privatleben und Beruf zu vereinbaren (z. B. Begleitung von Familienangehörigen, Wege in der Mittagspause usw.)\|Aus Reisekomfort (z. B. ich fahre allein und sitzend, ich werde nicht von anderen gestört, Transport von Gegenständen, kühl im Sommer und warm im Winter, ich bin vor Regen geschützt, kein Fußweg usw.)\|Andere Begründung |
| Per abitudine (non ho mai valutato o provato alternative)\|Per ragioni legate alla mia ridotta mobilità fisica o sensoriale\|Per ragioni di tempo e/o affidabilità\|Per necessità di conciliazione vita-lavoro (es. accompagnamento familiari, spostamenti in pausa pranzo, ecc.)\|Per comfort di viaggio (es. viaggio seduto, trasporto oggetti, fresco d’estate e caldo d’inverno, sono riparato dalla pioggia, nessun tratto a piedi, ecc.)\|Altra motivazione | Aus Gewohnheit (ich habe nie Alternativen geprüft oder ausprobiert)\|Aus Gründen, die mit meiner eingeschränkten körperlichen oder sensorischen Mobilität zusammenhängen\|Aus Zeit- und/oder Zuverlässigkeitsgründen\|Aus der Notwendigkeit, Privatleben und Beruf zu vereinbaren (z. B. Begleitung von Familienangehörigen, Wege in der Mittagspause usw.)\|Aus Reisekomfort (z. B. sitzende Fahrt, Transport von Gegenständen, kühl im Sommer und warm im Winter, ich bin vor Regen geschützt, kein Fußweg usw.)\|Andere Begründung |
| Per scelta od organizzazione aziendale\|Per abitudine (non ho mai valutato o provato alternative)\|Trasporto materiali o attrezzi da lavoro\|Perché è il mezzo più veloce\|Perché è l'unico mezzo per raggiungere la destinazione\|Per comfort di viaggio (es. non vengo disturbato da altri, fresco d’estate e caldo d’inverno, sono riparato dalla pioggia, nessun tratto a piedi, ecc.) | Aufgrund einer betrieblichen Entscheidung oder Organisation\|Aus Gewohnheit (ich habe nie Alternativen geprüft oder ausprobiert)\|Transport von Material oder Arbeitsgeräten\|Weil es das schnellste Verkehrsmittel ist\|Weil es das einzige Verkehrsmittel ist, um das Ziel zu erreichen\|Aus Reisekomfort (z. B. ich werde nicht von anderen gestört, kühl im Sommer und warm im Winter, ich bin vor Regen geschützt, kein Fußweg usw.) |
| Perché i percorsi pedonali (marciapiedi o spazi riservati ai pedoni)/ le piste ciclabili non ci sono\|Perché le piste ciclabili non sono separate dalle corsie dei veicoli\|Perché sulla carreggiata ci sono buche o tombini\|Perché sulla carreggiata ci sono cassonetti o altri elementi che intralciano il passaggio\|Perché la velocità dei veicoli mette a rischio tutti quanti (pedoni, ciclisti, altri automobilisti)\|Perché agli attraversamenti i veicoli non rispettano le regole (non si fermano né rallentano)\|Perché le strade sono poco illuminate\|Perché lungo le strade ci sono pochi negozi o altri punti di riferimento (biblioteca, centro giovani, oratorio ecc.) | Weil es keine Fußwege (Gehsteige oder Fußgängerbereiche)/Radwege gibt\|Weil die Radwege nicht von den Fahrspuren der Fahrzeuge getrennt sind\|Weil es auf der Fahrbahn Schlaglöcher oder Gullys gibt\|Weil auf der Fahrbahn Mülltonnen oder andere Hindernisse den Durchgang behindern\|Weil die Geschwindigkeit der Fahrzeuge alle gefährdet (Fußgänger, Radfahrer, andere Autofahrer)\|Weil die Fahrzeuge an Übergängen die Regeln nicht beachten (sie halten weder an noch bremsen sie ab)\|Weil die Straßen schlecht beleuchtet sind\|Weil es entlang der Straßen nur wenige Geschäfte oder andere Orientierungspunkte gibt (Bibliothek, Jugendzentrum, Pfarrjugendheim usw.) |
| Personale\|Bike sharing aziendale\|Bike sharing cittadino | Privat\|Firmen-Bikesharing\|Städtisches Bikesharing |
| Personale\|sharing aziendale | Privat\|Firmen-Sharing |
| Piedi (per un tragitto >15 minuti)\|Bus/tram\|Metropolitana GTT\|Auto\|Moto/Scooter\|Bicicletta\|Monopattino\|Taxi\|Servizio ferroviario Trenitalia/GTT\|Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...)\|Car sharing\|Bike sharing\|Moto sharing\|Monopattino sharing\|Navetta aziendale\|Car pooling | Zu Fuß (für eine Strecke >15 Minuten)\|Bus/Straßenbahn\|U-Bahn GTT\|Auto\|Motorrad/Roller\|Fahrrad\|E-Scooter\|Taxi\|Bahnverkehr Trenitalia/GTT\|Bahnverkehr anderer Betreiber (Trenord, Italo, Hochgeschwindigkeit, ...)\|Carsharing\|Bikesharing\|Motorrad-Sharing\|E-Scooter-Sharing\|Firmenshuttle\|Carpooling |
| Piedi\|Bus/Tram\|Metro\|Auto\|Moto\|Bici\|Monopattino\|Taxi\|Treno\|Servizio ferroviario\|Car sharing\|Bike sharing\|Moto sharing\|Monopattino sharing\|Navetta aziendale\|Car pooling | Zu Fuß\|Bus/Straßenbahn\|U-Bahn\|Auto\|Motorrad\|Fahrrad\|E-Scooter\|Taxi\|Zug\|Bahnverkehr\|Carsharing\|Bikesharing\|Motorradsharing\|E-Scooter-Sharing\|Werksshuttle\|Carpooling |
| Più piste ciclabili protette dalle corsie riservate ai auto\|Più interventi per rallentare i veicoli\|Presenza di adulti che aiutino gli studenti ad attraversare nei punti più a rischio\|Presenza, a scuola, di parcheggi per biciclette comodi, sicuri e coperti\|Altro | Mehr Radwege, die von den Fahrspuren für Autos geschützt sind\|Mehr Maßnahmen zur Verlangsamung der Fahrzeuge\|Anwesenheit von Erwachsenen, die den Schülern an den gefährlichsten Stellen beim Überqueren helfen\|Bequeme, sichere und überdachte Fahrradabstellplätze an der Schule\|Sonstiges |
| Rimango in sede (es. mensa aziendale)\|Pranzo fuori sede\|Torno a casa\|Non faccio la pausa pranzo | Ich bleibe vor Ort (z. B. Betriebskantine)\|Mittagessen außerhalb\|Ich gehe nach Hause\|Ich mache keine Mittagspause |
| S1\|Sede 2\|sede 3\|sede seconda | S1\|Standort 2\|Standort 3\|zweiter Standort |
| Scuola dell'infanzia\|Scuola primaria (elementare)\|Scuola secondaria di primo grado (media)\|Scuola secondaria di secondo grado (superiore) | Kindergarten\|Grundschule\|Sekundarschule I (Mittelschule)\|Sekundarschule II (Oberschule) |
| Se fosse disponibile una flotta aziendale di biciclette elettriche\|Se avessi a disposizione un abbonamento ai servizi di trasporto pubblico\|Se l’azienda mi supportasse nella pianificazione dello spostamento con i mezzi di trasporto pubblico\|Se esistesse un sistema di gestione / prenotazione / organizzazione dei viaggi con i colleghi (car pooling aziendale)\|Se potessi usufruire di adeguata o maggiore flessibilità oraria\|Se mi fosse inibita la possibilità di utilizzo dell’autovettura\|Non sono disponibile a cambiare abitudini | Wenn eine betriebliche Flotte von E-Bikes verfügbar wäre\|Wenn ich ein Abonnement für öffentliche Verkehrsdienste zur Verfügung hätte\|Wenn mich das Unternehmen bei der Planung der Fahrt mit öffentlichen Verkehrsmitteln unterstützen würde\|Wenn es ein System zur Verwaltung / Buchung / Organisation der Fahrten mit Kollegen gäbe (betriebliches Carpooling)\|Wenn ich eine angemessene oder größere zeitliche Flexibilität nutzen könnte\|Wenn mir die Möglichkeit, das Auto zu nutzen, untersagt würde\|Ich bin nicht bereit, meine Gewohnheiten zu ändern |
| Se fosse disponibile una flotta aziendale di cargo bike elettriche\|Se esistesse un sistema di gestione / prenotazione / organizzazione dei viaggi con i colleghi (car pooling aziendale)\|Se potessi usufruire di un’adeguata o maggiore flessibilità oraria\|Non sono disponibile a cambiare abitudini | Wenn eine betriebliche Flotte von elektrischen Lastenrädern verfügbar wäre\|Wenn es ein System zur Verwaltung / Buchung / Organisation von Fahrten mit Kolleginnen und Kollegen gäbe (betriebliches Carpooling)\|Wenn ich eine angemessene oder größere zeitliche Flexibilität nutzen könnte\|Ich bin nicht bereit, meine Gewohnheiten zu ändern |
| Sede 2\|Sede Principale | Standort 2\|Hauptstandort |
| Sede 2\|Sede Principale\|sede seconda | Standort 2\|Hauptstandort\|zweiter Standort |
| Sede Principale | Hauptstandort |
| Sede Principale\|Succursale | Hauptstandort\|Zweigstelle |
| Sede ex Don Bosco\|Presidio Territoriale Nizza\|Ospedale Cardinal Massaia | Standort ex Don Bosco\|Präsidio Territoriale Nizza\|Ospedale Cardinal Massaia |
| Sede legale | Hauptsitz |
| Sede operativa Adriano Sada\|Sede operativa Davide Negro\|Sede operativa Carlo Ghiglieno\|Sede operativa Ferdinando Prat\|Sede operativa Pietro Cinotto | Betriebsstandort Adriano Sada\|Betriebsstandort Davide Negro\|Betriebsstandort Carlo Ghiglieno\|Betriebsstandort Ferdinando Prat\|Betriebsstandort Pietro Cinotto |
| Sede principale template | Hauptstandort Vorlage |
| Sì, solo quella di proprietà\|Sì, quella condivisa (bike sharing)\|Sì, se ci fossero itinerari ciclabili sicuri\|Sì, se in azienda ci fossero le attrezzature a corredo dello spostamento in bicicletta (spogliatoi e docce per l’igiene personale,  con eventuale possibilità di ricarica per ebike ecc.)\|Sì, se ci fosse un parcheggio interno sicuro\|Sì, se ci fosse un parcheggio adeguato di interscambio con il trasporto pubblico (es. in stazione)\|Sì, se potessi disporre di un incentivo o di un rimborso per l'acquisto di una bicicletta\|Sì, solo nei mesi estivi\|No | Ja, nur mein eigenes\|Ja, das geteilte (Bikesharing)\|Ja, wenn es sichere Radrouten gäbe\|Ja, wenn es im Unternehmen die zum Radfahren gehörende Ausstattung gäbe (Umkleideräume und Duschen für die Körperpflege, gegebenenfalls mit Lademöglichkeit für E-Bikes usw.)\|Ja, wenn es einen sicheren internen Parkplatz gäbe\|Ja, wenn es einen geeigneten Park-and-ride-Parkplatz mit Anschluss an den öffentlichen Verkehr gäbe (z. B. am Bahnhof)\|Ja, wenn ich einen Anreiz oder eine Erstattung für den Kauf eines Fahrrads erhalten könnte\|Ja, nur in den Sommermonaten\|Nein |
| Sì\|No, lavoro per conto di altra società (es. cooperativa / agenzia interinale / subappaltatore / fornitore esterno / altro) | Ja\|Nein, ich arbeite für ein anderes Unternehmen (z. B. Genossenschaft / Zeitarbeitsagentur / Subunternehmer / externer Lieferant / sonstiges) |
| Torino | Torino |
| Torino Sud\|Torino nord\|Moncalieri\|Ciriè\|Pinerolo\|Rivoli\|Ivrea\|Cuorgnè\|Orbassano\|Susa\|Venaria Reale\|Chivasso\|Settimo Torinese\|Carmagnola\|Cuneo\|Alba\|Bra\|Fossano\|Savigliano\|Mondovì\|Saluzzo\|Ceva\|Acqui Terme\|Alessandria\|Casale Monferrato\|Novi Ligure\|Tortona\|Asti\|Canelli\|Nizza Monferrato\|Ovada\|Villanova D'Asti\|Biella\|Novara\|Borgomanero\|Domodossola\|Verbania\|Vercelli\|Borgosesia\|Chieri\|Omegna\|Torino via Avogadro | Torino Sud\|Torino nord\|Moncalieri\|Ciriè\|Pinerolo\|Rivoli\|Ivrea\|Cuorgnè\|Orbassano\|Susa\|Venaria Reale\|Chivasso\|Settimo Torinese\|Carmagnola\|Cuneo\|Alba\|Bra\|Fossano\|Savigliano\|Mondovì\|Saluzzo\|Ceva\|Acqui Terme\|Alessandria\|Casale Monferrato\|Novi Ligure\|Tortona\|Asti\|Canelli\|Nizza Monferrato\|Ovada\|Villanova D'Asti\|Biella\|Novara\|Borgomanero\|Domodossola\|Verbania\|Vercelli\|Borgosesia\|Chieri\|Omegna\|Torino via Avogadro |
| Uno\|Due | Eins\|Zwei |
| Uomo\|Donna\|Preferisco non specificare | Mann\|Frau\|Ich möchte keine Angabe machen |
| Variabile in base al turno settimanale\|prima delle 14:00\|tra le 14:00 e le 14:30\|tra le 14:30 e le 15:00\|tra le 15:00 e le 15:30\|tra le 15:30 e le 16:00\|tra le 16:00 e le 16:30\|tra le 16:30 e le 17:00\|tra le 17:00 e le 17:30\|tra le 17:30 e le 18:00\|tra le 18:00 e le 18:30\|tra le 18:30 e le 19:00\|oltre le 19:00 | Variabel je nach Wochenschicht\|vor 14:00 Uhr\|zwischen 14:00 und 14:30 Uhr\|zwischen 14:30 und 15:00 Uhr\|zwischen 15:00 und 15:30 Uhr\|zwischen 15:30 und 16:00 Uhr\|zwischen 16:00 und 16:30 Uhr\|zwischen 16:30 und 17:00 Uhr\|zwischen 17:00 und 17:30 Uhr\|zwischen 17:30 und 18:00 Uhr\|zwischen 18:00 und 18:30 Uhr\|zwischen 18:30 und 19:00 Uhr\|nach 19:00 Uhr |
| Variabile in base al turno settimanale\|prima delle 6:30\|tra le 6:30 e 6:45\|tra le 6:45 e le 7:00\|tra le 7:00 e le 7:15\|tra le 7:15 e le 7:30\|tra le 7:30 e le 7:45\|tra le 7:45 e le 8:00\|tra le 8:00 e le 8:15\|tra le 8:15 e le 8:30\|tra le 8:30 e le 8:45\|tra le 8:45 e le 9:00\|dopo le 9:00 | Variabel je nach Wochenschicht\|vor 6:30 Uhr\|zwischen 6:30 und 6:45 Uhr\|zwischen 6:45 und 7:00 Uhr\|zwischen 7:00 und 7:15 Uhr\|zwischen 7:15 und 7:30 Uhr\|zwischen 7:30 und 7:45 Uhr\|zwischen 7:45 und 8:00 Uhr\|zwischen 8:00 und 8:15 Uhr\|zwischen 8:15 und 8:30 Uhr\|zwischen 8:30 und 8:45 Uhr\|zwischen 8:45 und 9:00 Uhr\|nach 9:00 Uhr |
| Via Alfieri, 15 \|P.zza Solferino, 22 \|Via Arsenale, 14\|Via Arsenale, 12\|Via San Francesco D'Assisi, 35 | Via Alfieri, 15  \|P.zza Solferino, 22  \|Via Arsenale, 14\|Via Arsenale, 12\|Via San Francesco D'Assisi, 35 |
| as\|as | as\|as |
| bus urbano\|bus extraurbano\|tram\|navetta aziendale | Stadtbus\|Überlandbus\|Straßenbahn\|Firmenshuttle |
| marconi \|Sede 2\|sede seconda | marconi \|Standort 2\|zweiter Standort |

## New translations — array question group labels and options

| Italian | German |
|---|---|
| Auto | Auto |
| Bici | Fahrrad |
| Mezzo 1 | Verkehrsmittel 1 |
| Mezzo 2 | Verkehrsmittel 2 |
| Mezzo 3 | Verkehrsmittel 3 |
| Mezzo 4 | Verkehrsmittel 4 |
| Monopattino | Tretroller |
| Moto | Motorrad |
| Auto | Auto |
| Bici | Fahrrad |
| Mezzo Pubblico | Öffentliches Verkehrsmittel |
| Monopattino | Tretroller |
| Nave | Schiff |
| Piedi | Zu Fuß |


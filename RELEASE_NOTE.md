# EMMA - Release Note

## 2 Gennaio 2024 - 1.24.0102
- Separate in classi distinte le funzioni di esportazione
- Aggiunto un controller PsclController per staccare la generazione del PSCL da Office
- Aggiunta una dipendenza da beanstalkd per gestire la coda delle operazioni di esportazione lunghe
  - Se la coda non c'è tenta di eseguire in sincrono

## 26 Gennaio 2023 - 1.23.0126
- Nuova domanda mappa
- Gestione Mobility Label
- Adattamento per Riuso del Codice
- Miglioramento generazione PSCL HTML e DOC
- Ottimizzazione Geocoder
- Miglioramento Grafici (quickchart.io)

## 22 marzo 2022 - 1.22.0322
- Messaggio di benvenuto corretto
- Non dovrebbe duplicare i questionari in creazione (vi prego di verificare, perchè a me non lo faceva neanche prima)
- Nuova funzione analisi dati a sinistra che consente di analizzare i dati di questionari, aziende, sedi, etc (senza passare dal questionario ogni volta)
- Pulsante "nuovo questionario" solo per il mobility di area
- Possibilità di scaricare la guida utente
- Possibilità di caricare la guida utente in autonomia da https://api..../users/upload-guide

## 19 Gennaio 2022 - 1.22.0119
- Mostrate statistiche questionario in home
- Aggiunta nota su mappa per spiegare meglio il filtro per azienda
- Migliore gestione geocodifica


## 14 Dicembre 2021 - 1.21.1221
- Filtro su mappa migliorato per accelerare la risposta in caso di molti questionari
- Tolto il salvataggio del partecipante nel cookie, per gestire compilazioni multiple in browser condivisi
- Cancellato il participant_id in caso di compilazione completata della survey
- Migliorata la gestione delle domande obbligatorie in caso di array e checkbox
- Correzione bug questionario assegnato ad azienda diversa in lista utenti
- Aggiunte etichette su icone lista utenti
- Migliorata la gesitone aggiunta azienda che sul salvataggio aggiunge l'id azienda all'url per evitare doppioni in questionario
- Completamente rinnovata la generazione del PSCL per evitare incompatibilità con Libreoffice/Office varie versioni
- Miglioramento delle cache per i risultati della mappa (cancellazione su edit origini e durata più breve)


## 22 Novembre 2021 - 1.21.1122
- Aggiunto filtro multivariabile per domande nell'analisi del questioanario

## 21 Novempre 2021 - 1.21.1121

- Refactoring importazione dipendenti per gestire grandi numeri
- Aggiunta strategia di fallback per la geocodifica dei lavoratori (se non riconosce l'indirizzo usa CAP/CITTA) - Risultato 99%
- Miglioramenti ai grafici nelle analisi del questionario
- Completamento esportazione PSCL in formato DOCX
- Aggiunta possibilità di filtrare l'elenco dei dipendenti
- **Nascosta la possibilità** di importare elenchi dipendenti
- **Bloccata la possibilità di modificare il testo del messaggio di benvenuto**  per i non admin
- Questionario: miglioramento domande array

## 12 Febbario 2021 - 1.21.0212

### Impostazioni dell'ambiente da attivare
- Refactoring gestione degli invii mail tramite mail queue
  - Necessario eseguire `git clone git@github.com:impronta48/cakephp-email-queue.git plugins/EmailQueue`
  - Necessario eseguire `git clone git@bitbucket.org:massimoi/angelcake-notifications.git plugins/Notifications`
  - Lanciare `composer update`
  - Lanciare `bin/cake migrations migrate --plugin EmailQueue`
  - Aggiungere un task in cron (ogni 2 minuti) per invocare la mail queue `/var/www/vhosts/api/bin/cake EmailQueue.sender -l 10` (invia 10 mail per volta ogni 2 minuti)
- Migliorie nella gestione mappa e visualizzazione risposte al questionario (mail punto 5)
- Gestione migliorata dei grafici degli indicatori e unificazione con i grafici del questionario. Gestione corretta domande array, riduzione risposta duplicate (levenstein)
- Cambiamento del tipo di campo risposte in formato json (prima era mixed), migrazioni nel database
- Adattamento e test questionari, risposte, analytics, indicatori, mappa
- Aggiunta filtro per aziende negli analytics e nelle emissioni
- Migliorie footer (privacy e cookies) (https://trello.com/c/DtFPcrOw)
- Aggiornamento logo EMMA (https://trello.com/c/d22kUeOG)
- Correzione link questionario test (https://trello.com/c/M6V9DZuc)
- Solo admin può modificare le domande (https://trello.com/c/xvKk8JHw)

## 17 Gennaio 2021 - 1.21.0117
- Differenziazione del PSCL corretto per Scuole e Aziende
- Memorizzazione dell'ultima azienda scelta in fase di PSCL
- Generazione del documento Docx secondo indicazioni Città Metropolitana
- Possibilità di Caricare un template DOCX per ogni azienda (lato API)
- Miglioramento interfaccia utente API
- Inclusione Pagine Statiche (da CMS) nel tool (es: Pascal)
- Gestione di Servizi Esterni del DB
--> cfr trello
- https://trello.com/c/QJy1twCA
- https://trello.com/c/TccdAPZT
- https://trello.com/c/Wfdblsyv
- https://trello.com/c/oTS9Phz7
- https://trello.com/c/Hgqhsn43
- https://trello.com/c/8ns2pEI4

### Istruzioni Installazione v1.21.0110

#### Aggiornamento API

1. cd nella cartella API
2. copiare gli allegati della cartella owncloud
   [https://owncloud.impronta48.it/index.php/s/95jxnWgGt6agQF3]
   **api** nella cartella /webroot
3. eseguire la query che si trova in db/sql/V1\_\_measures.sql

```
git pull origin master
composer update
sudo chmod -R 777 webroot
```

#### Aggiornamento WEB

1. eseguire la query che si trova in /db/V9\_\_CreaPascal.sql
2. copiare gli allegati della cartella owncloud
   [https://owncloud.impronta48.it/index.php/s/95jxnWgGt6agQF3]
   **web** nella cartella /webroot
3. cd nella cartella del progetto

```
git pull origin master
composer update
sudo chmod -R 777 webroot
cd webroot/js/
npm update
```

#### Aggiornamento VUE

1. cd nella cartella del progetto
2. aggiungere alla riga 4 del file env.emma-5t
   l'url di accesso del CMS, esempio:
   VUE_APP_CMS="https://emma.5t.torino.it/"

```
git pull origin master
npm run build:emma-5t
```

3. Installare la cartella dist

---

## 6 Gennaio 2021 - v1.21.0106

Completamento dei seguenti task/bug/richieste
--> cfr trello https://trello.com/b/CwyQEWeR/5t-impronta-moma)

- https://trello.com/c/IDDPkZbc
- https://trello.com/c/UhNSNblV
- https://trello.com/c/GEP2dqp5
- https://trello.com/c/Wqr8QZGS
- https://trello.com/c/6C98pglM
- https://trello.com/c/QV7yhvWN
- https://trello.com/c/TP9cAO9x
- https://trello.com/c/TP9cAO9x
- https://trello.com/c/SqQaSfqj
- https://trello.com/c/NHoE01rX
- https://trello.com/c/WFiYTwPA
- https://trello.com/c/WFiYTwPA
- https://trello.com/c/HTsmPTJp
- https://trello.com/c/gl4ubjeU
- https://trello.com/c/fIFLQX3V
- https://trello.com/c/Kf5QZBbY
- https://trello.com/c/pe8lJtoD
- https://trello.com/c/pe8lJtoD
- https://trello.com/c/BwD6pWfL
- https://trello.com/c/YBXBCgwi
- https://trello.com/c/Qg76jkU4
- https://trello.com/c/Lqb65cTy
- https://trello.com/c/P9PkGJOH
- https://trello.com/c/WyRZqJ21
- https://trello.com/c/51EhDWLs
- https://trello.com/c/w7Z9HuLw
- https://trello.com/c/AFHXgx3l

---

## 4 Gennaio 2021 - v1.21.0104

Versione 1.1.01

Nuova Grafica, Footer e Pagina di Login

Rimozione del CMS di presentazione in apertura

Correzione diversi bug in fill questionario

Varie Migliorie nel Questionario Sede

Esportazione del Piano Spostamenti Casa Lavoro - _Il PSCL di ogni sede è esportabile in un file word che può essere completato dall'utente_

![](/home/massimoi/md-images/2021-01-04-12-44-42-image.png)

---

## 15 Dicembre 2020 - v1.20.1215

Versione 1.0.51

Esportazione della mappa nei formati XLS e KML

![](/home/massimoi/.config/marktext/images/2020-12-10-19-11-20-image.png)

Nuova interfaccia utente del questionario

![](/home/massimoi/.config/marktext/images/2020-12-15-06-11-02-image.png)

Gestione corretta invii quiestionario e relativi menu

![](/home/massimoi/.config/marktext/images/2020-12-15-06-14-12-image.png)

Miglioramenti gestione Auth0

Esportazione PSCL in formato Word (1 versione)

![](/home/massimoi/.config/marktext/images/2020-12-15-06-15-14-image.png)

Gestione domande dipendenti e relative migliorie interfaccia utente

![](/home/massimoi/.config/marktext/images/2020-12-15-06-16-15-image.png)

Indicazione del numero di versione nella barra dei menu e nel footer

![](/home/massimoi/.config/marktext/images/2020-12-15-06-11-37-image.png)

## Aggiornamento Matrice tracciabilità

- Fatto FN1
- Fatto FN2
- Fatto FN3
- Fatto FN4
- Fatto FN5
- Fatto FN6
- Fatto FN7
- In corso FN8 - 80% - manca definizione catalogo
- Fatto FN9
- In corso FN10 - 80% - manca definizione catalogo
- Fatto FN11
- Fatto FN12
- **Fatto FN13**
- Fatto FN14
- Fatto FN15
- Fatto FN16
- Fatto FN17
- Fatto FN18
- Fatto FN19
- Fatto FN20
- Fatto FN21
- Fatto FN22
- Fatto FN23
- Fatto FN24
- Fatto FN25
- Fatto FN26
- Fatto FN27
- **FN28**
- In corso FN29 - 99% - da discutere
- Fatto FN30
- Fatto FN31
- **FN32**
- Fatto FN33
- In corso FN35 - 90%
- Fatto FN36
- Fatto FN37
- Fatto FN38
- Fatto FN39
- In corso FN40 - 50% - specifiche da definire
- **FN41**
- In corso FN42 - 80%
- **FN43** - da definire insieme agli indicatori FN29
- Fatto FN44
- Fatto FN45
- Fatto FN46
- Fatto FN47

---

## 1 Dicembre 2020 v1.20.1201

Versione 1.0.0

Il progetto è stato ristrutturato in 3 moduli per facilitare la manutenzione.
5t.vue -> il frontend in vue
5t.api -> il backend che gestisce le API (cakephp 4)
5t.web -> il frontend web (cakephp 4)

Seguono le feature implementate e quelle che richiedono ancora qualche finalizzazione

Fatto FN1
Fatto FN2
Fatto FN3
Fatto FN4
Fatto FN5
Fatto FN6
Fatto FN7
In corso FN8 - 80%
Fatto FN9
In corso FN10 - 80%
Fatto FN11
Fatto FN12
In corso FN13 - 99%
Fatto FN14
Fatto FN15
Fatto FN16
Fatto FN17
Fatto FN18
Fatto FN19
Fatto FN20
Fatto FN21
Fatto FN22
Fatto FN23
Fatto FN24
Fatto FN25
Fatto FN26
Fatto FN27
In corso FN28 - 90%
In corso FN29 - 99%
Fatto FN30
Fatto FN31
In corso FN32 - 60%
Fatto FN33
In corso FN35 - 90%
Fatto FN36
Fatto FN37
Fatto FN38
Fatto FN39
In corso FN40 - 50%
In corso FN41 - 80%
In corso FN42 - 80%
In corso FN43 - 50%
Fatto FN44
Fatto FN45
Fatto FN46
Fatto FN47

---

## Data: 14 Settembre 2020 - v0.20.0914-beta
Versione Software: 0.9beta

## Installazione Presso 5T
La versione installata è costituita di 3 componenti

**Backend**
- Angelcake CMS
- 5TMoma Plugin

**Frontend**
- 5TMoma Vue
Tutti i componenti sono scaricabili facendo pull dalla versione più aggiornata dei repository di BitBucket
<https://bitbucket.org/mobilitymanagerplatform/>
Il server di BackEnd è  raggiungibile, dalla vpn, all'indirizzo <http://test.5t.torino.it:93>
Il server di FontEnd è  raggiungibile, dalla vpn, all'indirizzo [http://test.5t.torino.it:94](http://test.5t.torino.it:93)

## Istruzioni Installazione

- Sono presenti 3 container Docker
  - MYSQL (versione 8.x)
  - Apache / PHP 7.3
  - Nginx per il frontend
- Il container MYSQL (in questa fase è adatto anche MariaDB, ma per il futuro è da prevedere l'uso di MYSQL per gestire le funzioni JSON/NOSql e GIS) riceve il dump del db aggiornato - <https://owncloud.impronta48.it/index.php/f/1567251>
- Il container Apache/PHP riceve
  - Clone del repository AngelCake
  - Clone del repository 5T Moma Plugin nella cartella plugins/Moma
  - composer update
  - copia dei file di configurazione nella cartella config (con adattamento di username/pwd in modo che corrispondano al database ed al server SMTP dell'ambiente 5T)
  - copia dei file statici (immagini, allegati) nella cartella webroot
    - <https://owncloud.impronta48.it/index.php/s/3cqrCTrsj2GMBKs>
- Modifica dei file .production.env in modo che corrisponda all'indirizzo del server di backend
- Re-build dell'APP Vue tramite il comando
  - npm run build

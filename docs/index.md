### Importazione delle origini dei dipendenti del questionario
REQUEST_METHOD=GET ./bin/cake import_origins_from_answers 1
Indicare gli ID delle domande che contengono l'origine (nel questionario) nel file bootstap.php del plugin

### Creazione di un utente per ogni azienda (company mobility manager)
bin/cake create_users_from_companies
questo batch crea un utente per ogni azienda.

bin/cake create_users_from_companies --company_type_id=9 --pwd_prefix=orariscuole --role=superiori --self=0
- il parametro self ==> 0 (nel company_id metto null, altrimenti metto il company_id)
- role => Ruolo con cui viene creata l'azienda
- prefix => prefisso dell'orario scuole
- tipo di azienda per cui generare la pwd

bin/cake create_users_from_offices  --pwd_prefix=orariscuole --role=superiori --self=1 --min_id=869

### Invio delle mail digest
Manda tutte le notifiche digest per il timetable
HTTP_HOST=5t.impronta48.it  bin/cake timetable_digest_notifications --no-reset 1 --test 1

Significato dei parametri:
HTTP_HOST=5t.impronta48.it  deve indicare l'ambiente API su cui vogliamo operare
--no-reset 1  <-- Indica che non viene resettato il flag "da notificare" (utile per fare le prove)
--test 1  <-- Invece di mandare la mail a tutti i destinatari la manda solo all'utente di test (admin)

# Creazione Utenti
    admin: admin@nomail.com (può fare tutto)
    moma - empty company --> Mobility Manager d'Area (può vedere tutte le aziende non può creare nuovi utenti)
    moma - with company --> Mobility Manager di Azienda (può vedere solo la sua azienda e creare nuovi utenti nella sua azienda)
    user (può vedere solo il suo profilo)

# Creazione Utenti
Per creare un nuovo utente bisogna essere loggati come amministratore.
Andare nella sezione aziende e creare l'azienda (opzionale)
Andare nella sezione utenti e creare l'utente (pulsante verde in alto a destra), associando azienda e ruolo

# Invio delle email in coda

HTTP_HOST=5t.impronta48.it bin/cake EmailQueue.sender -l 1

# Migrazione di cambiamenti di struttura del DB
bin/cake migrations migrate
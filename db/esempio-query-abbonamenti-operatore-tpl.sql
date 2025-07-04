SELECT
      subscription->>"$.operatore",
      subscription->>"$.prezzo_pieno" as 'Tariffa Abbonamento',
      subscription->>"$.nome" as Nome,
      subscription->>"$.cognome" as Cognome,
      9999 as 'Codice Azienda (Service)',
      t.code as 'Codice Azienda',
      t.name as 'nome Azienda',
      subscription->>"$.data_nascita" as 'Data di Nascita',
      subscription->>"$.comune_nascita" as 'Luogo di Nascita',
      subscription->>"$.cittadinanza" as 'Nazionalita',
      subscription->>"$.codice_fiscale" as 'Codice Fiscale',
      subscription->>"$.residenza" as 'Indirizzo di Residenza',
      subscription->>"$.cap_residenza" as 'CAP di Residenza',
      subscription->>"$.comune_residenza" as 'Citta  di Residenza',
      subscription->>"$.provincia_residenza" as 'Provincia di Residenza',
      subscription->>"$.sesso" as Sesso,
      subscription->>"$.telefono" as 'Numero Telefono 1',
      '--' as 'Numero Telefono 2',
      subscription->>"$.email" as 'Indirizzo eMail',
      '' as 'Non utilizzato',
      if(subscription->>"$.tessera_tpl", 'NU', 'RN') as 'Nuova Richiesta o Rinnovo',
      if(subscription->>"$.stato_privacy"='accettato', 'S', '') as 'Privacy SI', 
      '--' as 'Descrizione Azienda (Destinatario Merci)',
      Companies.company_code as 'Codice Azienda (Destinatario Merci)'
    FROM
      subscriptions Subscriptions
      left join companies Companies on (Subscriptions.company_id = Companies.id)
      left join tpl_operators t on (t.company_id = Subscriptions.company_id and t.name = Subscriptions.subscription->>"$.operatore")


# Come convertire le opzioni delle domande dal formato "causale" al formato JSON

## Problema
Inizialmente le opzioni delle domande erano scritte in formato libero dentro il campo di testo options.
Le domande di testo erano un testo
Le domande singole o multiple un array json

Ho scelto di migrare tutto ad un campo json di mysql8, in modo da poter fare interrogazioni anche sui campi delle domande 
e gestire in modo trasparente la codifica in json tramite CakePHP complex types
https://book.cakephp.org/4/en/orm/saving-data.html#saving-complex-types

## Soluzione
Purtroppo la soluzione non è 100% automatica, ma richiede due passaggi manuali

## Passaggi
- Spegni lo schema json di cakephp nelle questions e nelle answers
  - src/AnswersTable.php -> commenti riga 82 (_initializeSchema)
  - src/QuestionsTable.php -> commenti riga 88,89 (_initializeSchema)

- Converti tutte le question text in json (usando lo script:
  ```bash
  # bin/cake cache clear_all
  # bin/cake ConvertQOptionsJson
  ```

- Riattivi lo schema json di cakephp nelle questions e nelle answers
 - src/AnswersTable.php -> scommenti riga 82 (_initializeSchema)
 - src/QuestionsTable.php -> scommenti riga 88,89 (_initializeSchema)

- Converti tutte le risposte singole e multiple nel corretto formato json
  ```bash
  # bin/cake ConvertArrayAnswers
  ```
- Converti tutte le risposte di testo nel corretto formato json
  ```bash
  # bin/cake ConvertSingleJsonAnswer
  ```


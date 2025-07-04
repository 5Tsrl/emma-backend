## Array Question
### Estrarre tutti i valori
SELECT 
  answer->>"$.Comfort" comfort,
  answer->>"$.Puntualità" puntualità,  
  answer->>"$.Informazione" informazione,
  answer->>"$.""Tempo di viaggio""" tempo_di_viaggio,
  answer->>"$.Tariffe" tariffe,
  answer->>"$.Frequenza" frequenza,
  answer->>"$.""Vicinanza alle fermate""" vicinanza_alle_fermate,
  answer->>"$.Sicurezza" sicurezza
FROM 
  answers Answers 
WHERE 
  (
    question_id = 175 
  ) ;

## Array Question
### Analizzare un singolo valore
SELECT
  answer->>"$.Comfort" comfort, count(answer->>"$.Comfort")
FROM 
  answers 
WHERE 
    question_id = 175 
group by comfort;

## Multiple Question
SELECT
  id, answer
FROM 
  answers 
WHERE 
    question_id = 168
;


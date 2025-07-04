## Tutte le risposte del questionario comprese le loro origin
SELECT * 
FROM answers a
inner join questions q on (question_id = q.id)
left join origins o on (o.survey_id = a.survey_id and o.user_id = a.user_id)
left join users u on (a.user_id = u.id)
order by a.user_id, a.question_id;

## Genero tutti gli utenti per le origin che non li hanno
insert into users (id, email, username, active, password) 
select distinct user_id, CONCAT(user_id, '@nomail.com'),CONCAT(user_id, '@nomail.com'), 0, 'difficult' from origins o where o.survey_id  is not null; 

## Gli utenti che hanno risposto ad una certa domanda
select
	DISTINCT user_id 
from
	answers 
where
	(question_id = 40 and answer = "Femmina")
	#or (question_id  =42 and answer like "%TO%")		## Uso il like
	or (question_id  =6 and answer = "tra 15 e 30 minuti")
group by 
	user_id 
having 
	count (distinct question_id) = 2;

## Modo alternativo per fare la query sopra
## ATTENZIONE FA OR non AND tra le condizioni!
select
	DISTINCT  user_id 
from
	answers 
where
	(question_id, answer) in (
		(40, "Femmina"),
		## (42, "TO") ##Forse differisce a causa del like
		(6, "tra 15 e 30 minuti")
	);

## Estraggo le origin degli utenti che hanno risposto ad una certa domanda
select * 
from origins o  
join users u on (o.user_id = u.id)
left join employees e on (e.user_id = u.id)
left join companies c2 on (u.company_id = c2.id)
where o.user_id in 
(select
	user_id 
from
	answers 
where
	(question_id = 40 and answer = "Femmina")
	or (question_id  =42 and answer like "%TO%")
group by 
	user_id 
having 
	count (distinct question_id) = 2
);


### Pivot Table Answers
SELECT
    user_id,  
    CASE WHEN (question_id =1) THEN a.answer ELSE NULL END AS q1,
    CASE WHEN (question_id =2) THEN a.answer ELSE NULL END AS q2,
    CASE WHEN (question_id =3) THEN a.answer ELSE NULL END AS q3,
    CASE WHEN (question_id =4) THEN a.answer ELSE NULL END AS q4,
    CASE WHEN (question_id =5) THEN a.answer ELSE NULL END AS q5,
    CASE WHEN (question_id =6) THEN a.answer ELSE NULL END AS q6
FROM 
    answers a
GROUP BY 
    user_id; 

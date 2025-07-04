select 
	km_percorsi.user_id,	
	km_percorsi.answer, 
	giorni_sede.giorni_sede, 
	mezzo.answer, 
	carburante.answer, 	
	km_percorsi.answer*giorni_sede.giorni_sede*2*44*0.7853/1000 as emissioni_co_kg_anno, #44 settimane lavorative/anno consideranto 220 giorni lavorativi/anno
	km_percorsi.answer*giorni_sede.giorni_sede*2*44*163.0846/1000 as  emissioni_co2_kg_anno,# la constante 163.0846 sono le emissioni di CO2/km
	km_percorsi.answer*giorni_sede.giorni_sede*2*44*0.4256/1000 as  emissioni_nox_kg_anno,
	km_percorsi.answer*giorni_sede.giorni_sede*2*44*0.0297/1000 as  emissioni_pm10_kg_anno
# TAB 1
from (select user_id, answer 
FROM answers
where question_id=3) as km_percorsi,
# TAB 2 
(select user_id,
	CASE
		WHEN answer = 'Sette' THEN 7
		WHEN answer = 'Sei' THEN 6
		WHEN answer = 'Cinque' THEN 5
		WHEN answer = 'Quattro' THEN 4
		WHEN answer = 'Tre' THEN 3
		WHEN answer = 'Due' THEN 2
		WHEN answer = 'Uno' THEN 1
		ELSE 0
	END as giorni_sede
FROM answers
where question_id=194) as  giorni_sede,
# TAB 3 
(select answer , user_id
FROM answers
where question_id=10) as mezzo,
# TAB 4 
(select answer , user_id
FROM answers
where question_id=16) as carburante
# WHERE
where km_percorsi.user_id = giorni_sede.user_id
and km_percorsi.user_id = mezzo.user_id
and km_percorsi.user_id = carburante.user_id










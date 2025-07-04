insert into offices (name, company_id) 
SELECT distinct a.answer, surveys.company_id FROM 
answers a 
inner join surveys 
on (a.survey_id = surveys.id) 
where 
a.question_id = 39;
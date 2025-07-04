update questions set options = "[]" where options is null or options="";

ALTER TABLE questions
CHANGE COLUMN `options` `options` JSON NULL DEFAULT NULL COMMENT 'Json contenente {id, label} - es {id: 23, label: \'meno di 23 minuti\'}';

-- Adiciona o CNPJ da escola. Pode ser executada novamente sem erro.
USE `db-acme-tarde`;

SET @has_school_cnpj = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schools'
      AND COLUMN_NAME = 'cnpj'
);
SET @add_school_cnpj = IF(
    @has_school_cnpj = 0,
    'ALTER TABLE schools ADD COLUMN cnpj CHAR(14) NULL UNIQUE AFTER code',
    'SELECT 1'
);
PREPARE school_cnpj_statement FROM @add_school_cnpj;
EXECUTE school_cnpj_statement;
DEALLOCATE PREPARE school_cnpj_statement;

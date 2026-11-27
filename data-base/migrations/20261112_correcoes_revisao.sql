-- Ajustes da revisão final. Execute depois das migrações anteriores.
-- Todas as alterações são aditivas e podem ser executadas novamente sem erro.
USE `db-acme-tarde`;

-- -----------------------------------------------------
-- 1. Imagens dos agendamentos: mesmo formato da tabela
--    product_images da Etapa 07 (image_path, alt_text, position)
-- -----------------------------------------------------
SET @has_image_path = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_images' AND COLUMN_NAME = 'image_path'
);
SET @sql = IF(@has_image_path = 0,
    'ALTER TABLE appointment_images ADD COLUMN image_path VARCHAR(255) NULL AFTER appointment_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_alt_text = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_images' AND COLUMN_NAME = 'alt_text'
);
SET @sql = IF(@has_alt_text = 0,
    'ALTER TABLE appointment_images ADD COLUMN alt_text VARCHAR(255) DEFAULT NULL AFTER image_path',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_position = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_images' AND COLUMN_NAME = 'position'
);
SET @sql = IF(@has_position = 0,
    'ALTER TABLE appointment_images ADD COLUMN position SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER alt_text',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Converte o nome antigo (stored_name) para o caminho relativo e remove as colunas antigas.
SET @has_stored_name = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_images' AND COLUMN_NAME = 'stored_name'
);
SET @sql = IF(@has_stored_name = 1,
    'UPDATE appointment_images SET image_path = CONCAT(''uploads/appointments/'', stored_name) WHERE image_path IS NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_stored_name = 1, 'ALTER TABLE appointment_images DROP COLUMN stored_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_mime_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_images' AND COLUMN_NAME = 'mime_type'
);
SET @sql = IF(@has_mime_type = 1, 'ALTER TABLE appointment_images DROP COLUMN mime_type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE appointment_images MODIFY image_path VARCHAR(255) NOT NULL;

-- -----------------------------------------------------
-- 2. Fotos de perfil: o banco passa a guardar o caminho relativo (uploads/users/arquivo)
-- -----------------------------------------------------
UPDATE users
SET photo = CONCAT('uploads/users/', photo)
WHERE photo IS NOT NULL AND photo <> '' AND photo NOT LIKE 'uploads/%';

-- -----------------------------------------------------
-- 3. Categorias de FAQs
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS faqs_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_faqs_categories_name (name)
);

INSERT INTO faqs_categories (name, active)
SELECT 'Geral', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM faqs_categories);

SET @has_faq_category = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faqs' AND COLUMN_NAME = 'faqs_category_id'
);
SET @sql = IF(@has_faq_category = 0,
    'ALTER TABLE faqs ADD COLUMN faqs_category_id INT NULL AFTER id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE faqs
SET faqs_category_id = (SELECT MIN(id) FROM faqs_categories)
WHERE faqs_category_id IS NULL;

ALTER TABLE faqs MODIFY faqs_category_id INT NOT NULL;

SET @has_faq_fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faqs' AND CONSTRAINT_NAME = 'fk_faqs_category'
);
SET @sql = IF(@has_faq_fk = 0,
    'ALTER TABLE faqs ADD CONSTRAINT fk_faqs_category FOREIGN KEY (faqs_category_id) REFERENCES faqs_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 4. Mensagens do formulário de contato
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

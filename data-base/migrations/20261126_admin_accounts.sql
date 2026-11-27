-- Atualiza as contas administrativas iniciais sem recriar tabelas nem tocar no banco local.
USE `db-acme-tarde`;

UPDATE users
SET email = 'admin@gmail.com',
    password = '$2y$12$vJlLDWL/9215xMybvL9sGu0FVkVGKpSqBJHDccqXj0X30jM.knrWq'
WHERE type_id = 1
  AND name = 'Administrador Global'
  AND email IN ('global@eduschedule.test', 'admin@gmail.com');

UPDATE users
SET email = 'escola@gmail.com',
    password = '$2y$12$vJlLDWL/9215xMybvL9sGu0FVkVGKpSqBJHDccqXj0X30jM.knrWq'
WHERE type_id = 2
  AND name = 'Administrador Escolar'
  AND email IN ('admin@ifsul.edu.br', 'escola@gmail.com');

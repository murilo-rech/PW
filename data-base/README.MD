# 📁 data-base/

## Banco de dados — `db-acme-tarde` (MySQL/MariaDB)

| Arquivo | Uso |
|---|---|
| `EduScheduleC.sql` | Script completo: **apaga e recria** todas as tabelas e insere os dados iniciais |
| `EduSchedule.mwb` / `EduScheduleS.mwb` | Diagramas ER (MySQL Workbench) |
| `migrations/20261029_etapa07_uploads.sql` | Etapa 07: coluna `appointments.status` e tabela `appointment_images` |
| `migrations/20261106_school_cnpj.sql` | Coluna `schools.cnpj` |
| `migrations/20261112_correcoes_revisao.sql` | Galeria no formato da Etapa 07 (`image_path`, `alt_text`, `position`), caminhos das fotos, categorias de FAQ e tabela `contacts` |
| `migrations/20261126_admin_accounts.sql` | Atualiza os e-mails e hashes de senha dos administradores iniciais |

Todas as migrações verificam o que já existe antes de alterar, então podem ser executadas novamente e também depois do script completo sem erro.

### Banco novo

1. Importe `EduScheduleC.sql` (phpMyAdmin ou `mysql -u root < EduScheduleC.sql`).
2. Execute as migrações em ordem de data (opcional em banco novo: o script completo já contém tudo).

### Banco existente com dados

Não importe o script completo. Execute somente as migrações, em ordem de data.

## Universo de discurso

| Tabela | Objetivo | Relações |
|---|---|---|
| `plans` | Planos do SaaS (preço e limites de alunos/professores; `0` = ilimitado) | 1:N com `schools` |
| `schools` | Instituições clientes, identificadas pelo código público usado no cadastro | N:1 `plans`; 1:N `users`, `subjects` |
| `users` | Usuários: 1 admin global, 2 admin escolar, 3 professor, 4 aluno. `photo` guarda o caminho relativo da foto | N:1 `schools` |
| `addresses` | Endereços do usuário (perfil) | N:1 `users` |
| `subjects` | Matérias de uma escola | N:1 `schools`; N:N `users` (professores) via `teacher_subjects` |
| `teacher_subjects` | Matérias que cada professor atende | N:1 `users`, N:1 `subjects` |
| `availabilities` | Horários semanais publicados pelo professor | N:1 `users`, N:1 `subjects`; 1:N `appointments` |
| `appointments` | Agendamentos dos alunos (entidade principal) com `status` | N:1 `availabilities`, N:1 `users` |
| `appointment_images` | Imagens anexadas ao agendamento (1:N, `ON DELETE CASCADE`) | N:1 `appointments` |
| `notifications` | Avisos automáticos para cada usuário | N:1 `users` |
| `faqs_categories` | Categorias das perguntas frequentes | 1:N `faqs` |
| `faqs` | Perguntas frequentes da área pública | N:1 `faqs_categories` |
| `contacts` | Mensagens do formulário de contato | — |

As exclusões são lógicas (`active = 0`), exceto a remoção de uma imagem da galeria.

### Usuários iniciais

O script cria o administrador global (`admin@gmail.com`) e o administrador da escola de exemplo (`escola@gmail.com`). Os dois registros recebem o hash de senha definido para este projeto.
